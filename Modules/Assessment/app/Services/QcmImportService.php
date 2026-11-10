<?php

namespace Modules\Assessment\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Enums\Difficulty;
use Modules\Assessment\Enums\ImportStatus;
use Modules\Assessment\Enums\QuestionType;
use Modules\Assessment\Models\QcmImport;
use Modules\Assessment\Models\Question;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;
use RuntimeException;
use Throwable;

/**
 * Import de questions QCM depuis un fichier CSV.
 *
 * Le fichier attendu comporte un en-tête avec au minimum les colonnes
 * `subject`, `level`, `question` et `correct`, ainsi que deux à cinq colonnes
 * `option_a` à `option_e`. La colonne `correct` accepte les lettres A à E ou
 * les chiffres 1 à 5, séparés par des virgules pour une réponse multiple.
 *
 * Les lignes valides sont créées dans une transaction ; les doublons sont
 * détectés par une empreinte stable (matière, niveau, énoncé normalisé), y
 * compris parmi les questions supprimées logiquement. En mode `dry_run`, les
 * lignes sont validées et comptées sans être persistées.
 */
class QcmImportService
{
    /**
     * Colonnes obligatoires de l’en-tête CSV.
     */
    private const REQUIRED_COLUMNS = ['subject', 'level', 'question', 'correct'];

    /**
     * Colonnes d’options reconnues, dans l’ordre A à E.
     */
    private const OPTION_COLUMNS = ['option_a', 'option_b', 'option_c', 'option_d', 'option_e'];

    /**
     * Nombre maximal d’erreurs détaillées conservées pour un import.
     */
    private const MAX_ERRORS = 500;

    /**
     * Traiter un import : lire le fichier, valider chaque ligne, puis créer
     * les questions et leurs options.
     */
    public function process(QcmImport $import): QcmImport
    {
        if ($import->status === ImportStatus::Completed) {
            return $import;
        }

        $import->update([
            'status' => ImportStatus::Processing,
            'started_at' => now(),
            'finished_at' => null,
            'errors' => null,
            'total_rows' => 0,
            'imported_count' => 0,
            'duplicate_count' => 0,
            'failed_count' => 0,
        ]);

        try {
            $rows = $this->readRows($import);
        } catch (Throwable $exception) {
            $import->update([
                'status' => ImportStatus::Failed,
                'errors' => [['row' => 0, 'errors' => [$exception->getMessage()]]],
                'finished_at' => now(),
            ]);

            return $import->refresh();
        }

        $import->update(['total_rows' => count($rows)]);

        $errors = [];
        $importedCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;
        $seenFingerprints = [];

        foreach ($rows as $row) {
            $validation = $this->validateRow($row['data']);

            if ($validation['errors'] !== []) {
                $failedCount++;
                $this->recordError($errors, $row['line'], $validation['errors']);

                continue;
            }

            $subject = $this->findSubject($validation['subject']);
            $level = $this->findLevel($validation['level']);

            $missing = [];

            if ($subject === null) {
                $missing[] = 'La matière « '.$validation['subject'].' » est introuvable.';
            }

            if ($level === null) {
                $missing[] = 'Le niveau « '.$validation['level'].' » est introuvable.';
            }

            if ($missing !== []) {
                $failedCount++;
                $this->recordError($errors, $row['line'], $missing);

                continue;
            }

            $fingerprint = $this->fingerprint($subject, $level, $validation['question']);

            if (isset($seenFingerprints[$fingerprint])
                || Question::withTrashed()->where('fingerprint', $fingerprint)->exists()) {
                $duplicateCount++;

                continue;
            }

            $seenFingerprints[$fingerprint] = true;

            if ($import->dry_run) {
                $importedCount++;

                continue;
            }

            DB::transaction(function () use ($subject, $level, $validation, $fingerprint): void {
                $question = Question::create([
                    'subject_id' => $subject->id,
                    'level_id' => $level->id,
                    'question' => $validation['question'],
                    'question_type' => $validation['question_type']->value,
                    'difficulty' => $validation['difficulty']->value,
                    'status' => $validation['status']->value,
                    'explanation' => $validation['explanation'],
                    'source_reference' => $validation['source_reference'],
                    'fingerprint' => $fingerprint,
                ]);

                $sortOrder = 1;

                foreach ($validation['options'] as $column => $label) {
                    $letter = strtoupper(substr($column, -1));

                    $question->options()->create([
                        'label' => $label,
                        'is_correct' => in_array($letter, $validation['correct'], true),
                        'sort_order' => $sortOrder++,
                    ]);
                }
            });

            $importedCount++;
        }

        $import->update([
            'status' => ImportStatus::Completed,
            'imported_count' => $importedCount,
            'duplicate_count' => $duplicateCount,
            'failed_count' => $failedCount,
            'errors' => $errors === [] ? null : $errors,
            'finished_at' => now(),
        ]);

        return $import->refresh();
    }

