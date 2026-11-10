<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'city',
        'type',
        'website_url',
        'is_partner',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_partner' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function faculties(): HasMany
    {
        return $this->hasMany(Faculty::class)->orderBy('name');
    }
}
