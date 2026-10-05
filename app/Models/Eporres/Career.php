<?php

namespace App\Models\Eporres;

use Illuminate\Database\Eloquent\SoftDeletes;

class Career extends EporresModel
{
    use SoftDeletes;

    protected $table = 'careers';
}
