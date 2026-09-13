<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates in-app notifications idempotently.
 *
 * A notification is uniquely identified by (user_id, type, related object).
 * Calling once() repeatedly for the same combination never produces duplicates,
 * so it is safe to call from model observers that may fire multiple times.
 */
class Notify
{
    public function once(User|int $user, string $type, string $message, ?Model $related = null): Notification
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return Notification::firstOrCreate(
            [
                'user_id' => $userId,
                'type' => $type,
                'related_type' => $related?->getMorphClass(),
                'related_id' => $related?->getKey(),
            ],
            [
                'message' => $message,
                'is_sent' => true,
            ],
        );
    }

    /**
     * @param  iterable<User>  $users
     */
    public function onceForEach(iterable $users, string $type, string $message, ?Model $related = null): void
    {
        foreach ($users as $user) {
            $this->once($user, $type, $message, $related);
        }
    }

    /**
     * Mark a notification "done" once its underlying task no longer needs action —
     * e.g. once a SOW is signed or a quotation is reviewed — so the bell badge
     * clears itself even if the recipient never clicked it.
     *
     * Scoped to one user when the notification only ever concerned that person
     * (e.g. the technician who signed); left unscoped when any recipient in a
     * group could have acted (e.g. any Manager reviewing a quotation), which
     * resolves it for the whole group at once.
     */
    public function resolve(string $type, Model $related, User|int|null $user = null): void
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        Notification::query()
            ->where('type', $type)
            ->where('related_type', $related->getMorphClass())
            ->where('related_id', $related->getKey())
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
