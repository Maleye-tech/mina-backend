<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prépare la taxonomie partagée pour la Library :
 * - ajoute la colonne `color` (couleur d'affichage des matières) à `subjects`
 * - recopie les données de `library_subjects` / `library_levels` vers les tables communes
 *   (identifiants préservés, sauts en cas de doublon d'id ou de slug).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->string('color', 20)->nullable();
        });

        $this->copy('library_subjects', 'subjects', ['name', 'slug', 'description', 'color', 'is_active', 'sort_order']);
        $this->copy('library_levels', 'levels', ['name', 'slug', 'description', 'is_active', 'sort_order']);
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropColumn('color');
        });
    }

    private function copy(string $from, string $to, array $columns): void
    {
        if (! Schema::hasTable($from)) {
            return;
        }

        $columns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($from, $column),
        ));

        $existingIds = DB::table($to)->pluck('id')->all();
        $existingSlugs = DB::table($to)->pluck('slug')->all();

        DB::table($from)
            ->whereNotIn('id', $existingIds)
            ->whereNotIn('slug', $existingSlugs)
            ->orderBy('id')
            ->get()
            ->each(function ($row) use ($to, $columns): void {
                DB::table($to)->insert([
                    'id' => $row->id,
                    ...collect($columns)->mapWithKeys(fn (string $column) => [$column => $row->{$column}])->all(),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            });
    }
};
