<?php

return [
    'name'        => 'Lenon Leite',
    'description' => 'ManyChat Connector',
    'version'     => '0.1.0',
    'author'      => 'Lenon Leite',
    'routes'      => [
        'public' => [
            'lenonleite_manychat_webhook_sync' => [
                'path'       => '/manychat/webhook/contact-sync',
                'controller' => 'MauticPlugin\LenonLeiteManyChatBundle\Controller\WebhookController::syncAction',
            ],
        ],
    ],
    'services'    => [
        'integrations' => [
            'mautic.integration.lenonleitemanychat' => [
                'class' => MauticPlugin\LenonLeiteManyChatBundle\Integration\LenonLeiteManyChatIntegration::class,
                'tags'  => ['mautic.integration', 'mautic.basic_integration'],
            ],
            'mautic.integration.lenonleitemanychat.configuration' => [
                'class' => MauticPlugin\LenonLeiteManyChatBundle\Integration\Support\ConfigSupport::class,
                'tags'  => ['mautic.config_integration'],
            ],
        ],
    ],
    'parameters' => [
        'manychat_enabled'              => false,
        'manychat_api_key'              => '',
        'manychat_webhook_secret'       => '',
        'manychat_contact_lookup_field' => 'email',
        'manychat_tag_prefix'           => 'manychat:',
        'manychat_sync_direction'       => 'manychat_to_mautic',
        'manychat_field_update_mode'    => 'overwrite',
    ],
];
