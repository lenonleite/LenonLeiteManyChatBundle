<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Service;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;

class MauticContactUpsertService
{
    public function __construct(
        private LeadModel $leadModel,
        private ManyChatConfig $config,
        private MauticContactFieldManager $fieldManager
    ) {
    }

    /**
     * @param array<string, mixed> $normalizedPayload
     *
     * @return array<string, mixed>
     */
    public function upsertFromPayload(array $normalizedPayload): array
    {
        $lead    = $this->findExistingLead($normalizedPayload);
        $created = false;

        if (!$lead instanceof Lead) {
            $lead    = new Lead();
            $created = true;
        }

        $fieldData = $this->buildFieldData($normalizedPayload);
        $this->fieldManager->ensureFields(array_keys($fieldData));
        $this->leadModel->setFieldValues($lead, $fieldData, false, false);
        $this->leadModel->saveEntity($lead);

        $tags = $this->prefixTags($normalizedPayload['tags'] ?? []);
        if ([] !== $tags) {
            $this->leadModel->modifyTags($lead, $tags, null, true);
        }

        return [
            'contact_id'      => $lead->getId(),
            'created'         => $created,
            'updated_fields'  => array_keys($fieldData),
            'ignored_fields'  => [],
            'applied_tags'    => $tags,
            'lookup_strategy' => $this->config->getContactLookupField(),
        ];
    }

    /**
     * @param array<string, mixed> $normalizedPayload
     */
    private function findExistingLead(array $normalizedPayload): ?Lead
    {
        $repository   = $this->leadModel->getRepository();
        $lookupField  = $this->config->getContactLookupField();
        $lookupValues = [];

        if ('phone' === $lookupField && !empty($normalizedPayload['phone'])) {
            $lookupValues[] = ['phone', (string) $normalizedPayload['phone']];
        }
        if (!empty($normalizedPayload['email'])) {
            $lookupValues[] = ['email', (string) $normalizedPayload['email']];
        }
        if ('email' === $lookupField && !empty($normalizedPayload['phone'])) {
            $lookupValues[] = ['phone', (string) $normalizedPayload['phone']];
        }

        foreach ($lookupValues as [$field, $value]) {
            $contacts = 'email' === $field
                ? $repository->getContactsByEmail($value)
                : $repository->getLeadsByFieldValue($field, $value);

            $lead = !empty($contacts) ? reset($contacts) : null;
            if ($lead instanceof Lead) {
                return $lead;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $normalizedPayload
     *
     * @return array<string, mixed>
     */
    private function buildFieldData(array $normalizedPayload): array
    {
        $fieldData = [];
        foreach ($normalizedPayload as $alias => $value) {
            if ('tags' === $alias || null === $value || '' === $value) {
                continue;
            }

            $fieldData[$alias] = $value;
        }

        return $fieldData;
    }

    /**
     * @param mixed $tags
     *
     * @return string[]
     */
    private function prefixTags(mixed $tags): array
    {
        if (!is_array($tags)) {
            return [];
        }

        $prefix = $this->config->getTagPrefix();
        $result = [];

        foreach ($tags as $tag) {
            if (!is_string($tag) || '' === trim($tag)) {
                continue;
            }

            $cleanTag  = trim($tag);
            $result[] = '' !== $prefix && !str_starts_with($cleanTag, $prefix)
                ? $prefix.$cleanTag
                : $cleanTag;
        }

        return array_values(array_unique($result));
    }
}
