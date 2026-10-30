<?php

namespace App\Http\Controllers;

use App\Models\OutboxNotification;
use App\Models\Permit;
use App\Support\StructuredLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The human side of exception handling: what needs a person, and the Requeue action. */
class AdminExceptionController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = OutboxNotification::with('permit:id,reference')
            ->where('status', OutboxNotification::MANUAL)
            ->latest('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (OutboxNotification $n) => [
                'id' => $n->id,
                'permit' => $n->permit->reference,
                'kind' => $n->kind,
                'attempts' => $n->attempts,
                'failure' => $n->last_error_class,
                'detail' => $n->last_error_detail,
                'since' => $n->updated_at->toIso8601String(),
            ]);

        $permits = Permit::where('needs_manual_action', true)
            ->latest('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Permit $p) => [
                'reference' => $p->reference,
                'status' => $p->status->value,
                'reason' => $p->manual_action_reason,
                'since' => $p->updated_at->toIso8601String(),
            ]);

        return response()->json(['data' => ['notifications' => $notifications, 'permits' => $permits]]);
    }

    public function requeue(Request $request, OutboxNotification $notification): JsonResponse
    {
        $requeued = OutboxNotification::whereKey($notification->id)
            ->where('status', OutboxNotification::MANUAL)
            ->update(['status' => OutboxNotification::PENDING, 'attempts' => 0, 'next_attempt_at' => now(), 'updated_at' => now()]);

        abort_if($requeued !== 1, 409);

        StructuredLog::event('notification.requeued_by_admin', [
            'notification_id' => $notification->id,
            'admin_id' => $request->user()->id,
        ]);

        return response()->json(['data' => ['id' => $notification->id, 'status' => OutboxNotification::PENDING]]);
    }
}
