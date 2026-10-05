<?php

namespace App\Models\Eporres;

class EconomicReductionBatch extends EporresModel
{
    protected $table = 'economic_reduction_batches';

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function instrumento()
    {
        return $this->belongsTo(NormativeInstrument::class, 'instrumento_normativo_id');
    }
}
