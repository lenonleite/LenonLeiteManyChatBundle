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

        if (!$created && $this->config->shouldOnlyFillEmptyValues()) {
            $this->hydrateExistingLeadFields($lead);
        }

        [$fieldData, $ignoredFields] = $this->buildFieldData($normalizedPayload, $lead, $created);
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
            'ignored_fields'  => $ignoredFields,
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
     * @return array{0: array<string, mixed>, 1: string[]}
     */
    private function buildFieldData(array $normalizedPayload, Lead $lead, bool $created): array
    {
        $fieldData     = [];
        $ignoredFields = [];
        foreach ($normalizedPayload as $alias => $value) {
            if ('tags' === $alias || null === $value || '' === $value) {
                continue;
            }

            if (!$created && $this->config->shouldOnlyFillEmptyValues() && $this->hasMeaningfulValue($lead->getFieldValue($alias))) {
                $ignoredFields[] = $alias;

                continue;
            }

            $fieldData[$alias] = $value;
        }

        return [$fieldData, $ignoredFields];
    }

    /**
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
            $result[]  = '' !== $prefix && !str_starts_with($cleanTag, $prefix)
                ? $prefix.$cleanTag
                : $cleanTag;
        }

        return array_values(array_unique($result));
    }

    private function hydrateExistingLeadFields(Lead $lead): void
    {
        if (!$lead->getId()) {
            return;
        }

        $lead->setFields($this->leadModel->getRepository()->getFieldValues($lead->getId()));
    }

    private function hasMeaningfulValue(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }

        if (is_string($value)) {
            return '' !== trim($value);
        }

        if (is_array($value)) {
            return [] !== $value;
        }

        return true;
    }
}
