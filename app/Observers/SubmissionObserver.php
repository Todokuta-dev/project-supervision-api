<?php

namespace App\Observers;

use App\Models\Submission;

class SubmissionObserver
{
    use LogsActivity;

    public function created(Submission $submission): void
    {
        $this->log('Created submission', 'Submission', $submission->submission_id);
    }
}