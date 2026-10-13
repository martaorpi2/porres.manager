<?php

namespace Tests\Unit;

use App\Models\Eporres\MercadoPagoWebhookEvent;
use App\Models\Eporres\Order;
use App\Models\Eporres\Student;
use App\Services\EporresMercadoPagoReleaseReport;
use Tests\TestCase;

class EporresMercadoPagoReleaseReportTest extends TestCase
{
    public function test_row_uses_the_release_date_in_local_time(): void
    {
        $event = $this->event([
            'status' => 'approved',
            'transaction_amount' => 230560,
            'payload_snapshot' => [
                'payment' => [
                    'status' => 'approved',
                    'money_release_status' => 'pending',
                    'money_release_date' => '2026-08-16T08:40:21.000-04:00',
                ],
            ],
        ]);

        $row = (new EporresMercadoPagoReleaseReport)->rowFromEvent($event);

        $this->assertSame('pending', $row->release_status);
        $this->assertSame('approved', $row->mp_status);
        $this->assertSame(230560.0, $row->amount);
        $this->assertSame('2026-08-16 09:40:21', $row->release_at);
    }

    public function test_filters_keep_the_matching_release_window(): void
    {
        $report = new EporresMercadoPagoReleaseReport;
        $rows = collect([
            $this->row('approved', 'pending', '2026-08-16 09:40:21', 100),
            $this->row('approved', 'released', '2026-08-20 10:00:00', 50),
            $this->row('rejected', 'released', '2026-08-18 10:00:00', 25),
        ]);

        $filtered = $report->filterRows($rows, 'approved', 'released', '2026-08-19', '2026-08-21');

        $this->assertCount(1, $filtered);
        $this->assertSame(50.0, $filtered->first()->amount);
    }

    public function test_empty_release_date_drops_out_of_a_date_filter(): void
    {
        $report = new EporresMercadoPagoReleaseReport;
        $rows = collect([
            $this->row('approved', 'pending', null, 10),
        ]);

        $this->assertCount(0, $report->filterRows($rows, 'approved', '', '2026-08-01', ''));
        $this->assertCount(1, $report->filterRows($rows, 'approved', 'pending', '', ''));
    }

    public function test_student_label_uses_the_order(): void
    {
        $student = new Student;
        $student->forceFill([
            'last_name' => 'Perez',
            'first_name' => 'Ana',
            'dni' => '30111222',
        ]);
        $order = new Order;
        $order->forceFill(['id' => 63326]);
        $order->setRelation('student', $student);

        $event = $this->event([
            'order_id' => 63326,
            'payment_id' => '999',
            'status' => '',
            'payload_snapshot' => ['payment' => ['status' => 'approved']],
        ]);
        $event->setRelation('order', $order);

        $row = (new EporresMercadoPagoReleaseReport)->rowFromEvent($event);

        $this->assertSame(63326, $row->event->order->id);
        $this->assertSame('Perez', $row->event->order->student->last_name);
        $this->assertSame('approved', $row->mp_status);
    }

    private function event(array $attributes): MercadoPagoWebhookEvent
    {
        $event = new MercadoPagoWebhookEvent;
        $event->forceFill($attributes);

        return $event;
    }

    private function row(string $mpStatus, string $releaseStatus, ?string $releaseAt, float $amount): object
    {
        return (object) [
            'mp_status' => $mpStatus,
            'release_status' => $releaseStatus,
            'release_at' => $releaseAt,
            'amount' => $amount,
        ];
    }
}
