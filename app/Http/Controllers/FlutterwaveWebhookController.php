<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FlutterwaveWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secretHash = config('services.flutterwave.secret_hash');
        $signature = $request->header('verif-hash');

        if (! $signature || ! hash_equals((string) $secretHash, $signature)) {
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;

        if ($event === 'charge.completed') {
            $this->handleChargeCompleted($payload['data'] ?? []);
        }

        return response('OK', 200);
    }

    private function handleChargeCompleted(array $data): void
    {
        if (($data['status'] ?? '') !== 'successful') {
            return;
        }

        $meta = $data['meta'] ?? [];
        $workspaceId = $meta['workspace_id'] ?? null;
        $plan = $meta['plan'] ?? null;

        if (! $workspaceId || ! $plan || ! in_array($plan, ['PRO', 'AGENCY'], true)) {
            Log::warning('Flutterwave charge.completed: missing or invalid workspace_id/plan in meta', $meta);

            return;
        }

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $workspaceId)) {
            Log::warning('Flutterwave charge.completed: invalid workspace_id format', ['workspace_id' => $workspaceId]);

            return;
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            Log::warning('Flutterwave charge.completed: workspace not found', ['workspace_id' => $workspaceId]);

            return;
        }

        $workspace->update(['plan' => $plan]);

        Log::info('Workspace plan upgraded via Flutterwave', [
            'workspace_id' => $workspaceId,
            'plan' => $plan,
        ]);
    }
}
