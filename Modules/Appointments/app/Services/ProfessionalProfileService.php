<?php

namespace Modules\Appointments\Services;

use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\ProfessionalProfile;

class ProfessionalProfileService
{
    public function syncLegacyProfile(ProfessionalProfile $profile): AppointmentProfessional
    {
        return AppointmentProfessional::query()->updateOrCreate(
            ['user_id' => $profile->user_id],
            [
                'display_name' => $profile->display_name,
                'title' => $profile->specialty->label(),
                'specialty' => $profile->specialty->value,
                'languages' => $profile->languages,
                'bio' => $profile->bio,
                'photo_path' => $profile->photo_path,
                'is_available' => true,
                'is_verified' => (bool) $profile->is_verified,
                'charter_accepted_at' => $profile->charter_accepted_at,
            ],
        );
    }
}
