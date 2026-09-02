<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Services\CostResolver;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\ReturnType;
use App\Models\Order;

/**
 * Resolves an order down the profitability chain:
 *
 *   Gross → − Discounts → − Cancelled → = Invoiced
 *         → − Returns → − RTO → = Net Sales
 *         → − COGS → − Fees → − Logistics → = Contribution Margin
 *
 * Writes the resolved amounts back onto the order and its line items so every
 * downstream rollup and report reads one consistent set of numbers.
 */
class ComputeOrderEconomics
{
    public function __construct(private readonly CostResolver $costs) {}

    public function handle(Order $order, bool $save = true): Order
    {
        $order->loadMissing(['items.sku', 'items.sku.costHistory', 'returns', 'shipments', 'fees', 'transactions']);
        $tenant = $order->tenant()->firstOrFail();
        $date = $order->placed_at->toDateString();
        $isCod = $order->payment_mode === PaymentMode::Cod;

        $grossAmount = 0;
        $discountAmount = 0;
        $cogs = 0;
        $units = 0;

        foreach ($order->items as $item) {
            $lineGross = $item->unit_price * $item->qty;
            $lineNet = $lineGross - $item->discount;
            $unitCost = $item->sku !== null ? $this->costs->costFor($item->sku, $date) : $item->cogs_unit;

            $grossAmount += $lineGross;
            $discountAmount += $item->discount;
            $units += $item->qty;

            $sellableQty = max(0, $item->qty - $item->returned_qty);
            $lineCogs = $unitCost * $item->qty;
            $cogs += $unitCost * $sellableQty;

            $item->forceFill([
                'cogs_unit' => $unitCost,
                'line_gross' => $lineGross,
                'line_net' => $lineNet,
                'line_margin' => $lineNet - $lineCogs,
            ]);

            if ($save) {
                $item->save();
            }
        }

        $invoicedAmount = in_array($order->status->value, OrderStatus::invoicedValues(), true)
            ? $grossAmount - $discountAmount + $order->shipping_amount
            : 0;

        $cancelledAmount = $order->status === OrderStatus::Cancelled
            ? $grossAmount - $discountAmount
            : 0;

        $returnedAmount = (int) $order->returns
            ->where('type', ReturnType::CustomerReturn)
            ->sum('refund_amount');

        $rtoAmount = $order->is_rto ? $invoicedAmount : 0;

        $netAmount = max(0, $invoicedAmount - $returnedAmount - $rtoAmount);

        $marketplaceFees = (int) $order->fees->sum('amount');
        $gatewayFee = $this->costs->gatewayFee($tenant, $netAmount, $isCod)
            + (int) $order->transactions->where('kind', 'sale')->sum('fee');

        $shipmentCost = (int) $order->shipments->sum('shipping_cost');
        $rtoCost = (int) $order->shipments->sum('rto_cost');
        $settings = $this->costs->settingsFor($tenant);

        $logistics = $shipmentCost > 0 ? $shipmentCost : ($invoicedAmount > 0 ? $settings->default_shipping_cost : 0);
        $logistics += $rtoCost;

        $returnCost = ($order->is_rto ? $settings->rto_handling_cost : 0)
            + ($returnedAmount > 0 ? $settings->return_handling_cost : 0);

        $packaging = $invoicedAmount > 0 ? $settings->packaging_cost + $settings->per_order_fixed_cost : 0;
        $codCharge = $isCod && $invoicedAmount > 0 ? $settings->cod_charge : 0;

        $contributionMargin = $netAmount - $cogs - $marketplaceFees - $logistics - $returnCost - $packaging - $gatewayFee - $codCharge;

        $order->forceFill([
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'invoiced_amount' => $invoicedAmount,
            'cancelled_amount' => $cancelledAmount,
            'returned_amount' => $returnedAmount,
            'rto_amount' => $rtoAmount,
            'net_amount' => $netAmount,
            'cogs_amount' => $cogs,
            'fees_amount' => $marketplaceFees,
            'logistics_amount' => $logistics + $codCharge,
            'return_cost_amount' => $returnCost,
            'packaging_amount' => $packaging,
            'gateway_fee_amount' => $gatewayFee,
            'contribution_margin' => $contributionMargin,
            'units_count' => $units,
            'items_count' => $order->items->count(),
            'has_return' => $returnedAmount > 0,
        ]);

        if ($save) {
            $order->save();
        }

        return $order;
    }
}
