<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Controller;

use MauticPlugin\LenonLeiteManyChatBundle\Service\ManyChatConfig;
use MauticPlugin\LenonLeiteManyChatBundle\Service\ManyChatPayloadNormalizer;
use MauticPlugin\LenonLeiteManyChatBundle\Service\ManyChatSyncLogger;
use MauticPlugin\LenonLeiteManyChatBundle\Service\MauticContactUpsertService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookController
{
    public function __construct(
        private ManyChatConfig $config,
        private ManyChatPayloadNormalizer $payloadNormalizer,
        private MauticContactUpsertService $contactUpsertService,
        private ManyChatSyncLogger $syncLogger
    ) {
    }

    public function syncAction(Request $request): JsonResponse
    {
        if (!$this->config->isEnabled()) {
            return new JsonResponse(
                ['success' => false, 'message' => 'ManyChat connector is disabled.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        if (!$request->isMethod(Request::METHOD_POST)) {
            return new JsonResponse(
                ['success' => false, 'message' => 'Only POST is allowed.'],
                Response::HTTP_METHOD_NOT_ALLOWED
            );
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        if (empty($payload)) {
            return new JsonResponse(
                ['success' => false, 'message' => 'Request payload is required.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->config->isValidWebhookSecret($request->headers->get('X-ManyChat-Secret'))) {
            $this->syncLogger->warning('ManyChat webhook rejected because the secret header did not match.', [
                'headers' => [
                    'X-ManyChat-Secret' => $request->headers->get('X-ManyChat-Secret'),
                ],
            ]);

            return new JsonResponse(
                ['success' => false, 'message' => 'Invalid webhook secret.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        try {
            $normalizedPayload = $this->payloadNormalizer->normalize($payload);
            $result            = $this->contactUpsertService->upsertFromPayload($normalizedPayload);

            $this->syncLogger->info('ManyChat contact sync completed.', [
                'subscriber_id' => $normalizedPayload['manychat_subscriber_id'] ?? null,
                'contact_id'    => $result['contact_id'],
                'created'       => $result['created'],
                'updated'       => $result['updated_fields'],
                'ignored'       => $result['ignored_fields'],
            ]);

            return new JsonResponse(
                ['success' => true] + $result,
                Response::HTTP_OK
            );
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(
                ['success' => false, 'message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Throwable $exception) {
            $this->syncLogger->error('ManyChat contact sync failed.', [
                'exception' => $exception,
                'payload'   => $payload,
            ]);

            return new JsonResponse(
                ['success' => false, 'message' => 'Unexpected sync error.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
