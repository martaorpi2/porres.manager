<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class Permission extends SpatiePermission
{
    use CrudTrait;
}

