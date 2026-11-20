<?php

namespace Modules\Library\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

final class SignedUrl
{
    public static function for(string $path): string
    {
        $disk = Storage::disk(config('library.audio_disk'));

        try {
            return $disk->temporaryUrl(
                $path,
                now()->addMinutes(config('library.signed_url_ttl_minutes')),
            );
        } catch (Throwable) {
            return $disk->url($path);
        }
    }
}
