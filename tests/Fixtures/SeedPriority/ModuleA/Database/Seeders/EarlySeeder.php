<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\SeedPriority\ModuleA\Database\Seeders;

use Illuminate\Database\Seeder;
use Laravarc\Core\Metadata\Attributes\SeedPriority;

#[SeedPriority(100)]
final class EarlySeeder extends Seeder
{
    public function run(): void {}
}
