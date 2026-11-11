<?php

namespace Tests\Feature\WellBeing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WellBeing\Database\Seeders\WellBeingDemoSeeder;
use Tests\TestCase;

class WellBeingDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->seed(WellBeingDemoSeeder::class);
        $this->seed(WellBeingDemoSeeder::class);

        $this->assertDatabaseCount('wellbeing_articles', 3);
        $this->assertDatabaseCount('tests', 3);
        $this->assertDatabaseCount('questions', 15);
        $this->assertDatabaseCount('emergency_contacts', 4);
    }

    public function test_demo_seeder_publishes_articles_for_each_category(): void
    {
        $this->seed(WellBeingDemoSeeder::class);

        foreach (['stress', 'burnout', 'sleep'] as $category) {
            $this->assertDatabaseHas('wellbeing_articles', [
                'category' => $category,
                'is_published' => true,
            ]);
        }
    }

    public function test_demo_seeder_creates_one_test_per_type_with_five_questions(): void
    {
        $this->seed(WellBeingDemoSeeder::class);

        foreach (['anxiety', 'stress', 'wellbeing'] as $type) {
            $this->assertDatabaseHas('tests', ['type' => $type]);
        }

        $this->assertDatabaseCount('questions', 15);
    }

    public function test_demo_seeder_includes_the_volkeno_emergency_contact(): void
    {
        $this->seed(WellBeingDemoSeeder::class);

        $this->assertDatabaseHas('emergency_contacts', [
            'phone_number' => (string) config('wellbeing.emergency_contacts.volkeno_phone'),
            'is_active' => true,
        ]);
    }
}
