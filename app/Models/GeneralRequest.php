<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralRequest extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'number',
        'created_by',
        'area_id',
        'title',
        'description',
        'priority',
        'attachments',
        'status',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function area()
    {
        return $this->belongsTo(ResponsibilityArea::class, 'area_id');
    }

    public function purchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class, 'converted_from_general_request_id');
    }

    /**
     * Get the details for this general request.
     */
    public function details()
    {
        return $this->hasMany(GeneralRequestDetail::class);
    }

    public static function generateNextNumber(): string
    {
        $year = now()->year;
        $prefix = 'SG-' . $year . '-';

        $last = static::query()
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $nextSequence = 1;
        if ($last) {
            $parts = explode('-', $last);
            $suffix = end($parts);
            $seq = (int) ltrim($suffix, '0');
            $nextSequence = $seq + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }
}
