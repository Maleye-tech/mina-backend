<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Assessment\Database\Factories\QuestionOptionFactory;

class QuestionOption extends Model
{
    use HasFactory;

    protected $table = 'assessment_question_options';

    protected $fillable = [
        'question_id',
        'label',
        'is_correct',
        'sort_order',
    ];

    protected $attributes = [
        'is_correct' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    protected static function newFactory(): QuestionOptionFactory
    {
        return QuestionOptionFactory::new();
    }
}
