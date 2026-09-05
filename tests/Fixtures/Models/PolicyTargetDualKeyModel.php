<?php

declare(strict_types=1);

namespace Laravarc\Core\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PolicyTargetDualKeyModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'policy_target_dual_key_models';

    protected $fillable = ['uuid', 'name'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
