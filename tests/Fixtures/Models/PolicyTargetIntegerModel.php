<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class PolicyTargetIntegerModel extends Model
{
    public $timestamps = false;

    protected $table = 'policy_target_integer_models';

    protected $fillable = ['name'];
}
