<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $signature = $request->header('X-Paystack-Signature');
        $secret = config('services.paystack.secret_key');

        if (! $signature || ! hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature)) {
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;

        if ($event === 'charge.success') {
            $this->handleChargeSuccess($payload['data'] ?? []);
        }

        return response('OK', 200);
    }

    private function handleChargeSuccess(array $data): void
    {
        $metadata = $data['metadata'] ?? [];
        $workspaceId = $metadata['workspace_id'] ?? null;
        $plan = $metadata['plan'] ?? null;

        if (! $workspaceId || ! $plan || ! in_array($plan, ['PRO', 'AGENCY'], true)) {
            Log::warning('Paystack charge.success: missing or invalid workspace_id/plan in metadata', $metadata);

            return;
        }

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $workspaceId)) {
            Log::warning('Paystack charge.success: invalid workspace_id format', ['workspace_id' => $workspaceId]);

            return;
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            Log::warning('Paystack charge.success: workspace not found', ['workspace_id' => $workspaceId]);

            return;
        }

        $workspace->update(['plan' => $plan]);

        Log::info('Workspace plan upgraded via Paystack', [
            'workspace_id' => $workspaceId,
            'plan' => $plan,
        ]);
    }
}
