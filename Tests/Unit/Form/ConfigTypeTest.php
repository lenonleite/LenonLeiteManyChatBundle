<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Tests\Unit\Form;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use MauticPlugin\LenonLeiteManyChatBundle\Form\Type\ConfigType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfigTypeTest extends TestCase
{
    public function testBuildFormAddsExpectedFields(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $fields  = [];

        $builder->expects(self::atLeastOnce())
            ->method('add')
            ->willReturnCallback(function (string $name, string $type, array $options) use (&$fields, $builder) {
                $fields[$name] = [$type, $options];

                return $builder;
            });

        $type = new ConfigType();
        $type->buildForm($builder, []);

        self::assertSame(YesNoButtonGroupType::class, $fields['manychat_enabled'][0]);
        self::assertSame(TextType::class, $fields['manychat_api_key'][0]);
        self::assertSame(TextType::class, $fields['manychat_webhook_secret'][0]);
        self::assertSame(ChoiceType::class, $fields['manychat_contact_lookup_field'][0]);
        self::assertSame(ChoiceType::class, $fields['manychat_sync_direction'][0]);
        self::assertSame(ChoiceType::class, $fields['manychat_field_update_mode'][0]);
        self::assertSame('lenonleitemanychat.config.form.manychat_enabled.label', $fields['manychat_enabled'][1]['label']);
        self::assertSame(['Email' => 'email', 'Phone' => 'phone'], $fields['manychat_contact_lookup_field'][1]['choices']);
        self::assertSame(
            ['Overwrite existing Mautic values' => 'overwrite', 'Only fill empty Mautic values' => 'fill_empty'],
            $fields['manychat_field_update_mode'][1]['choices']
        );
    }
}
