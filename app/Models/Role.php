<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class Role extends SpatieRole
{
    use CrudTrait;
}

