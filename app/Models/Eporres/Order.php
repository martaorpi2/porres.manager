<?php

namespace App\Models\Eporres;

class Order extends EporresModel
{
    public const STATE_PAID = 'App\\States\\Order\\Paid';

    public const STATE_PENDING = 'App\\States\\Order\\Pending';

    protected $table = 'orders';

    /** @var list<string> */
    protected static array $importeNullAsZero = [
        'amount_paid',
        'quota_amount',
        'positive_balance',
        'negative_balance',
        'discount_amount',
        'surcharge_amount',
        'grant_amount',
        'surcharge_grant',
        'surcharge_amountMP',
        'surcharge_amountCard',
    ];

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (in_array($key, self::$importeNullAsZero, true) && ($value === null || $value === '')) {
            return 0;
        }

        return $value;
    }

    public function scopePaid($query)
    {
        return $query->where('state', self::STATE_PAID);
    }

    public function tariff_category()
    {
        return $this->belongsTo(TariffCategory::class, 'tariff_category_id');
    }

    public function splitPayment()
    {
        return $this->hasOne(OrderSplitPayment::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function student_plan()
    {
        return $this->belongsTo(StudentPlan::class);
    }

    public function pend_plans()
    {
        return $this->hasMany(OrderPendPlan::class, 'order_id');
    }

    public function amortization()
    {
        return $this->hasOne(Amortization::class);
    }
}
