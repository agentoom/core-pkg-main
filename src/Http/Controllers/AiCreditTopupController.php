<?php

namespace Agentoom\Core\Http\Controllers;

use Agentoom\Core\Models\AiCreditTopup;
use Agentoom\Core\Services\AiCreditService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiCreditTopupController extends Controller
{
    public function callback(Request $request)
    {
        $data = $request->validate([
            'purchase_uuid' => 'required|string',
            'token' => 'required|string',
            'domain' => 'required|string',
            'user_email' => 'required|string',
            'amount' => 'required|numeric',
            'status' => 'required|string',
            'timestamp' => 'required|integer',
            'signature' => 'required|string',
        ]);

        $secret = config('services.agentoom.internal_secret');

        // 1. Signature validation
        $signingString = implode('|', [
            $data['purchase_uuid'],
            $data['token'],
            $data['domain'],
            $data['user_email'],
            $data['amount'],
            $data['status'],
            $data['timestamp'],
        ]);

        $expectedSignature = hash_hmac('sha256', $signingString, $secret);

        if (! hash_equals($expectedSignature, $data['signature'])) {
            Log::error('AiCreditTopup Callback: signature failure', [
                'purchase_uuid' => $data['purchase_uuid'],
                'received' => $data['signature'],
                'expected' => $expectedSignature,
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        Log::info('AiCreditTopup Callback: callback received', ['purchase_uuid' => $data['purchase_uuid'], 'status' => $data['status']]);

        // 2. Timestamp validation (5 minutes)
        if (abs(time() - $data['timestamp']) > 300) {
            Log::error('AiCreditTopup Callback: validation failures (timestamp expired)', ['timestamp' => $data['timestamp'], 'now' => time()]);

            return response()->json(['error' => 'Timestamp expired'], 403);
        }

        // 3. Find purchase
        $topup = AiCreditTopup::where('uuid', $data['purchase_uuid'])->first();

        if (! $topup) {
            Log::error('AiCreditTopup Callback: validation failures (purchase not found)', ['purchase_uuid' => $data['purchase_uuid']]);

            return response()->json(['error' => 'Purchase not found'], 404);
        }

        // 4. Validate topup details
        if ($topup->token !== $data['token'] ||
            $topup->user_email !== $data['user_email'] ||
            (float) $topup->amount !== (float) $data['amount'] ||
            $data['domain'] !== request()->getHost()
        ) {
            Log::error('AiCreditTopup Callback: validation failures (data mismatch)', [
                'topup' => $topup->toArray(),
                'received' => $data,
            ]);

            return response()->json(['error' => 'Data mismatch'], 403);
        }

        // 5. Idempotency & Status check
        if ($topup->status === AiCreditTopup::STATUS_COMPLETED) {
            Log::info('AiCreditTopup Callback: duplicate callback attempts', ['purchase_uuid' => $topup->uuid]);

            return response()->json(['success' => true]);
        }

        if ($topup->status !== AiCreditTopup::STATUS_AWAITING_PAYMENT) {
            Log::error('AiCreditTopup Callback: validation failures (invalid status transition)', ['current_status' => $topup->status]);

            return response()->json(['error' => 'Invalid status transition'], 403);
        }

        // 6. Process payment
        if ($data['status'] === 'payment_received') {
            try {
                app(AiCreditService::class)->fulfillTopup($topup);

                Log::info('AiCreditTopup Callback: successful topups', ['purchase_uuid' => $topup->uuid]);

                return response()->json(['success' => true]);
            } catch (\Exception $e) {
                Log::error('AiCreditTopup Callback: Fulfillment error', ['error' => $e->getMessage()]);

                return response()->json(['error' => 'Fulfillment error'], 500);
            }
        }

        // Handle other statuses if needed (e.g. failed)
        if (in_array($data['status'], ['failed', 'expired'])) {
            $topup->update(['status' => $data['status']]);

            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Unhandled status'], 400);
    }
}
