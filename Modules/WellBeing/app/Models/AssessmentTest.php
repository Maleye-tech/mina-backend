<?php

namespace Modules\WellBeing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\WellBeing\Database\Factories\AssessmentTestFactory;
use Modules\WellBeing\Enums\AssessmentTestType;

/**
 * Test d'auto-évaluation (anxiété, stress ou bien-être).
 */
class AssessmentTest extends Model
{
    use HasFactory;

    protected $table = 'tests';

    protected $fillable = [
        'title',
        'description',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssessmentTestType::class,
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class, 'test_id')->orderBy('id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(UserTestResult::class, 'test_id');
    }

    protected static function newFactory(): AssessmentTestFactory
    {
        return AssessmentTestFactory::new();
    }
}
