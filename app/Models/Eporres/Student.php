<?php

namespace App\Models\Eporres;

use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends EporresModel
{
    use SoftDeletes;

    protected $table = 'students';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function career()
    {
        return $this->belongsTo(Career::class);
    }
}
