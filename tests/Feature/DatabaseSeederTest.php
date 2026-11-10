<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_can_run_more_than_once_without_duplicate_test_users(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('users', 1);
        $this->assertModelExists(User::where('email', 'test@example.com')->firstOrFail());
    }
}
