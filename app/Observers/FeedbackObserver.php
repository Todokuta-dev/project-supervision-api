<?php

namespace App\Observers;

use App\Models\Feedback;

class FeedbackObserver
{
    use LogsActivity;

    public function created(Feedback $feedback): void
    {
        $this->log('Gave feedback', 'Feedback', $feedback->feedback_id);
    }
}