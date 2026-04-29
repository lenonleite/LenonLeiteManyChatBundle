<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Service;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;

class MauticContactFieldManager
{
    private const DEFAULT_FIELDS = [
        'manychat_subscriber_id' => 'ManyChat Subscriber ID',
        'manychat_channel'       => 'ManyChat Channel',
        'manychat_username'      => 'ManyChat Username',
        'manychat_last_sync_at'  => 'ManyChat Last Sync At',
    ];

    public function __construct(
        private FieldModel $fieldModel
    ) {
    }

    /**
     * @param string[] $aliases
     */
    public function ensureFields(array $aliases): void
    {
        $knownFields = $this->fieldModel->getLeadFields();
        foreach (array_unique($aliases) as $alias) {
            if (isset($knownFields[$alias])) {
                continue;
            }

            $field = new LeadField();
            $field->setName(self::DEFAULT_FIELDS[$alias] ?? $this->humanizeAlias($alias))
                ->setAlias($alias)
                ->setType('string')
                ->setObject('lead')
                ->setGroup('social');

            $this->fieldModel->saveEntity($field);
            $knownFields[$alias] = $field;
        }
    }

    private function humanizeAlias(string $alias): string
    {
        return ucwords(str_replace('_', ' ', $alias));
    }
}
