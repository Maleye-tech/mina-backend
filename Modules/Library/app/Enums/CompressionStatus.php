<?php

namespace Modules\Library\Enums;

enum CompressionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Processing => 'En cours',
            self::Done => 'Compressé',
            self::Failed => 'Échec',
            self::Skipped => 'Non compressé (ffmpeg indisponible)',
        };
    }
}
