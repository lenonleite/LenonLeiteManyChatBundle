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
}
