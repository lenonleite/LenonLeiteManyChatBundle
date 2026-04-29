<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('manychat_enabled', YesNoButtonGroupType::class, [
            'label'      => 'lenonleitemanychat.config.form.manychat_enabled.label',
            'label_attr' => ['class' => 'control-label'],
            'required'   => true,
            'attr'       => ['tooltip' => 'lenonleitemanychat.config.form.manychat_enabled.tooltip'],
        ]);

        $builder->add('manychat_api_key', TextType::class, [
            'label'      => 'lenonleitemanychat.config.form.manychat_api_key.label',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'lenonleitemanychat.config.form.manychat_api_key.tooltip',
            ],
        ]);

        $builder->add('manychat_webhook_secret', TextType::class, [
            'label'      => 'lenonleitemanychat.config.form.manychat_webhook_secret.label',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'lenonleitemanychat.config.form.manychat_webhook_secret.tooltip',
            ],
        ]);

        $builder->add('manychat_contact_lookup_field', ChoiceType::class, [
            'label'      => 'lenonleitemanychat.config.form.manychat_contact_lookup_field.label',
            'label_attr' => ['class' => 'control-label'],
            'required'   => true,
            'choices'    => [
                'Email' => 'email',
                'Phone' => 'phone',
            ],
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'lenonleitemanychat.config.form.manychat_contact_lookup_field.tooltip',
            ],
        ]);

        $builder->add('manychat_tag_prefix', TextType::class, [
            'label'      => 'lenonleitemanychat.config.form.manychat_tag_prefix.label',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'lenonleitemanychat.config.form.manychat_tag_prefix.tooltip',
            ],
        ]);

        $builder->add('manychat_sync_direction', ChoiceType::class, [
            'label'      => 'lenonleitemanychat.config.form.manychat_sync_direction.label',
            'label_attr' => ['class' => 'control-label'],
            'required'   => true,
            'choices'    => [
                'ManyChat -> Mautic (recommended first)' => 'manychat_to_mautic',
                'Mautic -> ManyChat'                     => 'mautic_to_manychat',
                'Two-way later'                          => 'bidirectional',
            ],
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'lenonleitemanychat.config.form.manychat_sync_direction.tooltip',
            ],
        ]);

        $builder->add('manychat_field_update_mode', ChoiceType::class, [
            'label'      => 'lenonleitemanychat.config.form.manychat_field_update_mode.label',
            'label_attr' => ['class' => 'control-label'],
            'required'   => true,
            'choices'    => [
                'Overwrite existing Mautic values' => 'overwrite',
                'Only fill empty Mautic values'    => 'fill_empty',
            ],
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'lenonleitemanychat.config.form.manychat_field_update_mode.tooltip',
            ],
        ]);
    }
}
