<?php

namespace App\Filament\Resources\Library\Pages;

use App\Filament\Resources\Library\PodcastResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePodcast extends CreateRecord
{
    protected static string $resource = PodcastResource::class;
}
