<?php

declare(strict_types=1);

namespace App\Domain\Sales\Queries;

use App\Models\Order;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Row-level order truth. Every widget drills down to one of these lists.
 */
class OrderQuery
{
    /** @return Builder<Order> */
    public function base(WidgetFilters $filters): Builder
    {
        return Order::query()
            ->with(['channel:id,name,code,color', 'customer:id,masked_email,name,city,state'])
            ->whereBetween('placed_at', [
                $filters->period->from->setTimezone('UTC'),
                $filters->period->to->setTimezone('UTC'),
            ])
            ->when($filters->channelIds !== [], fn (Builder $q) => $q->whereIn('channel_id', $filters->channelIds))
            ->when($filters->paymentMode !== null, fn (Builder $q) => $q->where('payment_mode', $filters->paymentMode))
            ->when($filters->search !== null, fn (Builder $q) => $q->where(function (Builder $inner) use ($filters): void {
                $inner->where('order_number', 'like', '%'.$filters->search.'%')
                    ->orWhere('shipping_city', 'like', '%'.$filters->search.'%')
                    ->orWhere('shipping_state', 'like', '%'.$filters->search.'%');
            }));
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function paginate(WidgetFilters $filters, int $perPage = 25, string $sort = 'placed_at', string $direction = 'desc'): LengthAwarePaginator
    {
        $sortable = [
            'placed_at', 'order_number', 'gross_amount', 'net_amount', 'contribution_margin',
            'status', 'payment_mode', 'shipping_state', 'units_count',
        ];

        return $this->base($filters)
            ->orderBy(in_array($sort, $sortable, true) ? $sort : 'placed_at', $direction === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage)
            ->through(fn (Order $order): array => $this->row($order));
    }

    /** @return array<string, mixed> */
    public function recent(WidgetFilters $filters, int $limit = 10): array
    {
        return [
            'rows' => $this->base($filters)->orderByDesc('placed_at')->limit($limit)->get()
                ->map(fn (Order $order): array => $this->row($order))->all(),
        ];
    }

    /**
     * Orders that lost money, worst first.
     *
     * @return array<string, mixed>
     */
    public function lossMaking(WidgetFilters $filters, int $limit = 10): array
    {
        $orders = $this->base($filters)->lossMaking()->orderBy('contribution_margin')->limit($limit)->get();
        $totalLoss = (int) $this->base($filters)->lossMaking()->sum('contribution_margin');
        $count = $this->base($filters)->lossMaking()->count();

        return [
            'rows' => $orders->map(fn (Order $order): array => $this->row($order))->all(),
            'count' => $count,
            'total_loss' => abs($totalLoss),
            'verdict' => ($count === 0
                ? Verdict::good('No loss-making orders in this view 🎉')
                : Verdict::bad(
                    sprintf('%d orders lost %s between them.', $count, Money::compact(abs($totalLoss))),
                    $orders->first() !== null
                        ? sprintf('Worst is %s at %s.', $orders->first()->order_number, Money::format($orders->first()->contribution_margin))
                        : null,
                    'Look for heavy discounting, high-cost SKUs and RTO on COD in these rows.',
                    abs($totalLoss),
                ))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    private function row(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'placed_at' => $order->placed_at->toIso8601String(),
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'payment_mode' => $order->payment_mode->value,
            'channel' => $order->channel?->only(['id', 'name', 'code', 'color']),
            'customer' => $order->customer?->only(['id', 'masked_email', 'name', 'city', 'state']),
            'shipping_state' => $order->shipping_state,
            'shipping_city' => $order->shipping_city,
            'units' => $order->units_count,
            'gross_amount' => $order->gross_amount,
            'discount_amount' => $order->discount_amount,
            'net_amount' => $order->net_amount,
            'cogs_amount' => $order->cogs_amount,
            'fees_amount' => $order->fees_amount,
            'logistics_amount' => $order->logistics_amount,
            'contribution_margin' => $order->contribution_margin,
            // An RTO order books zero revenue but still costs money. Reporting
            // "0.0%" there reads as break-even, so send null and let the UI
            // show a dash instead.
            'margin_pct' => $order->net_amount === 0 ? null : Num::pct($order->contribution_margin, $order->net_amount),
            'is_rto' => $order->is_rto,
            'has_return' => $order->has_return,
            'is_first_order' => $order->is_first_order,
        ];
    }
}
