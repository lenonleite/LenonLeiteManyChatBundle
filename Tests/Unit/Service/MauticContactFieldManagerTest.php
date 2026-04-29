<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Tests\Unit\Service;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LenonLeiteManyChatBundle\Service\MauticContactFieldManager;
use PHPUnit\Framework\TestCase;

class MauticContactFieldManagerTest extends TestCase
{
    public function testEnsureFieldsSupportsTraversableLeadFields(): void
    {
        $existingField = new LeadField();
        $existingField->setAlias('manychat_subscriber_id');

        $fieldModel = $this->createMock(FieldModel::class);
        $fieldModel->expects(self::once())
            ->method('getLeadFields')
            ->willReturn(new \ArrayIterator([$existingField]));

        $fieldModel->expects(self::once())
            ->method('saveEntity')
            ->willReturnCallback(function (LeadField $field): void {
                self::assertSame('manychat_channel', $field->getAlias());
                self::assertSame('ManyChat Channel', $field->getName());
                self::assertSame('text', $field->getType());
                self::assertSame('lead', $field->getObject());
                self::assertSame('social', $field->getGroup());
            });

        $manager = new MauticContactFieldManager($fieldModel);
        $manager->ensureFields(['manychat_subscriber_id', 'manychat_channel']);
    }
}
