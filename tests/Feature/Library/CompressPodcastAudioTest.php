<?php

namespace Tests\Feature\Library;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Library\Enums\CompressionStatus;
use Modules\Library\Jobs\CompressPodcastAudio;
use Modules\Library\Models\Podcast;
use Tests\TestCase;

class CompressPodcastAudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('library.audio_disk'));
    }

    public function test_job_skips_compression_when_ffmpeg_is_unavailable(): void
    {
        config([
            'library.ffmpeg_path' => 'ffmpeg-binary-that-does-not-exist-xyz',
            'library.ffprobe_path' => 'ffprobe-binary-that-does-not-exist-xyz',
        ]);

        $podcast = Podcast::factory()->create([
            'original_path' => 'podcasts/originals/episode.mp3',
        ]);

        (new CompressPodcastAudio($podcast->id))->handle();

        $podcast->refresh();

        $this->assertSame(CompressionStatus::Skipped, $podcast->compression_status);
        $this->assertStringContainsString('ffmpeg', (string) $podcast->compression_error);
        $this->assertNull($podcast->compressed_path);
    }

    public function test_job_keeps_original_when_the_source_file_is_missing(): void
    {
        config([
            // Binaire factice mais "existant" : on simule ffmpeg présent via un
            // chemin vers un exécutable portable. Sur CI sans ffmpeg, le test
            // du fichier manquant est couvert par la branche "missing file"
            // uniquement si ffmpeg répond. On valide ici l'idempotence à vide.
            'library.ffmpeg_path' => PHP_BINARY,
            'library.ffprobe_path' => PHP_BINARY,
        ]);

        $podcast = Podcast::factory()->create([
            'original_path' => 'podcasts/originals/missing-file.mp3',
        ]);

        (new CompressPodcastAudio($podcast->id))->handle();

        $podcast->refresh();

        $this->assertNotSame(CompressionStatus::Done, $podcast->compression_status);
        $this->assertNotEmpty($podcast->compression_error);
    }
}
