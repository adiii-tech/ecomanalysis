<?php

declare(strict_types=1);

use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Jobs\DeliverScheduledReport;
use App\Domain\Reports\Reports\ReportRegistry;
use App\Domain\Reports\Services\DatasetFileWriter;
use App\Domain\Sales\Actions\ComputeOrderEconomics;
use App\Enums\ChannelType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Mail\ScheduledReportMail;
use App\Models\Channel;
use App\Models\CostSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReportSchedule;
use App\Models\Sku;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);

    CostSetting::query()->create(['tenant_id' => $this->tenant->id, 'gateway_fee_pct' => 2.0]);

    $channel = Channel::query()->create([
        'tenant_id' => $this->tenant->id, 'name' => 'Shopify', 'code' => 'shopify', 'type' => ChannelType::D2c,
    ]);

    $sku = Sku::query()->create([
        'tenant_id' => $this->tenant->id, 'source' => 'test', 'external_id' => 'v1',
        'sku_code' => 'SKU-1', 'name' => 'Indigo Kurta', 'mrp' => 150000,
        'selling_price' => 100000, 'cost_price' => 40000, 'hsn' => '6204', 'gst_rate' => 5,
    ]);

    $order = Order::query()->create([
        'tenant_id' => $this->tenant->id, 'channel_id' => $channel->id, 'source' => 'test',
        'external_id' => 'o-1', 'order_number' => '#4001', 'placed_at' => CarbonImmutable::now()->subDay(),
        'status' => OrderStatus::Delivered, 'payment_mode' => PaymentMode::Prepaid,
        'shipping_state' => 'Maharashtra', 'shipping_city' => 'Mumbai',
    ]);

    OrderItem::query()->create([
        'tenant_id' => $this->tenant->id, 'order_id' => $order->id, 'sku_id' => $sku->id,
        'sku_code' => 'SKU-1', 'qty' => 1, 'unit_price' => 100000, 'discount' => 0, 'tax' => 4500, 'cogs_unit' => 40000,
    ]);

    app(ComputeOrderEconomics::class)->handle($order->fresh());
});

function schedule(array $attributes = []): ReportSchedule
{
    return ReportSchedule::query()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'report_key' => 'channel_scorecard',
        'cadence' => 'daily',
        'hour' => (int) now(test()->tenant->timezone)->hour,
        'recipients' => ['founder@brand.test'],
        'format' => 'csv',
        'filters' => ['preset' => 'last_30_days', 'channel' => 'all'],
        'is_active' => true,
        ...$attributes,
    ]);
}

it('queues only the schedules that are due this hour', function (): void {
    Queue::fake();

    $due = schedule();
    $laterToday = schedule(['hour' => ((int) now($this->tenant->timezone)->hour + 3) % 24]);
    $paused = schedule(['is_active' => false]);

    $this->artisan('reports:deliver')->assertSuccessful();

    Queue::assertPushed(DeliverScheduledReport::class, 1);
    Queue::assertPushed(DeliverScheduledReport::class, fn (DeliverScheduledReport $job): bool => $job->scheduleId === $due->id);
    Queue::assertNotPushed(DeliverScheduledReport::class, fn (DeliverScheduledReport $job): bool => in_array($job->scheduleId, [$laterToday->id, $paused->id], true));
});

it('does not send the same schedule twice in one window', function (): void {
    Queue::fake();

    schedule(['last_sent_at' => now()]);

    $this->artisan('reports:deliver')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('fires a weekly schedule only on its chosen day', function (): void {
    Queue::fake();

    $today = (int) now($this->tenant->timezone)->dayOfWeek;
    schedule(['cadence' => 'weekly', 'day_of_week' => ($today + 2) % 7]);

    $this->artisan('reports:deliver')->assertSuccessful();
    Queue::assertNothingPushed();

    schedule(['cadence' => 'weekly', 'day_of_week' => $today]);

    $this->artisan('reports:deliver')->assertSuccessful();
    Queue::assertPushed(DeliverScheduledReport::class, 1);
});

it('emails the report with its file attached and the verdict in the body', function (): void {
    Mail::fake();

    $model = schedule();

    app(DeliverScheduledReport::class, ['scheduleId' => $model->id])->handle(
        app(TenantContext::class),
        app(ReportRegistry::class),
        app(DatasetRegistry::class),
        app(DatasetFileWriter::class),
    );

    Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail): bool {
        return $mail->hasTo('founder@brand.test')
            && $mail->reportLabel === 'Channel Scorecard'
            && $mail->files !== []
            && str_ends_with($mail->files[0]['filename'], '.csv')
            && ($mail->summary['verdict']['headline'] ?? '') !== '';
    });

    expect($model->fresh()->last_sent_at)->not->toBeNull();
});

it('skips a paused schedule even if the job is dispatched directly', function (): void {
    Mail::fake();

    $model = schedule(['is_active' => false]);

    app(DeliverScheduledReport::class, ['scheduleId' => $model->id])->handle(
        app(TenantContext::class),
        app(ReportRegistry::class),
        app(DatasetRegistry::class),
        app(DatasetFileWriter::class),
    );

    Mail::assertNothingSent();
});
