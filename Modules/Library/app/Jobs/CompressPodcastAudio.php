<?php

namespace Modules\Library\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Library\Enums\CompressionStatus;
use Modules\Library\Models\Podcast;
use Symfony\Component\Process\Process;
use Throwable;

class CompressPodcastAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 900;

    public function __construct(public int $podcastId) {}

    public function handle(): void
    {
        $podcast = Podcast::find($this->podcastId);

        if (! $podcast || ! $podcast->original_path) {
            return;
        }

        $disk = config('library.audio_disk');
        $ffmpeg = config('library.ffmpeg_path');
        $ffprobe = config('library.ffprobe_path');

        $podcast->forceFill(['compression_status' => CompressionStatus::Processing])->save();

        if (! $this->binaryAvailable($ffmpeg)) {
            $podcast->forceFill([
                'compression_status' => CompressionStatus::Skipped,
                'compression_error' => 'ffmpeg introuvable sur le serveur.',
            ])->save();

            return;
        }

        if (Storage::disk($disk)->missing($podcast->original_path)) {
            $podcast->forceFill([
                'compression_status' => CompressionStatus::Skipped,
                'compression_error' => 'Fichier original introuvable sur le disque.',
            ])->save();

            return;
        }

        $temporaryDirectory = storage_path('app/tmp');
        is_dir($temporaryDirectory) || mkdir($temporaryDirectory, 0755, true);

        $inputPath = tempnam($temporaryDirectory, 'podcast-in-');
        $outputPath = $inputPath.'.mp3';

        try {
            $this->copyToTemporaryFile($disk, $podcast->original_path, $inputPath);

            $process = new Process([
                $ffmpeg,
                '-y',
                '-i', $inputPath,
                '-codec:a', 'libmp3lame',
                '-b:a', config('library.mp3_bitrate'),
                '-vn',
                $outputPath,
            ]);
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outputPath)) {
                $podcast->forceFill([
                    'compression_status' => CompressionStatus::Failed,
                    'compression_error' => $this->excerpt($process->getErrorOutput()),
                ])->save();

                return;
            }

            $compressedPath = config('library.compressed_directory').'/'.$podcast->id.'.mp3';

            Storage::disk($disk)->put(
                $compressedPath,
                fopen($outputPath, 'rb'),
            );

            $podcast->forceFill([
                'compressed_path' => $compressedPath,
                'compression_status' => CompressionStatus::Done,
                'compression_error' => null,
                'duration_seconds' => $this->probeDuration($ffprobe, $inputPath)
                    ?? $podcast->duration_seconds,
            ])->save();
        } catch (Throwable $exception) {
            $podcast->forceFill([
                'compression_status' => CompressionStatus::Failed,
                'compression_error' => $this->excerpt($exception->getMessage()),
            ])->save();
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
        }
    }

    private function copyToTemporaryFile(string $disk, string $path, string $destination): void
    {
        $input = fopen($destination, 'wb');
        $stream = Storage::disk($disk)->readStream($path);

        try {
            stream_copy_to_stream($stream, $input);
        } finally {
            fclose($input);
            is_resource($stream) && fclose($stream);
        }
    }

    private function binaryAvailable(string $binary): bool
    {
        try {
            $process = new Process([$binary, '-version']);
            $process->setTimeout(30);
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    private function probeDuration(string $ffprobe, string $file): ?int
    {
        try {
            $process = new Process([
                $ffprobe,
                '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'csv=p=0',
                $file,
            ]);
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $duration = (float) trim($process->getOutput());

            return $duration > 0 ? (int) round($duration) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function excerpt(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return $message !== '' ? Str::limit($message, 250) : 'Échec de la compression audio.';
    }
}
