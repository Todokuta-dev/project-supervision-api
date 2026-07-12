<?php

namespace App\Observers;

use App\Models\Project;

class ProjectObserver
{
    use LogsActivity;

    public function created(Project $project): void
    {
        $this->log('Created project', 'Project', $project->project_id);
    }

    public function updated(Project $project): void
    {
        $this->log('Updated project', 'Project', $project->project_id);
    }
}