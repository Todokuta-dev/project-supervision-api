<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasUuids;

    protected $primaryKey = 'approval_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'approved_by',
        'stage',
        'status',
        'notes',
        'approval_date',
    ];

    protected function casts(): array
    {
        return [
            'approval_date' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'approval_id';
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}