<?php

namespace App\Observers;

use App\Models\ActivityLog;

trait LogsActivity
{
    protected function log(string $action, string $entityType, string $entityId): void
    {
        if (! auth()->check()) {
            return; // e.g. seeders/system actions with no logged-in user
        }

        ActivityLog::create([
            'actor_id' => auth()->user()->user_id,
            'actor_name' => auth()->user()->full_name,
            'actor_role' => auth()->user()->role,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'timestamp' => now(),
        ]);
    }
}