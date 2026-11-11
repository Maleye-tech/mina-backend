<?php

namespace Modules\WellBeing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\WellBeing\Database\Factories\WellbeingArticleFactory;
use Modules\WellBeing\Enums\WellbeingCategory;

/**
 * Fiche de prévention (contenu éducatif) du module Mina Care.
 */
class WellbeingArticle extends Model
{
    use HasFactory;

    protected $table = 'wellbeing_articles';

    protected $fillable = [
        'title',
        'slug',
        'category',
        'content',
        'is_published',
    ];

    protected $attributes = [
        'is_published' => false,
    ];

    protected function casts(): array
    {
        return [
            'category' => WellbeingCategory::class,
            'is_published' => 'boolean',
        ];
    }

    /**
     * Générer automatiquement un slug unique lorsqu'il n'est pas fourni.
     */
    protected static function booted(): void
    {
        static::saving(function (self $article): void {
            if (blank($article->slug)) {
                $article->slug = static::uniqueSlug($article->title, $article->getKey());
            }
        });
    }

    /**
     * Uniquement les fiches publiées.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Uniquement les fiches d'une thématique donnée.
     */
    public function scopeOfCategory(Builder $query, WellbeingCategory $category): Builder
    {
        return $query->where('category', $category->value);
    }

    private static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'fiche';
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn (Builder $query, int $id): Builder => $query->whereKeyNot($id))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    protected static function newFactory(): WellbeingArticleFactory
    {
        return WellbeingArticleFactory::new();
    }
}
