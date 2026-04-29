<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Service;

use Psr\Log\LoggerInterface;

class ManyChatSyncLogger
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info('[ManyChat] '.$message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning('[ManyChat] '.$message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error('[ManyChat] '.$message, $context);
    }
}
