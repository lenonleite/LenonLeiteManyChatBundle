<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LenonLeiteManyChatBundle\Tests\Traits\ActivePluginTrait;
use MauticPlugin\LenonLeiteManyChatBundle\Tests\Traits\HelperEntitiesTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

class WebhookControllerTest extends MauticMysqlTestCase
{
    use ActivePluginTrait;
    use HelperEntitiesTrait;

    protected $useCleanupRollback = false;

    protected function setUp(): void
    {
        $this->configParams['manychat_enabled'] = true;
        $this->configParams['manychat_webhook_secret'] = 'test-secret';
        $this->configParams['manychat_contact_lookup_field'] = 'email';
        $this->configParams['manychat_tag_prefix'] = 'manychat:';
        $this->configParams['manychat_sync_direction'] = 'manychat_to_mautic';

        parent::setUp();
        $this->activePlugin();
        $this->ensureWebhookRoute();
    }

    private function ensureWebhookRoute(): void
    {
        $routes = $this->router->getRouteCollection();
        if (null !== $routes->get('lenonleite_manychat_webhook_sync')) {
            return;
        }

        $routes->add('lenonleite_manychat_webhook_sync', new Route(
            '/manychat/webhook/contact-sync',
            ['_controller' => \MauticPlugin\LenonLeiteManyChatBundle\Controller\WebhookController::class.'::syncAction'],
            [],
            [],
            '',
            [],
            ['POST']
        ));
    }

    public function testCreatesContactAndManyChatFields(): void
    {
        $payload = [
            'subscriber_id' => 'mc_123',
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'email'         => 'john@example.com',
            'channel'       => 'instagram',
            'tags'          => ['lead', 'vip'],
            'custom_fields' => [
                'campaign_name' => 'Spring Launch'
            ]
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/manychat/webhook/contact-sync',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_MANYCHAT_SECRET' => 'test-secret'
            ],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $data = json_decode((string) $response->getContent(), true);
        self::assertTrue($data['success']);
        self::assertTrue($data['created']);
        self::assertContains('manychat:lead', $data['applied_tags']);
        self::assertContains('manychat:vip', $data['applied_tags']);

        $this->em->clear();
        $lead = $this->em->getRepository(Lead::class)->find($data['contact_id']);
        self::assertInstanceOf(Lead::class, $lead);

        $fields = $this->em->getRepository(Lead::class)->getFieldValues($lead->getId());
        $lead->setFields($fields);

        self::assertSame('john@example.com', $lead->getFieldValue('email'));
        self::assertSame('John', $lead->getFieldValue('firstname'));
        self::assertSame('Doe', $lead->getFieldValue('lastname'));
        self::assertSame('mc_123', $lead->getFieldValue('manychat_subscriber_id'));
        self::assertSame('instagram', $lead->getFieldValue('manychat_channel'));
        self::assertSame('Spring Launch', $lead->getFieldValue('manychat_campaign_name'));

        $leadTags = array_map(static fn ($tag): string => $tag->getTag(), $lead->getTags()->toArray());
        self::assertContains('manychat:lead', $leadTags);
        self::assertContains('manychat:vip', $leadTags);
    }

    public function testUpdatesExistingContact(): void
    {
        $lead = $this->createLead('Existing', 'existing@example.com');

        $payload = [
            'subscriber_id' => 'mc_existing',
            'email'         => 'existing@example.com',
            'first_name'    => 'Updated',
            'tags'          => ['warm']
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/manychat/webhook/contact-sync',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_MANYCHAT_SECRET' => 'test-secret'
            ],
            json_encode($payload)
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $data = json_decode((string) $response->getContent(), true);
        self::assertTrue($data['success']);
        self::assertFalse($data['created']);
        self::assertSame($lead->getId(), $data['contact_id']);

        $this->em->clear();
        $updatedLead = $this->em->getRepository(Lead::class)->find($lead->getId());
        self::assertInstanceOf(Lead::class, $updatedLead);
        $fields = $this->em->getRepository(Lead::class)->getFieldValues($updatedLead->getId());
        $updatedLead->setFields($fields);

        self::assertSame('Updated', $updatedLead->getFieldValue('firstname'));
        self::assertSame('mc_existing', $updatedLead->getFieldValue('manychat_subscriber_id'));
    }

    public function testRejectsInvalidSecret(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/manychat/webhook/contact-sync',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_MANYCHAT_SECRET' => 'wrong-secret'
            ],
            json_encode([
                'email' => 'secret@example.com'
            ])
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), $response->getContent());

        $data = json_decode((string) $response->getContent(), true);
        self::assertFalse($data['success']);
        self::assertSame('Invalid webhook secret.', $data['message']);
    }
}
