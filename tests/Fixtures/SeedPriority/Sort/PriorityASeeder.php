<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\SeedPriority\Sort;

use Illuminate\Database\Seeder;
use Laravarc\Core\Metadata\Attributes\SeedPriority;

#[SeedPriority(90)]
final class PriorityASeeder extends Seeder
{
    public function run(): void {}
}
