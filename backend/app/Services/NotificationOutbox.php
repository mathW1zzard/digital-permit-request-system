<?php

namespace App\Services;

use App\Models\OutboxNotification;
use App\Models\Permit;
use App\Models\User;
use App\Support\StructuredLog;
use Illuminate\Database\UniqueConstraintViolationException;

final class NotificationOutbox
{
    /**
     * Queue a notification exactly once per dedupe key.
     * Returns true when a new row was created, false when it already existed.
     */
    public function enqueue(Permit $permit, User $recipient, string $kind, string $dedupeKey): bool
    {
        try {
            $row = OutboxNotification::firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                [
                    'permit_id' => $permit->id,
                    'recipient_id' => $recipient->id,
                    'kind' => $kind,
                    'status' => OutboxNotification::PENDING,
                    'attempts' => 0,
                    'next_attempt_at' => now(),
                    'correlation_id' => StructuredLog::correlationId(),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            return false; // another process queued the same key a moment earlier
        }

        if ($row->wasRecentlyCreated) {
            StructuredLog::event('notification.queued', [
                'notification_id' => $row->id,
                'permit' => $permit->reference,
                'kind' => $kind,
            ]);
        }

        return $row->wasRecentlyCreated;
    }
}
