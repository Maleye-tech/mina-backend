<?php

namespace Modules\WellBeing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\WellBeing\Database\Factories\AssessmentQuestionFactory;

/**
 * Question d'un test d'auto-évaluation.
 *
 * @property array<int, array{key: string, label: string, points: int}> $options
 */
class AssessmentQuestion extends Model
{
    use HasFactory;

    protected $table = 'questions';

    protected $fillable = [
        'test_id',
        'question_text',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(AssessmentTest::class, 'test_id');
    }

    protected static function newFactory(): AssessmentQuestionFactory
    {
        return AssessmentQuestionFactory::new();
    }
}
