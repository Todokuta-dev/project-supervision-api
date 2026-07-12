<?php

namespace App\Providers;

use App\Models\Approval;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\Submission;
use App\Observers\ApprovalObserver;
use App\Observers\FeedbackObserver;
use App\Observers\ProjectObserver;
use App\Observers\SubmissionObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Project::observe(ProjectObserver::class);
        Submission::observe(SubmissionObserver::class);
        Feedback::observe(FeedbackObserver::class);
        Approval::observe(ApprovalObserver::class);
    }
}