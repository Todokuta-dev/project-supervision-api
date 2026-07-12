<?php

namespace App\Observers;

use App\Models\Approval;

class ApprovalObserver
{
    use LogsActivity;

    public function created(Approval $approval): void
    {
        $this->log('Created approval: ' . $approval->status, 'Approval', $approval->approval_id);
    }

    public function updated(Approval $approval): void
    {
        $this->log('Updated approval: ' . $approval->status, 'Approval', $approval->approval_id);
    }
}