<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SimilarityReport extends Model
{
    use HasUuids;

    protected $primaryKey = 'report_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'score',
        'matched_sources',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'matched_sources' => 'array',
            'generated_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'report_id';
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class, 'submission_id', 'submission_id');
    }
}