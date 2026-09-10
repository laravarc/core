<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\SeedPriority\ModuleZ\Database\Seeders;

use Illuminate\Database\Seeder;
use Laravarc\Core\Metadata\Attributes\SeedPriority;

#[SeedPriority(10)]
final class LateSeeder extends Seeder
{
    public function run(): void {}
}
