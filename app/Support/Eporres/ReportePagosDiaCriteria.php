<?php

namespace App\Support\Eporres;

use App\Models\Eporres\Order;
use App\Models\Eporres\StudentPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Criterios de “Pagos por Día”: cobros de caja vs imputaciones a plan de pago.
 */
final class ReportePagosDiaCriteria
{
    public const PAYMENT_TYPE_PLAN = 'Plan de Pago';

    public const PEND_PLAN_STATE_PAID = 'Pagado';

    public const VISTA_COBROS = 'cobros';

    public const VISTA_IMPUTACIONES = 'imputaciones';

    public const QUOTA_ARANCEL_MAX = 10;

    public const PLAN_QUOTA_MIN = 100;

    public const DISCOUNT_REASON = 'Reducción arancelaria por razones económicas';

    public const DISCOUNT_REASON_ART14 = 'Reducción arancelaria excepcional (art. 14)';

    /** @var list<string> */
    public const OTROS_CONCEPTOS = [
        'Libreta',
        'Equivalencia',
        'Egreso',
        'Constancia',
        'Titulo',
        'Título',
    ];

    public const OTROS_QUOTA_MIN = 11;

    public const OTROS_QUOTA_MAX = 99;

    /** @return list<string> */
    public static function vistas(): array
    {
        return [self::VISTA_COBROS, self::VISTA_IMPUTACIONES];
    }

    public static function normalizeVista(?string $vista): string
    {
        return in_array($vista, self::vistas(), true) ? $vista : self::VISTA_COBROS;
    }

    public static function isImputacionPlan(Order $order): bool
    {
        $quota = (int) $order->quota_number;
        if ($quota < 0 || $quota > self::QUOTA_ARANCEL_MAX) {
            return false;
        }

        return trim((string) ($order->payment_type ?? '')) === self::PAYMENT_TYPE_PLAN
            || self::estaSaldadaPorPlan($order);
    }

    public static function estaSaldadaPorPlan(Order $order): bool
    {
        $lineas = $order->relationLoaded('pend_plans')
            ? $order->pend_plans
            : $order->pend_plans()->get();

        return $lineas->isNotEmpty()
            && $lineas->every(
                fn ($linea) => strcasecmp((string) $linea->state, self::PEND_PLAN_STATE_PAID) === 0
            );
    }

    public static function whereSaldadaPorPlan(Builder $query): Builder
    {
        return $query
            ->whereHas('pend_plans')
            ->whereDoesntHave('pend_plans', function (Builder $linea) {
                $linea->where('state', '!=', self::PEND_PLAN_STATE_PAID);
            });
    }

