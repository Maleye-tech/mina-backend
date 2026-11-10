<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Assessment\Database\Factories\ClinicalCaseStepFactory;

class ClinicalCaseStep extends Model
{
    use HasFactory;

    protected $table = 'assessment_clinical_case_steps';

    protected $fillable = [
        'clinical_case_id',
        'prompt',
        'question',
        'expected_answer',
        'sort_order',
    ];

    protected $attributes = [
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ClinicalCaseStepAnswer::class);
    }

    protected static function newFactory(): ClinicalCaseStepFactory
    {
        return ClinicalCaseStepFactory::new();
    }
}
