<?php

namespace Modules\WellBeing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\WellBeing\Database\Factories\UserTestResultFactory;

/**
 * Résultat d'un test d'auto-évaluation.
 *
 * `response_data` est chiffré au repos grâce au cast `encrypted:array` :
 * les réponses détaillées ne sont jamais lisibles directement en base.
 */
class UserTestResult extends Model
{
    use HasFactory;

    protected $table = 'user_test_results';

    protected $fillable = [
        'user_id',
        'test_id',
        'score',
        'response_data',
        'feedback_given',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'response_data' => 'encrypted:array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(AssessmentTest::class, 'test_id');
    }

    /**
     * Uniquement les résultats d'un utilisateur donné.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    protected static function newFactory(): UserTestResultFactory
    {
        return UserTestResultFactory::new();
    }
}
