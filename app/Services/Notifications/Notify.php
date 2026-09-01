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
}
