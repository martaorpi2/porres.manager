<?php

namespace App\Models\Eporres;

use Illuminate\Database\Eloquent\Model;

abstract class EporresModel extends Model
{
    protected $connection = 'eporres';
}
