<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RepositoryItem extends Model
{
    use HasUuids;

    protected $primaryKey = 'repo_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

protected $fillable = [
    'title',
    'abstract',
    'full_text',
    'keywords',
    'department_id',
    'session_id',
    'student_id',
    'supervisor_id',
    'file_path',
    'archived_at',
];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'repo_id';
    }
}