    public static function whereNoSaldadaPorPlan(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereDoesntHave('pend_plans')
                ->orWhereHas('pend_plans', function (Builder $linea) {
                    $linea->where('state', '!=', self::PEND_PLAN_STATE_PAID);
                });
        });
    }

    public static function isCuotaPlanPago(Order $order): bool
    {
        return (int) $order->quota_number >= self::PLAN_QUOTA_MIN;
    }

    public static function isOtroConcepto(Order $order): bool
    {
        if (self::isCuotaPlanPago($order) || self::isImputacionPlan($order)) {
            return false;
        }

        $q = (int) $order->quota_number;
        if ($q >= self::OTROS_QUOTA_MIN && $q <= self::OTROS_QUOTA_MAX) {
            return true;
        }

        $tariffType = trim((string) (optional($order->tariff_category)->type ?? ''));

        return $tariffType !== '' && in_array($tariffType, self::OTROS_CONCEPTOS, true);
    }

    public static function baseQuery(string $fechaDesde, string $fechaHasta): Builder
    {
        return Order::query()
            ->where('state', Order::STATE_PAID)
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $fechaDesde)
            ->whereDate('paid_at', '<=', $fechaHasta);
    }

    public static function applyVista(Builder $query, string $vista): Builder
    {
        $vista = self::normalizeVista($vista);

        if ($vista === self::VISTA_IMPUTACIONES) {
            return $query
                ->whereBetween('quota_number', [0, self::QUOTA_ARANCEL_MAX])
                ->where(function (Builder $q) {
                    $q->where('payment_type', self::PAYMENT_TYPE_PLAN)
                        ->orWhere(fn (Builder $plan) => self::whereSaldadaPorPlan($plan));
                });
        }

        $query->where(function (Builder $q) {
            $q->where(function (Builder $arancel) {
                $arancel->whereBetween('quota_number', [0, self::QUOTA_ARANCEL_MAX])
                    ->where(function (Builder $medio) {
                        $medio->whereNull('payment_type')
                            ->orWhere('payment_type', '')
                            ->orWhere('payment_type', '!=', self::PAYMENT_TYPE_PLAN);
                    })
                    ->whereDoesntHave('tariff_category', function (Builder $t) {
                        $t->whereIn('type', self::OTROS_CONCEPTOS);
                    });
            })->orWhere(function (Builder $otros) {
                $otros->where('quota_number', '<', self::PLAN_QUOTA_MIN)
                    ->where(function (Builder $o) {
                        $o->whereBetween('quota_number', [self::OTROS_QUOTA_MIN, self::OTROS_QUOTA_MAX])
                            ->orWhereHas('tariff_category', function (Builder $t) {
                                $t->whereIn('type', self::OTROS_CONCEPTOS);
                            });
                    })
                    ->where(function (Builder $medio) {
                        $medio->whereNull('payment_type')
                            ->orWhere('payment_type', '')
                            ->orWhere('payment_type', '!=', self::PAYMENT_TYPE_PLAN);
                    });
            })->orWhere(function (Builder $plan) {
                $plan->where('quota_number', '>=', self::PLAN_QUOTA_MIN);
            });
        });

        return self::whereNoSaldadaPorPlan($query);
    }

    public static function applyTipoPago(Builder $query, ?string $tipoPago, string $vista): Builder
    {
        $vista = self::normalizeVista($vista);

        if ($vista === self::VISTA_IMPUTACIONES) {
            if ($tipoPago === 'matricula') {
                return $query->where('quota_number', 0);
            }
            if ($tipoPago === 'cuota') {
                return $query->whereBetween('quota_number', [1, self::QUOTA_ARANCEL_MAX]);
            }

            return $query;
        }

        if ($tipoPago === 'matricula') {
            return $query->where('quota_number', 0)
                ->where(function (Builder $q) {
                    $q->whereDoesntHave('tariff_category', function (Builder $t) {
                        $t->whereIn('type', self::OTROS_CONCEPTOS);
                    })->orWhereHas('tariff_category', function (Builder $t) {
                        $t->where('type', 'Matrícula');
                    });
                });
        }
        if ($tipoPago === 'cuota') {
            return $query->whereBetween('quota_number', [1, self::QUOTA_ARANCEL_MAX])
                ->where(function (Builder $q) {
                    $q->whereDoesntHave('tariff_category', function (Builder $t) {
                        $t->whereIn('type', self::OTROS_CONCEPTOS);
                    })->orWhereHas('tariff_category', function (Builder $t) {
                        $t->where('type', 'Arancel Mensual');
                    });
                });
        }
        if ($tipoPago === 'plan') {
            return $query->where('quota_number', '>=', self::PLAN_QUOTA_MIN);
        }
        if ($tipoPago === 'otros') {
            return $query->where(function (Builder $q) {
                $q->whereBetween('quota_number', [self::OTROS_QUOTA_MIN, self::OTROS_QUOTA_MAX])
                    ->orWhereHas('tariff_category', function (Builder $t) {
                        $t->whereIn('type', self::OTROS_CONCEPTOS);
                    });
            })->where('quota_number', '<', self::PLAN_QUOTA_MIN);
        }

        return $query;
    }

    public static function labelTipo(Order $order): string
    {
        if (self::isImputacionPlan($order)) {
            return (int) $order->quota_number === 0
                ? 'Matrícula (imputada a plan)'
                : 'Cuota (imputada a plan)';
        }

        if (self::isCuotaPlanPago($order)) {
            return 'Plan de pago';
        }

        if (self::isOtroConcepto($order)) {
            $tariffType = trim((string) (optional($order->tariff_category)->type ?? ''));
            if ($tariffType !== '') {
                return $tariffType === 'Titulo' ? 'Título' : $tariffType;
            }

            return self::labelOtroPorQuota((int) $order->quota_number);
        }

        return (int) $order->quota_number === 0 ? 'Matrícula' : 'Cuota';
    }

    public static function isBeca(Order $order): bool
    {
        if ((float) $order->grant_amount > 0.005) {
            return true;
        }

        return self::hasBecaDiscount($order);
    }

    public static function labelBeca(Order $order): string
    {
        if (! self::isBeca($order)) {
            return '';
        }

        $pct = self::becaPercentage($order);
        if ($pct <= 0) {
            return 'Becas';
        }

        return 'Becas '.self::formatBecaPercentage($pct).'%';
    }

    public static function becaPercentage(Order $order): float
    {
        $raw = (float) ($order->percentage_discount ?? 0);
        if ($raw > 1) {
            return round($raw, 2);
        }
        if ($raw > 0) {
            return round($raw * 100, 2);
        }

        return 0.0;
    }

    public static function formatBecaPercentage(float $pct): string
    {
        $normalized = round($pct, 2);
        if (abs($normalized - round($normalized)) < 0.001) {
            return (string) (int) round($normalized);
        }

        return rtrim(rtrim(number_format($normalized, 2, ',', ''), '0'), ',');
    }

    public static function notaReporte(Order $order, Collection $relatedByPlanId): string
    {
        $beca = self::labelBeca($order);
        $vinculo = self::notaVinculo($order, $relatedByPlanId);
        if ($beca !== '' && $vinculo !== '') {
            return $beca.' · '.$vinculo;
        }

        return $beca !== '' ? $beca : $vinculo;
    }

    private static function hasBecaDiscount(Order $order): bool
    {
        if (! self::isBecaReason((string) ($order->discount_reason ?? ''))) {
            return false;
        }

        return (float) ($order->discount_amount ?? 0) > 0.005
            || (float) ($order->percentage_discount ?? 0) > 0;
    }

    private static function isBecaReason(string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '') {
            return false;
        }

        $known = [
            self::DISCOUNT_REASON,
            self::DISCOUNT_REASON_ART14,
            'Reducción arancelaria',
        ];
        foreach ($known as $label) {
            if (strcasecmp($reason, $label) === 0) {
                return true;
            }
        }

        return strncasecmp($reason, 'Reducción arancelaria', strlen('Reducción arancelaria')) === 0;
    }

    public static function labelOtroPorQuota(int $quota): string
    {
        return match ($quota) {
            20 => 'Libreta',
            30 => 'Equivalencia',
            40 => 'Egreso',
            50 => 'Título',
            60 => 'Constancia',
            default => 'Otro',
        };
    }

    public static function labelNumeroCuota(Order $order, ?StudentPlan $plan = null): string
    {
        if (self::isCuotaPlanPago($order)) {
            $n = max(1, (int) $order->quota_number - self::PLAN_QUOTA_MIN);
            $dues = $plan?->dues ?? optional($order->student_plan)->dues;
            if ($dues) {
                return $n.'/'.(int) $dues;
            }

            return (string) $n;
        }

        if (self::isOtroConcepto($order)) {
            return '—';
        }

        return (string) (int) $order->quota_number;
    }

    public static function coveredArancelByPlanId(Collection $planOrders): Collection
    {
        $planIds = $planOrders
            ->filter(fn (Order $o) => self::isCuotaPlanPago($o))
            ->pluck('student_plan_id')
            ->filter()
            ->unique()
            ->values();

        if ($planIds->isEmpty()) {
            return collect();
        }

        return Order::query()
            ->whereIn('student_plan_id', $planIds)
            ->whereBetween('quota_number', [0, self::QUOTA_ARANCEL_MAX])
            ->where('state', Order::STATE_PAID)
            ->where(function (Builder $q) {
                $q->where('payment_type', self::PAYMENT_TYPE_PLAN)
                    ->orWhere(fn (Builder $plan) => self::whereSaldadaPorPlan($plan));
            })
            ->orderBy('quota_number')
            ->get()
            ->groupBy('student_plan_id');
    }

    public static function planInstallmentsByPlanId(Collection $imputaciones): Collection
    {
        $planIds = $imputaciones
            ->pluck('student_plan_id')
            ->filter()
            ->unique()
            ->values();

        if ($planIds->isEmpty()) {
            return collect();
        }

        return Order::query()
            ->whereIn('student_plan_id', $planIds)
            ->where('quota_number', '>=', self::PLAN_QUOTA_MIN)
            ->where('state', Order::STATE_PAID)
            ->whereNotNull('paid_at')
            ->orderBy('quota_number')
            ->get()
            ->groupBy('student_plan_id');
    }

    public static function notaVinculo(Order $order, Collection $relatedByPlanId): string
    {
        if (! $order->student_plan_id) {
            return '';
        }

        $related = $relatedByPlanId->get($order->student_plan_id, collect());
        if ($related->isEmpty()) {
            return '';
        }

        if (self::isCuotaPlanPago($order)) {
            $ids = $related->map(function (Order $o) {
                $tipo = (int) $o->quota_number === 0 ? 'Mat.' : 'C'.(int) $o->quota_number;

                return $tipo.' #'.$o->id;
            })->implode(', ');

            return 'Cubre: '.$ids;
        }

        if (self::isImputacionPlan($order)) {
            $ids = $related->map(function (Order $o) {
                $n = max(1, (int) $o->quota_number - self::PLAN_QUOTA_MIN);

                return 'Plan c'.$n.' #'.$o->id;
            })->implode(', ');

            return 'Cobro(s) de plan: '.$ids;
        }

        return '';
    }
}
