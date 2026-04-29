<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Tests\Functional\Controller;

use MauticPlugin\LenonLeiteManyChatBundle\Controller\WebhookController;
use MauticPlugin\LenonLeiteManyChatBundle\Service\ManyChatConfig;
use MauticPlugin\LenonLeiteManyChatBundle\Service\ManyChatPayloadNormalizer;
use MauticPlugin\LenonLeiteManyChatBundle\Service\ManyChatSyncLogger;
use MauticPlugin\LenonLeiteManyChatBundle\Service\MauticContactUpsertService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookControllerTest extends TestCase
{
    private ManyChatConfig&MockObject $config;

    private ManyChatPayloadNormalizer $payloadNormalizer;

    private MauticContactUpsertService&MockObject $contactUpsertService;

    private ManyChatSyncLogger&MockObject $syncLogger;

    private WebhookController $controller;

    protected function setUp(): void
    {
        $this->config               = $this->createMock(ManyChatConfig::class);
        $this->payloadNormalizer    = new ManyChatPayloadNormalizer();
        $this->contactUpsertService = $this->createMock(MauticContactUpsertService::class);
        $this->syncLogger           = $this->createMock(ManyChatSyncLogger::class);

        $this->controller = new WebhookController(
            $this->config,
            $this->payloadNormalizer,
            $this->contactUpsertService,
            $this->syncLogger
        );
    }

    public function testCreatesAndUpdatesContact(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isValidWebhookSecret')->with('test-secret')->willReturn(true);

        $this->contactUpsertService
            ->expects(self::exactly(2))
            ->method('upsertFromPayload')
            ->willReturnCallback(function (array $normalizedPayload): array {
                if ('mc_123' === ($normalizedPayload['manychat_subscriber_id'] ?? null)) {
                    self::assertSame('john@example.com', $normalizedPayload['email']);
                    self::assertSame('John', $normalizedPayload['firstname']);
                    self::assertSame('Doe', $normalizedPayload['lastname']);
                    self::assertSame('instagram', $normalizedPayload['manychat_channel']);
                    self::assertSame('Spring Launch', $normalizedPayload['manychat_campaign_name']);
                    self::assertSame(['lead', 'vip'], $normalizedPayload['tags']);

                    return [
                        'contact_id'      => 101,
                        'created'         => true,
                        'updated_fields'  => ['email', 'firstname', 'lastname', 'manychat_subscriber_id'],
                        'ignored_fields'  => [],
                        'applied_tags'    => ['manychat:lead', 'manychat:vip'],
                        'lookup_strategy' => 'email',
                    ];
                }

                self::assertSame('mc_existing', $normalizedPayload['manychat_subscriber_id']);
                self::assertSame('john@example.com', $normalizedPayload['email']);
                self::assertSame('Updated', $normalizedPayload['firstname']);
                self::assertSame(['warm'], $normalizedPayload['tags']);

                return [
                    'contact_id'      => 101,
                    'created'         => false,
                    'updated_fields'  => ['firstname', 'manychat_subscriber_id'],
                    'ignored_fields'  => [],
                    'applied_tags'    => ['manychat:warm'],
                    'lookup_strategy' => 'email',
                ];
            });

        $this->syncLogger->expects(self::exactly(2))->method('info');

        $createResponse = $this->controller->syncAction($this->createJsonRequest([
            'subscriber_id' => 'mc_123',
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'email'         => 'john@example.com',
            'channel'       => 'instagram',
            'tags'          => ['lead', 'vip'],
            'custom_fields' => [
                'campaign_name' => 'Spring Launch',
            ],
        ], 'test-secret'));

        self::assertSame(Response::HTTP_OK, $createResponse->getStatusCode());
        self::assertSame(
            [
                'success'         => true,
                'contact_id'      => 101,
                'created'         => true,
                'updated_fields'  => ['email', 'firstname', 'lastname', 'manychat_subscriber_id'],
                'ignored_fields'  => [],
                'applied_tags'    => ['manychat:lead', 'manychat:vip'],
                'lookup_strategy' => 'email',
            ],
            json_decode((string) $createResponse->getContent(), true)
        );

        $updateResponse = $this->controller->syncAction($this->createJsonRequest([
            'subscriber_id' => 'mc_existing',
            'email'         => 'john@example.com',
            'first_name'    => 'Updated',
            'tags'          => ['warm'],
        ], 'test-secret'));

        self::assertSame(Response::HTTP_OK, $updateResponse->getStatusCode());
        self::assertSame(
            [
                'success'         => true,
                'contact_id'      => 101,
                'created'         => false,
                'updated_fields'  => ['firstname', 'manychat_subscriber_id'],
                'ignored_fields'  => [],
                'applied_tags'    => ['manychat:warm'],
                'lookup_strategy' => 'email',
            ],
            json_decode((string) $updateResponse->getContent(), true)
        );
    }

    public function testRejectsInvalidSecret(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isValidWebhookSecret')->with('wrong-secret')->willReturn(false);
        $this->contactUpsertService->expects(self::never())->method('upsertFromPayload');
        $this->syncLogger->expects(self::once())->method('warning');

        $response = $this->controller->syncAction($this->createJsonRequest([
            'email' => 'secret@example.com',
        ], 'wrong-secret'));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(
            [
                'success' => false,
                'message' => 'Invalid webhook secret.',
            ],
            json_decode((string) $response->getContent(), true)
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createJsonRequest(array $payload, string $secret): Request
    {
        $request = Request::create(
            '/manychat/webhook/contact-sync',
            Request::METHOD_POST,
            [],
            [],
            [],
            [],
            json_encode($payload)
        );
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('X-ManyChat-Secret', $secret);

        return $request;
    }
}
