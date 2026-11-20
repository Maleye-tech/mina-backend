<?php

namespace Modules\Library\Enums;

enum ContentType: string
{
    case Course = 'course';
    case SummarySheet = 'summary_sheet';
    case Recap = 'recap';

    public function label(): string
    {
        return match ($this) {
            self::Course => 'Cours',
            self::SummarySheet => 'Fiche de synthèse',
            self::Recap => 'Résumé',
        };
    }

    public static function options(): array
    {
        return array_column(
            array_map(
                static fn (self $case): array => [$case->value, $case->label()],
                self::cases(),
            ),
            1,
            0,
        );
    }
}
