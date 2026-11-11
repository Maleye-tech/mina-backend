<?php

namespace Tests\Feature\WellBeing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WellBeing\Models\EmergencyContact;
use Tests\Feature\WellBeing\Concerns\InteractsWithWellBeing;
use Tests\TestCase;

class EmergencyContactApiTest extends TestCase
{
    use InteractsWithWellBeing;
    use RefreshDatabase;

    public function test_only_active_contacts_are_returned_ordered_by_name(): void
    {
        EmergencyContact::factory()->create(['name' => 'Zulu']);
        EmergencyContact::factory()->create(['name' => 'Alpha']);
        EmergencyContact::factory()->inactive()->create(['name' => 'Inactif']);

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/emergency-contacts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.1.name', 'Zulu');
    }

    public function test_contacts_expose_their_contact_details(): void
    {
        $contact = EmergencyContact::factory()->create([
            'name' => 'Volkeno',
            'role_or_description' => 'Contact officiel',
            'phone_number' => '+221 76 626 35 68',
            'address' => 'Dakar',
            'availability' => '24h/24, 7j/7',
            'is_active' => true,
        ]);

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/emergency-contacts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $contact->id)
            ->assertJsonPath('data.0.phone_number', '+221 76 626 35 68')
            ->assertJsonPath('data.0.availability', '24h/24, 7j/7');
    }
}
