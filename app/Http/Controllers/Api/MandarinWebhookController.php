<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MandarinGatewayException;
use App\Http\Controllers\Controller;
use App\Services\Mandarin\MandarinWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MandarinWebhookController extends Controller
{
    public function __construct(
        protected MandarinWebhookService $webhookService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $payload = $request->all();

        $signature = $request->header('X-Mandarin-Signature') ?? $request->header('X-Signature');
        $timestamp = $request->header('X-Mandarin-Timestamp') ?? $request->header('X-Timestamp');

        try {
            $result = $this->webhookService->handleWebhook(
                payload: $payload,
                rawPayload: $rawPayload,
                signature: $signature,
                timestamp: $timestamp
            );

            return response()->json($result);
        } catch (MandarinGatewayException $e) {
            Log::warning('Mandarin webhook rejected: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Log::error('Mandarin webhook processing error: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal webhook error',
            ], 500);
        }
    }
}
