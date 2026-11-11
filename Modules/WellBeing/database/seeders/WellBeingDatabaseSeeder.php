<?php

namespace Modules\WellBeing\Database\Seeders;

use Illuminate\Database\Seeder;

class WellBeingDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(WellBeingDemoSeeder::class);
    }
}
