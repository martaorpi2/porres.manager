<?php

namespace App\Models\Eporres;

use Illuminate\Database\Eloquent\SoftDeletes;

class NormativeInstrument extends EporresModel
{
    use SoftDeletes;

    protected $table = 'normative_instruments';

    protected $casts = [
        'fecha_emision' => 'date',
    ];
}
