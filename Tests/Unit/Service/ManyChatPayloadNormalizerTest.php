<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Tests\Unit\Service;

use MauticPlugin\LenonLeiteManyChatBundle\Service\ManyChatPayloadNormalizer;
use PHPUnit\Framework\TestCase;

class ManyChatPayloadNormalizerTest extends TestCase
{
    public function testNormalizesCoreFieldsTagsAndCustomFields(): void
    {
        $normalizer = new ManyChatPayloadNormalizer();

        $normalized = $normalizer->normalize([
            'subscriber_id' => 'mc_123',
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'email'         => 'john@example.com',
            'phone'         => '+1 (555) 123-9999',
            'channel'       => 'instagram',
            'username'      => 'johnny',
            'tags'          => ['lead', 'vip', 'lead'],
            'custom_fields' => [
                'Campaign Name' => 'Spring Launch',
                'entry_point'   => 'Instagram DM'
            ]
        ]);

        self::assertSame('mc_123', $normalized['manychat_subscriber_id']);
        self::assertSame('John', $normalized['firstname']);
        self::assertSame('Doe', $normalized['lastname']);
        self::assertSame('john@example.com', $normalized['email']);
        self::assertSame('+15551239999', $normalized['phone']);
        self::assertSame('instagram', $normalized['manychat_channel']);
        self::assertSame('johnny', $normalized['manychat_username']);
        self::assertSame(['lead', 'vip'], $normalized['tags']);
        self::assertSame('Spring Launch', $normalized['manychat_campaign_name']);
        self::assertSame('Instagram DM', $normalized['manychat_entry_point']);
        self::assertArrayHasKey('manychat_last_sync_at', $normalized);
    }

    public function testFallsBackToSubscriberPayloadAndUsernameForFirstname(): void
    {
        $normalizer = new ManyChatPayloadNormalizer();

        $normalized = $normalizer->normalize([
            'subscriber' => [
                'user_id'   => 'sub_42',
                'email'     => 'fallback@example.com',
                'name'      => 'Fallback User',
                'platform'  => 'facebook',
                'tags'      => [['name' => 'warm']]
            ]
        ]);

        self::assertSame('sub_42', $normalized['manychat_subscriber_id']);
        self::assertSame('fallback@example.com', $normalized['email']);
        self::assertSame('Fallback User', $normalized['firstname']);
        self::assertSame('Fallback User', $normalized['manychat_username']);
        self::assertSame('facebook', $normalized['manychat_channel']);
        self::assertSame(['warm'], $normalized['tags']);
    }

    public function testRequiresEmailOrPhone(): void
    {
        $normalizer = new ManyChatPayloadNormalizer();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one identifier is required: email or phone.');

        $normalizer->normalize([
            'subscriber_id' => 'mc_missing',
            'first_name'    => 'No Identifier'
        ]);
    }
}
