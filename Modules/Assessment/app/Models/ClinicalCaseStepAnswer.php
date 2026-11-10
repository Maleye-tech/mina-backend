<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Assessment\Database\Factories\ClinicalCaseStepAnswerFactory;

class ClinicalCaseStepAnswer extends Model
{
    use HasFactory;

    protected $table = 'assessment_clinical_case_step_answers';

    protected $fillable = [
        'clinical_case_attempt_id',
        'clinical_case_step_id',
        'answer',
    ];

    public function clinicalCaseAttempt(): BelongsTo
    {
        return $this->belongsTo(ClinicalCaseAttempt::class);
    }

    public function clinicalCaseStep(): BelongsTo
    {
        return $this->belongsTo(ClinicalCaseStep::class);
    }

    protected static function newFactory(): ClinicalCaseStepAnswerFactory
    {
        return ClinicalCaseStepAnswerFactory::new();
    }
}
