<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Tests\Traits;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;

trait HelperEntitiesTrait
{
    private function createLead(string $name, string $email, ?string $phone = null): Lead
    {
        $lead = new Lead();
        $lead->setFirstname($name);
        $lead->setLastname($name.' lastname');
        $lead->setEmail($email);
        if (null !== $phone) {
            $lead->setPhone($phone);
        }

        $this->em->persist($lead);
        $this->flushWithRetry();

        return $lead;
    }

    private function addField(string $type, string $alias, string $name): LeadField
    {
        $field = new LeadField();
        $field->setType($type);
        $field->setObject('lead');
        $field->setAlias($alias);
        $field->setName($name);
        $field->setGroup('core');

        /** @var FieldModel $fieldModel */
        $fieldModel = static::getContainer()->get('mautic.lead.model.field');
        $fieldModel->saveEntity($field);

        return $field;
    }

    private function flushWithRetry(): void
    {
        $attempts  = 0;
        $lastError = null;

        while ($attempts < 3) {
            try {
                $this->em->flush();

                return;
            } catch (\Doctrine\DBAL\Exception $exception) {
                $lastError = $exception;
                if (1205 !== (int) $exception->getCode()) {
                    throw $exception;
                }
                usleep(200000);
                ++$attempts;
            }
        }

        if ($lastError instanceof \Doctrine\DBAL\Exception) {
            throw $lastError;
        }
    }
}
