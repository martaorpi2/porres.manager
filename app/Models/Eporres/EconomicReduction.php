<?php

namespace App\Models\Eporres;

class EconomicReduction extends EporresModel
{
    public const ORIGEN_ART_14 = 'art_14';

    protected $table = 'economic_reductions';

    public function batch()
    {
        return $this->belongsTo(EconomicReductionBatch::class, 'batch_id');
    }

    public function isArt14(): bool
    {
        return $this->origen === self::ORIGEN_ART_14;
    }
}
