<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Tests\Traits;

use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;

trait ActivePluginTrait
{
    private function activePlugin(bool $isPublished = true): void
    {
        $this->installPlugin('LenonLeiteManyChatBundle', $isPublished);
    }

    private function installPlugin(string $nameBundle, bool $isPublished = true): void
    {
        if (!$this->em->isOpen()) {
            /** @phpstan-ignore-next-line */
            $this->em = $this->em->create($this->em->getConnection(), $this->em->getConfiguration());
        }

        $nameIntegration = str_replace('Bundle', '', $nameBundle);
        $integration     = $this->em->getRepository(Integration::class)->findOneBy(['name' => $nameIntegration]);

        if (empty($integration)) {
            $plugin = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle]);

            if (empty($plugin)) {
                $plugin = new Plugin();
                $plugin->setName($nameIntegration);
                $plugin->setBundle($nameBundle);
                $plugin->setDescription('ManyChat Connector');
                $plugin->setVersion('0.1.0');
                $plugin->setAuthor('Lenon Leite');
                $this->em->persist($plugin);
                $this->flushWithRetryForPlugin();
            }

            if (null !== $plugin && !$this->em->contains($plugin)) {
                $plugin = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle]);
            }

            $integration = new Integration();
            $integration->setName($nameIntegration);
            $integration->setPlugin($plugin);
        }

        $integration->setIsPublished($isPublished);
        $this->em->persist($integration);
        $this->flushWithRetryForPlugin();
    }

    private function flushWithRetryForPlugin(): void
    {
        $attempts  = 0;
        $lastError = null;

        while ($attempts < 3) {
            try {
                if (!$this->em->isOpen()) {
                    /** @phpstan-ignore-next-line */
                    $this->em = $this->em->create($this->em->getConnection(), $this->em->getConfiguration());
                }
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
