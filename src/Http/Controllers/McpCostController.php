<?php

namespace Agentoom\Core\Http\Controllers;

use Agentoom\Core\Models\AiUsageLog;
use Agentoom\Core\Services\AiCreditService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpCostController extends Controller
{
    public function store(Request $request, AiCreditService $creditService): JsonResponse
    {
        $validated = $request->validate([
            'service' => ['required', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'in:USD'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ]);

        $actionType = 'mcp_'.$validated['service'];
        $existing = AiUsageLog::query()
            ->where('action_type', $actionType)
            ->where('idempotency_key', $validated['idempotency_key'])
            ->first();
        if ($existing) {
            return response()->json(['message' => 'Cost already logged.', 'id' => $existing->id], 200);
        }

        $log = $creditService->logGenericUsage(
            user: null,
            actionType: $actionType,
            cost: (float) $validated['cost'],
            idempotencyKey: $validated['idempotency_key'],
            currency: $validated['currency'],
            provider: $validated['service'],
        );

        return response()->json(['message' => 'Cost logged successfully.', 'id' => $log->id], 201);
    }
}
