<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disque de stockage des fichiers audio
    |--------------------------------------------------------------------------
    |
    | "s3" en production, "local" (ou "public") en développement. Les tests
    | utilisent un disque simulé via Storage::fake().
    |
    */

    'audio_disk' => env('LIBRARY_AUDIO_DISK', 's3'),

    'originals_directory' => 'podcasts/originals',

    'compressed_directory' => 'podcasts/compressed',

    /*
    |--------------------------------------------------------------------------
    | Limites d'upload audio
    |--------------------------------------------------------------------------
    */

    'max_audio_mb' => (int) env('LIBRARY_MAX_AUDIO_MB', 200),

    'accepted_audio_types' => [
        'audio/mpeg',
        'audio/mp4',
        'audio/x-m4a',
        'audio/wav',
        'audio/x-wav',
        'audio/ogg',
        'audio/aac',
        'audio/webm',
    ],

    /*
    |--------------------------------------------------------------------------
    | Compression (ffmpeg)
    |--------------------------------------------------------------------------
    |
    | Si ffmpeg est introuvable sur le serveur, la compression est skippée et
    | le fichier original reste utilisable tel quel.
    |
    */

    'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),

    'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),

    'mp3_bitrate' => env('LIBRARY_MP3_BITRATE', '96k'),

    /*
    |--------------------------------------------------------------------------
    | URLs temporaires (manifeste hors-ligne)
    |--------------------------------------------------------------------------
    */

    'signed_url_ttl_minutes' => (int) env('LIBRARY_SIGNED_URL_TTL_MINUTES', 1440),

];
