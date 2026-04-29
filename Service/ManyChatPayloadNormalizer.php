<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Service;

final class ManyChatPayloadNormalizer
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        $subscriber = $this->readArray($payload, ['subscriber', 'contact', 'user']);
        $custom     = $this->normalizeCustomFields($payload['custom_fields'] ?? $subscriber['custom_fields'] ?? []);
        $tags       = $this->normalizeTags($payload['tags'] ?? $subscriber['tags'] ?? []);
        $channel    = $this->readString($payload, ['channel', 'platform', 'source'], $this->readString($subscriber, ['channel', 'platform', 'source']));

        $normalized = [
            'email'                  => $this->readString($payload, ['email'], $this->readString($subscriber, ['email'])),
            'firstname'              => $this->readString($payload, ['first_name', 'firstname'], $this->readString($subscriber, ['first_name', 'firstname', 'name'])),
            'lastname'               => $this->readString($payload, ['last_name', 'lastname'], $this->readString($subscriber, ['last_name', 'lastname'])),
            'phone'                  => $this->normalizePhone($this->readString($payload, ['phone', 'whatsapp_phone'], $this->readString($subscriber, ['phone', 'whatsapp_phone']))),
            'tags'                   => $tags,
            'manychat_channel'       => $channel,
            'manychat_subscriber_id' => $this->readString($payload, ['subscriber_id', 'contact_id', 'user_id', 'id'], $this->readString($subscriber, ['subscriber_id', 'contact_id', 'user_id', 'id'])),
            'manychat_username'      => $this->readString($payload, ['username', 'user_name'], $this->readString($subscriber, ['username', 'user_name', 'name'])),
            'manychat_last_sync_at'  => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        foreach ($custom as $alias => $value) {
            $normalized[$alias] = $value;
        }

        if ('' === $normalized['firstname'] && '' !== $normalized['manychat_username']) {
            $normalized['firstname'] = $normalized['manychat_username'];
        }

        if ('' === $normalized['email'] && '' === $normalized['phone']) {
            throw new \InvalidArgumentException('At least one identifier is required: email or phone.');
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[]             $keys
     */
    private function readString(array $payload, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[]             $keys
     *
     * @return array<string, mixed>
     */
    private function readArray(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function normalizeTags(mixed $tags): array
    {
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        if (!is_array($tags)) {
            return [];
        }

        $normalized = [];
        foreach ($tags as $tag) {
            if (is_array($tag)) {
                $tag = $tag['name'] ?? $tag['title'] ?? null;
            }

            if (!is_scalar($tag)) {
                continue;
            }

            $value = trim((string) $tag);
            if ('' !== $value) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, string>
     */
    private function normalizeCustomFields(mixed $customFields): array
    {
        if (!is_array($customFields)) {
            return [];
        }

        $normalized = [];
        foreach ($customFields as $key => $value) {
            $alias      = is_string($key) ? $this->normalizeCustomFieldAlias($key) : '';
            $fieldValue = $value;

            if (is_array($value)) {
                $alias      = $alias ?: $this->normalizeCustomFieldAlias((string) ($value['name'] ?? $value['field_name'] ?? $value['key'] ?? ''));
                $fieldValue = $value['value'] ?? $value['field_value'] ?? $value['text'] ?? null;
            }

            if ('' === $alias || null === $fieldValue || is_object($fieldValue)) {
                continue;
            }

            if (is_array($fieldValue)) {
                $fieldValue = implode(', ', array_map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $fieldValue));
            }

            $fieldValue = trim((string) $fieldValue);
            if ('' !== $fieldValue) {
                $normalized[$alias] = $fieldValue;
            }
        }

        return $normalized;
    }

    private function normalizeCustomFieldAlias(string $fieldName): string
    {
        $fieldName = strtolower(trim($fieldName));
        $fieldName = preg_replace('/[^a-z0-9]+/', '_', $fieldName) ?? '';
        $fieldName = trim($fieldName, '_');

        if ('' === $fieldName) {
            return '';
        }

        return 'manychat_'.$fieldName;
    }

    private function normalizePhone(string $phone): string
    {
        if ('' === $phone) {
            return '';
        }

        $normalized = preg_replace('/[^\d+]/', '', $phone) ?? '';

        return trim($normalized);
    }
}
