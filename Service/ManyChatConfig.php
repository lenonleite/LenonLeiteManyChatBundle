<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;

class ManyChatConfig
{
    public function __construct(
        private CoreParametersHelper $coreParametersHelper
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->coreParametersHelper->get('manychat_enabled');
    }

    public function getContactLookupField(): string
    {
        $field = (string) $this->coreParametersHelper->get('manychat_contact_lookup_field');

        return in_array($field, ['email', 'phone'], true) ? $field : 'email';
    }

    public function getTagPrefix(): string
    {
        return trim((string) $this->coreParametersHelper->get('manychat_tag_prefix'));
    }

    public function getWebhookSecret(): string
    {
        return trim((string) $this->coreParametersHelper->get('manychat_webhook_secret'));
    }

    public function getFieldUpdateMode(): string
    {
        $mode = (string) $this->coreParametersHelper->get('manychat_field_update_mode');

        return in_array($mode, ['overwrite', 'fill_empty'], true) ? $mode : 'overwrite';
    }

    public function shouldOnlyFillEmptyValues(): bool
    {
        return 'fill_empty' === $this->getFieldUpdateMode();
    }

    public function isValidWebhookSecret(?string $providedSecret): bool
    {
        $expected = $this->getWebhookSecret();
        if ('' === $expected) {
            return false;
        }

        return is_string($providedSecret) && hash_equals($expected, trim($providedSecret));
    }
}
