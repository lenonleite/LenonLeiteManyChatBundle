<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Tests\Unit\Service;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LenonLeiteManyChatBundle\Service\ManyChatConfig;
use MauticPlugin\LenonLeiteManyChatBundle\Service\MauticContactFieldManager;
use MauticPlugin\LenonLeiteManyChatBundle\Service\MauticContactUpsertService;
use PHPUnit\Framework\TestCase;

class MauticContactUpsertServiceTest extends TestCase
{
    public function testCreatesNewLeadAndPrefixesTags(): void
    {
        $repository   = $this->createMock(LeadRepository::class);
        $leadModel    = $this->createMock(LeadModel::class);
        $config       = $this->createMock(ManyChatConfig::class);
        $fieldManager = $this->createMock(MauticContactFieldManager::class);

        $repository->expects(self::once())
            ->method('getContactsByEmail')
            ->with('new@example.com')
            ->willReturn([]);
        $repository->expects(self::once())
            ->method('getLeadsByFieldValue')
            ->with('phone', '+15550000000')
            ->willReturn([]);

        $leadModel->method('getRepository')->willReturn($repository);
        $config->method('getContactLookupField')->willReturn('email');
        $config->method('getTagPrefix')->willReturn('manychat:');
        $config->method('shouldOnlyFillEmptyValues')->willReturn(false);

        $capturedFieldData = null;
        $savedLead         = null;
        $appliedTags       = null;

        $fieldManager->expects(self::once())
            ->method('ensureFields')
            ->willReturnCallback(function (array $aliases): void {
                self::assertContains('email', $aliases);
                self::assertContains('manychat_subscriber_id', $aliases);
            });

        $leadModel->expects(self::once())
            ->method('setFieldValues')
            ->willReturnCallback(function (Lead $lead, array $fieldData) use (&$capturedFieldData): void {
                $capturedFieldData = $fieldData;
                $lead->setEmail((string) $fieldData['email']);
                $lead->setFirstname((string) $fieldData['firstname']);
            });

        $leadModel->expects(self::once())
            ->method('saveEntity')
            ->willReturnCallback(function (Lead $lead) use (&$savedLead): void {
                $savedLead = $lead;
            });

        $leadModel->expects(self::once())
            ->method('modifyTags')
            ->willReturnCallback(function (Lead $lead, array $tags) use (&$appliedTags, &$savedLead): bool {
                self::assertSame($savedLead, $lead);
                $appliedTags = $tags;

                return true;
            });

        $service = new MauticContactUpsertService($leadModel, $config, $fieldManager);
        $result  = $service->upsertFromPayload([
            'email'                  => 'new@example.com',
            'phone'                  => '+15550000000',
            'firstname'              => 'New',
            'manychat_subscriber_id' => 'mc_1',
            'tags'                   => ['lead', 'vip'],
        ]);

        self::assertTrue($result['created']);
        self::assertSame('email', $result['lookup_strategy']);
        self::assertSame(['manychat:lead', 'manychat:vip'], $result['applied_tags']);
        self::assertSame(['manychat:lead', 'manychat:vip'], $appliedTags);
        self::assertSame('new@example.com', $capturedFieldData['email']);
        self::assertSame('mc_1', $capturedFieldData['manychat_subscriber_id']);
        self::assertSame(['email', 'phone', 'firstname', 'manychat_subscriber_id'], $result['updated_fields']);
    }

    public function testUsesExistingLeadWithPhoneFirstFallback(): void
    {
        $existingLead = new Lead();
        $repository   = $this->createMock(LeadRepository::class);
        $leadModel    = $this->createMock(LeadModel::class);
        $config       = $this->createMock(ManyChatConfig::class);
        $fieldManager = $this->createMock(MauticContactFieldManager::class);

        $repository->expects(self::once())
            ->method('getLeadsByFieldValue')
            ->with('phone', '+351912345678')
            ->willReturn([$existingLead]);
        $repository->expects(self::never())
            ->method('getContactsByEmail');

        $leadModel->method('getRepository')->willReturn($repository);
        $config->method('getContactLookupField')->willReturn('phone');
        $config->method('getTagPrefix')->willReturn('');
        $config->method('shouldOnlyFillEmptyValues')->willReturn(false);

        $fieldManager->expects(self::once())->method('ensureFields');
        $leadModel->expects(self::once())->method('setFieldValues');
        $leadModel->expects(self::once())->method('saveEntity')->with($existingLead);
        $leadModel->expects(self::once())->method('modifyTags')->with($existingLead, ['warm'], null, true);

        $service = new MauticContactUpsertService($leadModel, $config, $fieldManager);
        $result  = $service->upsertFromPayload([
            'phone'                  => '+351912345678',
            'manychat_subscriber_id' => 'mc_phone',
            'tags'                   => ['warm'],
        ]);

        self::assertFalse($result['created']);
        self::assertSame(['warm'], $result['applied_tags']);
        self::assertSame('phone', $result['lookup_strategy']);
    }

    public function testOnlyFillsEmptyValuesWhenConfigured(): void
    {
        $existingLead = new Lead();
        $this->setLeadId($existingLead, 77);

        $repository   = $this->createMock(LeadRepository::class);
        $leadModel    = $this->createMock(LeadModel::class);
        $config       = $this->createMock(ManyChatConfig::class);
        $fieldManager = $this->createMock(MauticContactFieldManager::class);

        $repository->expects(self::once())
            ->method('getContactsByEmail')
            ->with('existing@example.com')
            ->willReturn([$existingLead]);
        $repository->expects(self::once())
            ->method('getFieldValues')
            ->with(77)
            ->willReturn([
                'core' => [
                    'firstname' => ['type' => 'text', 'value' => 'Existing'],
                    'lastname'  => ['type' => 'text', 'value' => ''],
                ],
                'social' => [
                    'manychat_subscriber_id' => ['type' => 'text', 'value' => 'mc_old'],
                ],
            ]);

        $leadModel->method('getRepository')->willReturn($repository);
        $config->method('getContactLookupField')->willReturn('email');
        $config->method('getTagPrefix')->willReturn('manychat:');
        $config->method('shouldOnlyFillEmptyValues')->willReturn(true);

        $fieldManager->expects(self::once())
            ->method('ensureFields')
            ->with(['email', 'lastname']);

        $leadModel->expects(self::once())
            ->method('setFieldValues')
            ->with(
                $existingLead,
                [
                    'email'    => 'existing@example.com',
                    'lastname' => 'Updated',
                ],
                false,
                false
            );
        $leadModel->expects(self::once())->method('saveEntity')->with($existingLead);
        $leadModel->expects(self::never())->method('modifyTags');

        $service = new MauticContactUpsertService($leadModel, $config, $fieldManager);
        $result  = $service->upsertFromPayload([
            'email'                  => 'existing@example.com',
            'firstname'              => 'From ManyChat',
            'lastname'               => 'Updated',
            'manychat_subscriber_id' => 'mc_new',
        ]);

        self::assertFalse($result['created']);
        self::assertSame(['email', 'lastname'], $result['updated_fields']);
        self::assertSame(['firstname', 'manychat_subscriber_id'], $result['ignored_fields']);
    }

    private function setLeadId(Lead $lead, int $id): void
    {
        $property = new \ReflectionProperty(Lead::class, 'id');
        $property->setValue($lead, $id);
    }
}