    /**
     * Lire le fichier CSV et renvoyer les lignes non vides avec leur numéro.
     *
     * @return array<int, array{line: int, data: array<string, string|null>}>
     */
    private function readRows(QcmImport $import): array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($import->file_path)) {
            throw new RuntimeException('Le fichier d’import est introuvable.');
        }

        $handle = fopen($disk->path($import->file_path), 'rb');

        if ($handle === false) {
            throw new RuntimeException('Le fichier d’import ne peut pas être lu.');
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');

            if ($header === false) {
                throw new RuntimeException('Le fichier d’import est vide.');
            }

            $header = $this->normalizeHeader($header);

            if (count(array_unique($header)) !== count($header)) {
                throw new RuntimeException('L’en-tête du fichier contient des colonnes en double.');
            }

            $missingColumns = array_diff(self::REQUIRED_COLUMNS, $header);

            if ($missingColumns !== []) {
                throw new RuntimeException(
                    'Colonnes obligatoires manquantes : '.implode(', ', $missingColumns).'.'
                );
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $values = array_pad($values, count($header), null);

                /** @var array<string, string|null> $data */
                $data = array_combine($header, array_slice($values, 0, count($header)));

                $rows[] = ['line' => $line, 'data' => $data];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Normaliser l’en-tête : retirer le BOM, passer en minuscules et remplacer
     * les séparateurs par des underscores.
     *
     * @param  array<int, string>  $header
     * @return array<int, string>
     */
    private function normalizeHeader(array $header): array
    {
        return array_map(function (string $column): string {
            $column = preg_replace('/^\xEF\xBB\xBF/', '', $column) ?? $column;
            $column = mb_strtolower(trim($column));

            return preg_replace('/[^a-z0-9]+/', '_', $column) ?? $column;
        }, $header);
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Valider une ligne et normaliser ses valeurs.
     *
     * @param  array<string, string|null>  $data
     * @return array{
     *     errors: array<int, string>,
     *     subject: string,
     *     level: string,
     *     question: string,
     *     options: array<string, string>,
     *     correct: array<int, string>,
     *     difficulty: Difficulty,
     *     status: ContentStatus,
     *     question_type: QuestionType,
     *     explanation: string|null,
     *     source_reference: string|null
     * }
     */
    private function validateRow(array $data): array
    {
        $errors = [];

        $subject = trim((string) ($data['subject'] ?? ''));
        $level = trim((string) ($data['level'] ?? ''));
        $question = trim((string) ($data['question'] ?? ''));

        if ($subject === '') {
            $errors[] = 'La matière est obligatoire.';
        }

        if ($level === '') {
            $errors[] = 'Le niveau est obligatoire.';
        }

        if ($question === '') {
            $errors[] = 'L’énoncé de la question est obligatoire.';
        } elseif (mb_strlen($question) > 5000) {
            $errors[] = 'L’énoncé de la question est trop long (5 000 caractères maximum).';
        }

        $options = [];

        foreach (self::OPTION_COLUMNS as $column) {
            $label = trim((string) ($data[$column] ?? ''));

            if ($label !== '') {
                $options[$column] = $label;
            }
        }

        if (count($options) < 2) {
            $errors[] = 'Au moins deux options sont obligatoires.';
        }

        $correct = $this->parseCorrectLetters((string) ($data['correct'] ?? ''));

        if ($correct === []) {
            $errors[] = 'La colonne correct doit indiquer au moins une bonne réponse (A à E ou 1 à 5).';
        } else {
            $availableLetters = array_map(
                fn (string $column): string => strtoupper(substr($column, -1)),
                array_keys($options),
            );

            foreach ($correct as $letter) {
                if (! in_array($letter, $availableLetters, true)) {
                    $errors[] = 'La bonne réponse '.$letter.' ne correspond à aucune option renseignée.';
                }
            }
        }

        $difficultyValue = mb_strtolower(trim((string) ($data['difficulty'] ?? '')));
        $difficulty = $difficultyValue === '' ? Difficulty::Medium : Difficulty::tryFrom($difficultyValue);

        if ($difficulty === null) {
            $errors[] = 'La difficulté doit être easy, medium ou hard.';
        }

        $statusValue = mb_strtolower(trim((string) ($data['status'] ?? '')));
        $status = $statusValue === '' ? ContentStatus::Draft : ContentStatus::tryFrom($statusValue);

        if ($status === null) {
            $errors[] = 'Le statut doit être draft ou published.';
        }

        $typeValue = mb_strtolower(trim((string) ($data['question_type'] ?? '')));
        $questionType = $typeValue === ''
            ? (count($correct) > 1 ? QuestionType::Multiple : QuestionType::Single)
            : QuestionType::tryFrom($typeValue);

        if ($questionType === null) {
            $errors[] = 'Le type de question doit être single ou multiple.';
        } elseif ($questionType === QuestionType::Single && count($correct) > 1) {
            $errors[] = 'Une question à réponse unique ne peut avoir qu’une bonne réponse.';
        }

        $explanation = trim((string) ($data['explanation'] ?? ''));
        $sourceReference = trim((string) ($data['source_reference'] ?? ''));

        return [
            'errors' => $errors,
            'subject' => $subject,
            'level' => $level,
            'question' => $question,
            'options' => $options,
            'correct' => $correct,
            'difficulty' => $difficulty ?? Difficulty::Medium,
            'status' => $status ?? ContentStatus::Draft,
            'question_type' => $questionType ?? QuestionType::Single,
            'explanation' => $explanation !== '' ? $explanation : null,
            'source_reference' => $sourceReference !== '' ? $sourceReference : null,
        ];
    }

    /**
     * Convertir les lettres ou chiffres de la colonne correct en lettres A à E.
     *
     * @return array<int, string>
     */
    private function parseCorrectLetters(string $value): array
    {
        $value = strtoupper(trim($value));

        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[\s,;|]+/', $value) ?: [];
        $letters = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match('/^[A-E]$/', $part) === 1) {
                $letters[] = $part;
            } elseif (preg_match('/^[1-5]$/', $part) === 1) {
                $letters[] = chr(64 + (int) $part);
            } else {
                return [];
            }
        }

        return array_values(array_unique($letters));
    }

    private function findSubject(string $value): ?Subject
    {
        return Subject::query()->where('slug', Str::slug($value))->first()
            ?? Subject::query()->where('name', $value)->first();
    }

    private function findLevel(string $value): ?Level
    {
        return Level::query()->where('slug', Str::slug($value))->first()
            ?? Level::query()->where('name', $value)->first();
    }

    private function fingerprint(Subject $subject, Level $level, string $question): string
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($question)) ?? trim($question));

        return hash('sha256', $subject->id.'|'.$level->id.'|'.$normalized);
    }

    /**
     * @param  array<int, array{row: int, errors: array<int, string>}>  $errors
     * @param  array<int, string>  $messages
     */
    private function recordError(array &$errors, int $line, array $messages): void
    {
        if (count($errors) < self::MAX_ERRORS) {
            $errors[] = ['row' => $line, 'errors' => $messages];
        }
    }
}
