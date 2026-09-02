<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Rollups\Actions\RebuildAdSpendRollup;
use App\Domain\Rollups\Actions\RebuildCohorts;
use App\Domain\Rollups\Actions\RebuildCustomerMetrics;
use App\Domain\Rollups\Actions\RebuildDailyMetrics;
use App\Domain\Rollups\Actions\RebuildPincodeRisk;
use App\Domain\Rollups\Actions\RebuildSkuRollup;
use App\Domain\Rollups\Actions\RebuildStateRollup;
use App\Domain\Sales\Actions\ComputeOrderEconomics;
use App\Enums\ChannelType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\ReturnType;
use App\Enums\ShipmentStatus;
use App\Models\Benchmark;
use App\Models\Channel;
use App\Models\CostSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Support\Money;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Builds a realistic Indian D2C dataset so a brand new tenant can explore the
 * whole product in 30 seconds with zero connectors.
 *
 * The data is deliberately imperfect — some states RTO badly, some SKUs lose
 * money, one channel under-performs — because a demo where everything is green
 * teaches the user nothing.
 */
class DemoDataSeeder extends Seeder
{
    private const DAYS = 120;

    private const ADS_DAYS = 45;

    /** @var array<string, array{d2c: float, rto: float, share: float}> */
    private const STATES = [
        'Maharashtra' => ['d2c' => 0.35, 'rto' => 0.07, 'share' => 0.17],
        'Karnataka' => ['d2c' => 0.42, 'rto' => 0.06, 'share' => 0.13],
        'Delhi' => ['d2c' => 0.38, 'rto' => 0.11, 'share' => 0.11],
        'Tamil Nadu' => ['d2c' => 0.33, 'rto' => 0.08, 'share' => 0.09],
        'Uttar Pradesh' => ['d2c' => 0.18, 'rto' => 0.27, 'share' => 0.10],
        'Gujarat' => ['d2c' => 0.30, 'rto' => 0.09, 'share' => 0.07],
        'Telangana' => ['d2c' => 0.36, 'rto' => 0.08, 'share' => 0.07],
        'West Bengal' => ['d2c' => 0.22, 'rto' => 0.24, 'share' => 0.06],
        'Rajasthan' => ['d2c' => 0.20, 'rto' => 0.19, 'share' => 0.05],
        'Haryana' => ['d2c' => 0.31, 'rto' => 0.13, 'share' => 0.05],
        'Kerala' => ['d2c' => 0.34, 'rto' => 0.07, 'share' => 0.04],
        'Punjab' => ['d2c' => 0.26, 'rto' => 0.16, 'share' => 0.03],
        'Madhya Pradesh' => ['d2c' => 0.19, 'rto' => 0.21, 'share' => 0.03],
        'Bihar' => ['d2c' => 0.12, 'rto' => 0.34, 'share' => 0.03],
        'Andhra Pradesh' => ['d2c' => 0.28, 'rto' => 0.12, 'share' => 0.03],
    ];

    /** @var array<string, array{city: string, pin: string}> */
    private const CITIES = [
        'Maharashtra' => ['city' => 'Mumbai', 'pin' => '400001'],
        'Karnataka' => ['city' => 'Bengaluru', 'pin' => '560001'],
        'Delhi' => ['city' => 'New Delhi', 'pin' => '110001'],
        'Tamil Nadu' => ['city' => 'Chennai', 'pin' => '600001'],
        'Uttar Pradesh' => ['city' => 'Lucknow', 'pin' => '226001'],
        'Gujarat' => ['city' => 'Ahmedabad', 'pin' => '380001'],
        'Telangana' => ['city' => 'Hyderabad', 'pin' => '500001'],
        'West Bengal' => ['city' => 'Kolkata', 'pin' => '700001'],
        'Rajasthan' => ['city' => 'Jaipur', 'pin' => '302001'],
        'Haryana' => ['city' => 'Gurugram', 'pin' => '122001'],
        'Kerala' => ['city' => 'Kochi', 'pin' => '682001'],
        'Punjab' => ['city' => 'Ludhiana', 'pin' => '141001'],
        'Madhya Pradesh' => ['city' => 'Indore', 'pin' => '452001'],
        'Bihar' => ['city' => 'Patna', 'pin' => '800001'],
        'Andhra Pradesh' => ['city' => 'Visakhapatnam', 'pin' => '530001'],
    ];

    /** @var list<array{code: string, name: string, type: string, share: float, commission: float, color: string, cod: float}> */
    private const CHANNELS = [
        ['code' => 'shopify', 'name' => 'Shopify (D2C)', 'type' => 'd2c', 'share' => 0.42, 'commission' => 0.0, 'color' => '#16a34a', 'cod' => 0.34],
        ['code' => 'amazon', 'name' => 'Amazon', 'type' => 'marketplace', 'share' => 0.23, 'commission' => 0.155, 'color' => '#ff9900', 'cod' => 0.48],
        ['code' => 'flipkart', 'name' => 'Flipkart', 'type' => 'marketplace', 'share' => 0.16, 'commission' => 0.175, 'color' => '#2874f0', 'cod' => 0.58],
        ['code' => 'myntra', 'name' => 'Myntra', 'type' => 'marketplace', 'share' => 0.12, 'commission' => 0.225, 'color' => '#ff3f6c', 'cod' => 0.41],
        ['code' => 'meesho', 'name' => 'Meesho', 'type' => 'marketplace', 'share' => 0.07, 'commission' => 0.24, 'color' => '#570d63', 'cod' => 0.71],
    ];

    /** @var list<array{name: string, category: string, mrp: int, price: int, cost: int, gst: int|float, hsn: string}> */
    private const CATALOG = [
        ['name' => 'Cotton Kurta Set — Indigo', 'category' => 'Ethnic Wear', 'mrp' => 3499, 'price' => 2299, 'cost' => 780, 'gst' => 5, 'hsn' => '6204'],
        ['name' => 'Cotton Kurta Set — Ivory', 'category' => 'Ethnic Wear', 'mrp' => 3499, 'price' => 2299, 'cost' => 780, 'gst' => 5, 'hsn' => '6204'],
        ['name' => 'Chikankari Anarkali', 'category' => 'Ethnic Wear', 'mrp' => 5999, 'price' => 4199, 'cost' => 1650, 'gst' => 12, 'hsn' => '6204'],
        ['name' => 'Bandhani Dupatta', 'category' => 'Ethnic Wear', 'mrp' => 1499, 'price' => 899, 'cost' => 310, 'gst' => 5, 'hsn' => '6214'],
        ['name' => 'Linen Co-ord Set', 'category' => 'Western Wear', 'mrp' => 4499, 'price' => 3199, 'cost' => 1290, 'gst' => 12, 'hsn' => '6204'],
        ['name' => 'Oversized Cotton Shirt', 'category' => 'Western Wear', 'mrp' => 2299, 'price' => 1599, 'cost' => 545, 'gst' => 5, 'hsn' => '6206'],
        ['name' => 'High-Rise Straight Jeans', 'category' => 'Western Wear', 'mrp' => 3299, 'price' => 2399, 'cost' => 980, 'gst' => 12, 'hsn' => '6204'],
        ['name' => 'Ribbed Knit Top', 'category' => 'Western Wear', 'mrp' => 1799, 'price' => 1199, 'cost' => 420, 'gst' => 5, 'hsn' => '6109'],
        ['name' => 'Vitamin C Face Serum 30ml', 'category' => 'Skincare', 'mrp' => 1299, 'price' => 899, 'cost' => 215, 'gst' => 18, 'hsn' => '3304'],
        ['name' => 'Niacinamide Serum 30ml', 'category' => 'Skincare', 'mrp' => 999, 'price' => 699, 'cost' => 178, 'gst' => 18, 'hsn' => '3304'],
        ['name' => 'Ubtan Face Wash 150ml', 'category' => 'Skincare', 'mrp' => 549, 'price' => 399, 'cost' => 96, 'gst' => 18, 'hsn' => '3401'],
        ['name' => 'SPF 50 Sunscreen Gel', 'category' => 'Skincare', 'mrp' => 899, 'price' => 649, 'cost' => 190, 'gst' => 18, 'hsn' => '3304'],
        ['name' => 'Kumkumadi Face Oil 15ml', 'category' => 'Skincare', 'mrp' => 1899, 'price' => 1399, 'cost' => 385, 'gst' => 18, 'hsn' => '3304'],
        ['name' => 'Onion Hair Oil 200ml', 'category' => 'Haircare', 'mrp' => 699, 'price' => 449, 'cost' => 128, 'gst' => 18, 'hsn' => '3305'],
        ['name' => 'Rosemary Scalp Serum', 'category' => 'Haircare', 'mrp' => 1199, 'price' => 849, 'cost' => 240, 'gst' => 18, 'hsn' => '3305'],
        ['name' => 'Sulphate-Free Shampoo 300ml', 'category' => 'Haircare', 'mrp' => 799, 'price' => 599, 'cost' => 165, 'gst' => 18, 'hsn' => '3305'],
        ['name' => 'Silk Pillowcase', 'category' => 'Home', 'mrp' => 2499, 'price' => 1799, 'cost' => 690, 'gst' => 12, 'hsn' => '6302'],
        ['name' => 'Handblock Bedsheet Set', 'category' => 'Home', 'mrp' => 4999, 'price' => 3499, 'cost' => 1480, 'gst' => 12, 'hsn' => '6302'],
        ['name' => 'Ceramic Diffuser', 'category' => 'Home', 'mrp' => 1999, 'price' => 1499, 'cost' => 620, 'gst' => 18, 'hsn' => '6912'],
        ['name' => 'Soy Wax Candle — Oudh', 'category' => 'Home', 'mrp' => 899, 'price' => 649, 'cost' => 245, 'gst' => 18, 'hsn' => '3406'],
        ['name' => 'Leather Sling Bag', 'category' => 'Accessories', 'mrp' => 3999, 'price' => 2799, 'cost' => 1340, 'gst' => 18, 'hsn' => '4202'],
        ['name' => 'Oxidised Silver Jhumkas', 'category' => 'Accessories', 'mrp' => 1299, 'price' => 899, 'cost' => 380, 'gst' => 3, 'hsn' => '7113'],
        ['name' => 'Kolhapuri Chappals', 'category' => 'Footwear', 'mrp' => 2299, 'price' => 1699, 'cost' => 810, 'gst' => 12, 'hsn' => '6403'],
        ['name' => 'Everyday Canvas Sneakers', 'category' => 'Footwear', 'mrp' => 2999, 'price' => 2199, 'cost' => 1180, 'gst' => 12, 'hsn' => '6404'],
    ];

    /** @var list<string> */
    private const VARIANTS = ['XS', 'S', 'M', 'L', 'XL'];

    /** @var list<array{code: string, text: string, weight: float}> */
    private const RETURN_REASONS = [
        ['code' => 'size_issue', 'text' => 'Size did not fit', 'weight' => 0.31],
        ['code' => 'not_as_described', 'text' => 'Product looked different from photos', 'weight' => 0.19],
        ['code' => 'quality_issue', 'text' => 'Quality below expectation', 'weight' => 0.15],
        ['code' => 'damaged_in_transit', 'text' => 'Arrived damaged', 'weight' => 0.11],
        ['code' => 'wrong_item', 'text' => 'Wrong item shipped', 'weight' => 0.08],
        ['code' => 'late_delivery', 'text' => 'Delivered too late', 'weight' => 0.08],
        ['code' => 'changed_mind', 'text' => 'Changed my mind', 'weight' => 0.08],
    ];

    /** @var list<string> */
    private const COURIERS = ['Delhivery', 'Bluedart', 'Xpressbees', 'Ecom Express', 'Shadowfax'];

    private CarbonImmutable $today;

    public function run(?Tenant $tenant = null): void
    {
        $tenant ??= Tenant::query()->where('is_demo', true)->firstOrFail();
        $this->today = CarbonImmutable::now($tenant->timezone)->startOfDay();

        app(TenantContext::class)->runAs($tenant, function () use ($tenant): void {
            mt_srand(20260901 + $tenant->id);

            $this->command?->info('  · settings & benchmarks');
            $this->seedSettings($tenant);

            $this->command?->info('  · channels');
            $channels = $this->seedChannels($tenant);

            $this->command?->info('  · catalog & inventory');
            $skus = $this->seedCatalog($tenant);

            $this->command?->info('  · customers');
            $customers = $this->seedCustomers($tenant, 900);

            $this->command?->info('  · orders, shipments & returns');
            $this->seedOrders($tenant, $channels, $skus, $customers);

            $this->command?->info('  · ads, analytics & social');
            $this->seedMarketing($tenant);
            $this->seedAnalytics($tenant);
            $this->seedSocial($tenant);

            $this->command?->info('  · reviews');
            $this->seedReviews($tenant, $skus);

            $this->command?->info('  · rollups');
            $this->rebuildRollups($tenant);
        });
    }

    private function seedSettings(Tenant $tenant): void
    {
        CostSetting::query()->updateOrCreate(['tenant_id' => $tenant->id], [
            'cogs_method' => 'sku_cost',
            'packaging_cost' => Money::fromRupees(18),
            'per_order_fixed_cost' => Money::fromRupees(12),
            'cod_charge' => Money::fromRupees(35),
            'return_handling_cost' => Money::fromRupees(95),
            'rto_handling_cost' => Money::fromRupees(140),
            'default_shipping_cost' => Money::fromRupees(78),
            'gateway_fee_pct' => 2.1,
            'gst_mode' => 'inclusive',
            // Rent, salaries and software for a lean team on roughly ₹1.8 Cr of
            // annual revenue. It leaves the demo brand thinly profitable at
            // EBITDA, which is the honest starting point for this product.
            'monthly_fixed_opex' => Money::fromRupees(60000),
        ]);

        Benchmark::query()->updateOrCreate(['tenant_id' => $tenant->id], [
            'target_roas' => 3.2,
            'target_margin_pct' => 26.0,
            'target_repeat_rate' => 24.0,
            'target_cac' => 640,
            'dispatch_sla_days' => 2,
            'delivery_sla_days' => 6,
            'rto_threshold_pct' => 15.0,
            'return_threshold_pct' => 10.0,
            'days_of_cover_threshold' => 14,
            'monthly_revenue_target' => Money::fromRupees(4800000),
        ]);
    }

    /** @return array<string, Channel> */
    private function seedChannels(Tenant $tenant): array
    {
        $channels = [];

        foreach (self::CHANNELS as $config) {
            $channels[$config['code']] = Channel::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $config['code']],
                [
                    'name' => $config['name'],
                    'type' => ChannelType::from($config['type']),
                    'source_connector' => $config['type'] === 'd2c' ? 'shopify' : 'unicommerce',
                    'color' => $config['color'],
                    'is_active' => true,
                ],
            );
        }

        return $channels;
    }

    /** @return list<object> */
    private function seedCatalog(Tenant $tenant): array
    {
        $skus = [];
        $skuIndex = 1;

        foreach (self::CATALOG as $productIndex => $item) {
            $productId = DB::table('products')->insertGetId([
                'tenant_id' => $tenant->id,
                'source' => 'shopify',
                'external_id' => 'demo-p-'.($productIndex + 1),
                'title' => $item['name'],
                'handle' => str($item['name'])->slug()->toString(),
                'category' => $item['category'],
                'brand' => $tenant->name,
                'product_type' => $item['category'],
                'status' => 'active',
                'image_url' => null,
                'tags' => json_encode([$item['category']]),
                'published_at' => $this->today->subDays(200),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $needsSizes = in_array($item['category'], ['Ethnic Wear', 'Western Wear', 'Footwear'], true);
            $variants = $needsSizes ? array_slice(self::VARIANTS, 0, 2) : [null];

            foreach ($variants as $variant) {
                $code = sprintf('%s-%03d', strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $item['category']) ?? 'SKU', 0, 3)), $skuIndex);
                // A couple of SKUs are deliberately priced near cost so the
                // loss-making widgets have something honest to show.
                $costMultiplier = $skuIndex % 11 === 0 ? 1.72 : 1.0;

                $skuId = DB::table('skus')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'product_id' => $productId,
                    'source' => 'shopify',
                    'external_id' => 'demo-v-'.$skuIndex,
                    'sku_code' => $code,
                    'name' => $item['name'].($variant !== null ? ' — '.$variant : ''),
                    'variant_title' => $variant,
                    'category' => $item['category'],
                    'brand' => $tenant->name,
                    'mrp' => Money::fromRupees($item['mrp']),
                    'selling_price' => Money::fromRupees($item['price']),
                    'cost_price' => (int) round(Money::fromRupees($item['cost']) * $costMultiplier),
                    'weight_grams' => $this->rand(150, 900),
                    'hsn' => $item['hsn'],
                    'gst_rate' => $item['gst'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('sku_cost_history')->insert([
                    'tenant_id' => $tenant->id,
                    'sku_id' => $skuId,
                    'cost_price' => (int) round(Money::fromRupees($item['cost']) * $costMultiplier),
                    'effective_from' => $this->today->subDays(self::DAYS + 30)->toDateString(),
                    'note' => 'Opening cost',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 20% of SKUs are out of stock or nearly so, and a handful have
                // never sold — that is what the dead-stock widget exists for.
                $stock = match (true) {
                    $skuIndex % 13 === 0 => 0,
                    $skuIndex % 7 === 0 => $this->rand(1, 9),
                    default => $this->rand(40, 420),
                };

                DB::table('inventory')->insert([
                    'tenant_id' => $tenant->id,
                    'sku_id' => $skuId,
                    'location_id' => null,
                    'source' => 'shopify',
                    'on_hand' => $stock,
                    'reserved' => 0,
                    'available' => $stock,
                    'synced_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $skus[] = (object) [
                    'id' => $skuId,
                    'code' => $code,
                    'name' => $item['name'].($variant !== null ? ' — '.$variant : ''),
                    'category' => $item['category'],
                    'price' => Money::fromRupees($item['price']),
                    'mrp' => Money::fromRupees($item['mrp']),
                    'cost' => (int) round(Money::fromRupees($item['cost']) * $costMultiplier),
                    'gst' => $item['gst'],
                    // Popularity is heavily skewed so the Pareto widget is real.
                    'weight' => $skuIndex <= 8 ? 4.0 : ($skuIndex <= 20 ? 1.6 : ($skuIndex % 13 === 0 ? 0.0 : 0.45)),
                ];

                $skuIndex++;
            }
        }

        return $skus;
    }

    /** @return list<int> */
    private function seedCustomers(Tenant $tenant, int $count): array
    {
        $first = ['Aditi', 'Rohan', 'Priya', 'Karan', 'Sneha', 'Vikram', 'Ananya', 'Arjun', 'Meera', 'Rahul', 'Divya', 'Nikhil', 'Ishita', 'Sanjay', 'Kavya'];
        $last = ['Sharma', 'Iyer', 'Patel', 'Reddy', 'Nair', 'Gupta', 'Menon', 'Joshi', 'Rao', 'Verma', 'Banerjee', 'Kulkarni'];
        $ids = [];
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $state = $this->weightedState();
            $name = $first[$this->rand(0, count($first) - 1)].' '.$last[$this->rand(0, count($last) - 1)];
            $email = str($name)->slug('.')->toString().$i.'@example.com';

            $rows[] = [
                'tenant_id' => $tenant->id,
                'source' => 'shopify',
                'external_id' => 'demo-c-'.$i,
                'email_hash' => Customer::hashIdentity($email),
                'masked_email' => Customer::maskEmail($email),
                // This row is bulk-inserted, so the model's `encrypted` cast
                // never runs. encryptString matches what that cast reads back;
                // encrypt() would serialise first and come back as PHP soup.
                'email_encrypted' => Crypt::encryptString($email),
                'phone_hash' => Customer::hashIdentity('9'.$this->rand(100000000, 999999999)),
                'masked_phone' => '******'.$this->rand(1000, 9999),
                'name' => $name,
                'city' => self::CITIES[$state]['city'],
                'state' => $state,
                'pincode' => self::CITIES[$state]['pin'],
                'accepts_marketing' => $this->rand(0, 100) < 62,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('customers')->insert($chunk);
        }

        return DB::table('customers')->where('tenant_id', $tenant->id)->pluck('id')->all();
    }

    /**
     * @param  array<string, Channel>  $channels
     * @param  list<object>  $skus
     * @param  list<int>  $customerIds
     */
    private function seedOrders(Tenant $tenant, array $channels, array $skus, array $customerIds): void
    {
        $economics = app(ComputeOrderEconomics::class);
        $skuPool = $this->weightedPool($skus);
        $orderNumber = 1001;
        $seenCustomers = [];
        $orderIds = [];

        for ($dayOffset = self::DAYS; $dayOffset >= 0; $dayOffset--) {
            $date = $this->today->subDays($dayOffset);
            $ordersToday = $this->ordersForDay($date, $dayOffset);

            for ($n = 0; $n < $ordersToday; $n++) {
                $channelConfig = $this->weightedChannel();
                $channel = $channels[$channelConfig['code']];
                $state = $this->weightedState();
                $stateConfig = self::STATES[$state];

                $isCod = $this->chance($channelConfig['cod']);
                $placedAt = $date->addHours($this->rand(8, 22))->addMinutes($this->rand(0, 59));

                $customerId = $channelConfig['type'] === 'd2c'
                    ? $customerIds[$this->rand(0, count($customerIds) - 1)]
                    : null;

                $isFirstOrder = $customerId !== null && ! isset($seenCustomers[$customerId]);
                if ($customerId !== null) {
                    $seenCustomers[$customerId] = true;
                }

                $status = $this->resolveStatus($isCod, $stateConfig['rto'], $dayOffset);

                $orderId = DB::table('orders')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'channel_id' => $channel->id,
                    'customer_id' => $customerId,
                    'source' => $channelConfig['type'] === 'd2c' ? 'shopify' : 'unicommerce',
                    'external_id' => 'demo-o-'.$orderNumber,
                    'order_number' => '#'.$orderNumber,
                    'placed_at' => $placedAt->setTimezone('UTC'),
                    'invoiced_at' => $status === OrderStatus::Cancelled ? null : $placedAt->addHours(2)->setTimezone('UTC'),
                    'cancelled_at' => $status === OrderStatus::Cancelled ? $placedAt->addHours(6)->setTimezone('UTC') : null,
                    'delivered_at' => $status === OrderStatus::Delivered ? $placedAt->addDays($this->rand(2, 7))->setTimezone('UTC') : null,
                    'status' => $status->value,
                    'fulfillment_status' => $status === OrderStatus::Delivered ? 'fulfilled' : 'unfulfilled',
                    'payment_mode' => $isCod ? PaymentMode::Cod->value : PaymentMode::Prepaid->value,
                    'shipping_state' => $state,
                    'shipping_city' => self::CITIES[$state]['city'],
                    'shipping_pincode' => self::CITIES[$state]['pin'],
                    'currency' => 'INR',
                    'is_first_order' => $isFirstOrder,
                    'is_rto' => $status === OrderStatus::Rto,
                    'shipping_amount' => $this->chance(0.3) ? Money::fromRupees(79) : 0,
                    'tax_amount' => 0,
                    'discount_codes' => $this->chance(0.34) ? $this->discountCode() : null,
                    'utm_source' => $this->utmSource(),
                    'utm_medium' => $this->chance(0.55) ? 'cpc' : 'organic',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $orderIds[] = $orderId;
                $this->seedOrderItems($tenant, $orderId, $skuPool, $status);
                $this->seedFees($tenant, $orderId, $channel, $channelConfig);
                $this->seedShipment($tenant, $orderId, $channel, $state, $isCod, $status, $placedAt);
                $this->seedReturnIfAny($tenant, $orderId, $channel, $state, $status, $placedAt);
                $this->seedTransaction($tenant, $orderId, $isCod, $status, $placedAt);

                $orderNumber++;
            }
        }

        $this->command?->info('    → '.count($orderIds).' orders; resolving economics');

        Order::query()->whereIn('id', $orderIds)->chunkById(200, function ($orders) use ($economics): void {
            foreach ($orders as $order) {
                $economics->handle($order);
            }
        });
    }

    /** @param list<object> $skuPool */
    private function seedOrderItems(Tenant $tenant, int $orderId, array $skuPool, OrderStatus $status): void
    {
        $lineCount = $this->chance(0.62) ? 1 : ($this->chance(0.75) ? 2 : 3);
        $used = [];

        for ($i = 0; $i < $lineCount; $i++) {
            $sku = $skuPool[$this->rand(0, count($skuPool) - 1)];

            if (isset($used[$sku->id])) {
                continue;
            }
            $used[$sku->id] = true;

            $qty = $this->chance(0.82) ? 1 : $this->rand(2, 3);
            $discountPct = match (true) {
                $this->chance(0.18) => $this->rand(20, 40),
                $this->chance(0.40) => $this->rand(5, 15),
                default => 0,
            };
            $lineGross = $sku->price * $qty;
            $discount = (int) round($lineGross * $discountPct / 100);

            DB::table('order_items')->insert([
                'tenant_id' => $tenant->id,
                'order_id' => $orderId,
                'sku_id' => $sku->id,
                'external_id' => 'demo-li-'.$orderId.'-'.$i,
                'sku_code' => $sku->code,
                'title' => $sku->name,
                'qty' => $qty,
                'unit_price' => $sku->price,
                'discount' => $discount,
                'tax' => (int) round(($lineGross - $discount) * $sku->gst / (100 + $sku->gst)),
                'cogs_unit' => $sku->cost,
                'status' => $status->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array{commission: float, type: string} $config */
    private function seedFees(Tenant $tenant, int $orderId, Channel $channel, array $config): void
    {
        if ($config['commission'] <= 0) {
            return;
        }

        $gross = (int) DB::table('order_items')
            ->where('order_id', $orderId)
            ->selectRaw('COALESCE(SUM(unit_price * qty - discount), 0) AS total')
            ->value('total');

        $feeDate = DB::table('orders')->where('id', $orderId)->value('placed_at');

        $fees = [
            ['fee_type' => 'commission', 'amount' => (int) round($gross * $config['commission'])],
            ['fee_type' => 'fixed', 'amount' => Money::fromRupees($this->rand(15, 40))],
            ['fee_type' => 'shipping', 'amount' => Money::fromRupees($this->rand(45, 95))],
            ['fee_type' => 'collection', 'amount' => (int) round($gross * 0.012)],
        ];

        foreach ($fees as $fee) {
            DB::table('marketplace_fees')->insert([
                'tenant_id' => $tenant->id,
                'order_id' => $orderId,
                'channel_id' => $channel->id,
                'fee_type' => $fee['fee_type'],
                'amount' => $fee['amount'],
                'fee_date' => substr((string) $feeDate, 0, 10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedShipment(Tenant $tenant, int $orderId, Channel $channel, string $state, bool $isCod, OrderStatus $status, CarbonImmutable $placedAt): void
    {
        if ($status === OrderStatus::Cancelled) {
            return;
        }

        $shipmentStatus = match ($status) {
            OrderStatus::Delivered, OrderStatus::Returned => ShipmentStatus::Delivered,
            OrderStatus::Rto => ShipmentStatus::RtoDelivered,
            OrderStatus::Shipped => ShipmentStatus::InTransit,
            default => ShipmentStatus::Manifested,
        };

        $dispatchedAt = $placedAt->addDays($this->rand(0, 3));
        $transitDays = $this->rand(2, 9);

        DB::table('shipments')->insert([
            'tenant_id' => $tenant->id,
            'order_id' => $orderId,
            'channel_id' => $channel->id,
            'source' => 'ithink',
            'external_id' => 'demo-s-'.$orderId,
            'courier' => self::COURIERS[$this->rand(0, count(self::COURIERS) - 1)],
            'awb' => (string) $this->rand(100000000000, 999999999999),
            'status' => $shipmentStatus->value,
            'payment_mode' => $isCod ? 'cod' : 'prepaid',
            'destination_state' => $state,
            'destination_city' => self::CITIES[$state]['city'],
            'destination_pincode' => self::CITIES[$state]['pin'],
            'manifested_at' => $placedAt->addHours(6)->setTimezone('UTC'),
            'dispatched_at' => $dispatchedAt->setTimezone('UTC'),
            'delivered_at' => $shipmentStatus === ShipmentStatus::Delivered ? $dispatchedAt->addDays($transitDays)->setTimezone('UTC') : null,
            'promised_at' => $placedAt->addDays(6)->setTimezone('UTC'),
            'attempts' => $status === OrderStatus::Rto ? $this->rand(2, 3) : 1,
            'ndr_reason' => $status === OrderStatus::Rto ? 'Customer not reachable' : null,
            'is_rto' => $status === OrderStatus::Rto,
            'rto_at' => $status === OrderStatus::Rto ? $dispatchedAt->addDays($this->rand(6, 14))->setTimezone('UTC') : null,
            'cod_amount' => 0,
            'shipping_cost' => Money::fromRupees($this->rand(58, 105)),
            'rto_cost' => $status === OrderStatus::Rto ? Money::fromRupees($this->rand(70, 130)) : 0,
            'transit_days' => $shipmentStatus === ShipmentStatus::Delivered ? $transitDays : null,
            'cod_collected_at' => $isCod && $shipmentStatus === ShipmentStatus::Delivered ? $dispatchedAt->addDays($transitDays)->setTimezone('UTC') : null,
            'cod_remitted_at' => $isCod && $shipmentStatus === ShipmentStatus::Delivered && $this->chance(0.72)
                ? $dispatchedAt->addDays($transitDays + $this->rand(5, 14))->setTimezone('UTC')
                : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedReturnIfAny(Tenant $tenant, int $orderId, Channel $channel, string $state, OrderStatus $status, CarbonImmutable $placedAt): void
    {
        if (! in_array($status, [OrderStatus::Returned, OrderStatus::Rto], true)) {
            return;
        }

        $item = DB::table('order_items')->where('order_id', $orderId)->first();

        if ($item === null) {
            return;
        }

        $reason = $status === OrderStatus::Rto
            ? ['code' => 'rto_undelivered', 'text' => 'Undelivered — returned to origin']
            : $this->weightedReason();

        DB::table('returns')->insert([
            'tenant_id' => $tenant->id,
            'order_id' => $orderId,
            'order_item_id' => $item->id,
            'channel_id' => $channel->id,
            'sku_id' => $item->sku_id,
            'source' => 'demo',
            'external_id' => 'demo-r-'.$orderId,
            'type' => ($status === OrderStatus::Rto ? ReturnType::Rto : ReturnType::CustomerReturn)->value,
            'reason_code' => $reason['code'],
            'reason_text' => $reason['text'],
            'qty' => $item->qty,
            'initiated_at' => $placedAt->addDays($this->rand(4, 18))->setTimezone('UTC'),
            'received_at' => $placedAt->addDays($this->rand(8, 26))->setTimezone('UTC'),
            'refund_amount' => $status === OrderStatus::Rto ? 0 : ($item->unit_price * $item->qty - $item->discount),
            'loss_amount' => Money::fromRupees($this->rand(60, 180)),
            'restock' => $this->chance(0.6),
            'shipping_state' => $state,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($status === OrderStatus::Returned) {
            DB::table('order_items')->where('id', $item->id)->update(['returned_qty' => $item->qty]);
        }
    }

    private function seedTransaction(Tenant $tenant, int $orderId, bool $isCod, OrderStatus $status, CarbonImmutable $placedAt): void
    {
        if ($isCod || $status === OrderStatus::Cancelled) {
            return;
        }

        $amount = (int) DB::table('order_items')
            ->where('order_id', $orderId)
            ->selectRaw('COALESCE(SUM(unit_price * qty - discount), 0) AS total')
            ->value('total');

        $gateways = [['Razorpay', 'UPI'], ['Razorpay', 'Credit Card'], ['Cashfree', 'Net Banking'], ['Razorpay', 'Wallet']];
        [$gateway, $method] = $gateways[$this->rand(0, count($gateways) - 1)];

        DB::table('transactions')->insert([
            'tenant_id' => $tenant->id,
            'order_id' => $orderId,
            'source' => 'demo',
            'external_id' => 'demo-t-'.$orderId,
            'gateway' => $gateway,
            'method' => $method,
            'kind' => 'sale',
            'amount' => $amount,
            'fee' => (int) round($amount * 0.021),
            'status' => $this->chance(0.97) ? 'success' : 'failed',
            'failure_reason' => null,
            'processed_at' => $placedAt->setTimezone('UTC'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($status === OrderStatus::Returned) {
            DB::table('transactions')->insert([
                'tenant_id' => $tenant->id,
                'order_id' => $orderId,
                'source' => 'demo',
                'external_id' => 'demo-t-refund-'.$orderId,
                'gateway' => $gateway,
                'method' => $method,
                'kind' => 'refund',
                'amount' => -$amount,
                'fee' => 0,
                'status' => 'success',
                'processed_at' => $placedAt->addDays($this->rand(10, 22))->setTimezone('UTC'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedMarketing(Tenant $tenant): void
    {
        $platforms = [
            'meta' => [
                'account' => 'Meta Ads — '.$tenant->name,
                'campaigns' => [
                    ['name' => 'Prospecting | Broad | Skincare', 'objective' => 'conversions', 'roas' => 2.1, 'budget' => 2890],
                    ['name' => 'Retargeting | ATC 14d', 'objective' => 'conversions', 'roas' => 6.4, 'budget' => 1020],
                    ['name' => 'Prospecting | Lookalike 1% | Ethnic', 'objective' => 'conversions', 'roas' => 3.6, 'budget' => 2295],
                    ['name' => 'Reels | Awareness', 'objective' => 'reach', 'roas' => 0.7, 'budget' => 799],
                    ['name' => 'Catalogue | DPA', 'objective' => 'catalog_sales', 'roas' => 4.9, 'budget' => 1496],
                ],
            ],
            'google_ads' => [
                'account' => 'Google Ads — '.$tenant->name,
                'campaigns' => [
                    ['name' => 'Brand | Exact', 'objective' => 'search', 'roas' => 11.2, 'budget' => 612],
                    ['name' => 'PMax | All Products', 'objective' => 'performance_max', 'roas' => 3.1, 'budget' => 2125],
                    ['name' => 'Shopping | Skincare', 'objective' => 'shopping', 'roas' => 2.4, 'budget' => 1156],
                    ['name' => 'Search | Non-brand Generic', 'objective' => 'search', 'roas' => 1.2, 'budget' => 935],
                ],
            ],
        ];

        foreach ($platforms as $platform => $config) {
            $accountId = DB::table('ad_accounts')->insertGetId([
                'tenant_id' => $tenant->id,
                'platform' => $platform,
                'external_id' => 'demo-acct-'.$platform,
                'name' => $config['account'],
                'currency' => 'INR',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($config['campaigns'] as $index => $campaign) {
                $campaignId = DB::table('campaigns')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'ad_account_id' => $accountId,
                    'platform' => $platform,
                    'external_id' => 'demo-camp-'.$platform.'-'.$index,
                    'name' => $campaign['name'],
                    'objective' => $campaign['objective'],
                    'status' => 'active',
                    'daily_budget' => Money::fromRupees($campaign['budget']),
                    'started_at' => $this->today->subDays(self::ADS_DAYS + 20),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->seedAdInsights($tenant, $platform, $campaignId, $campaign);
            }
        }
    }

    /** @param array{roas: float, budget: int} $campaign */
    private function seedAdInsights(Tenant $tenant, string $platform, int $campaignId, array $campaign): void
    {
        $rows = [];
        $breakdownRows = [];

        for ($dayOffset = self::ADS_DAYS; $dayOffset >= 0; $dayOffset--) {
            $date = $this->today->subDays($dayOffset)->toDateString();
            $spend = Money::fromRupees($campaign['budget'] * $this->float(0.72, 1.15));
            $roas = $campaign['roas'] * $this->float(0.7, 1.35);
            // One deliberate spend spike so the anomaly detector has something real to find.
            if ($dayOffset === 9) {
                $spend = (int) round($spend * 2.6);
                $roas *= 0.45;
            }

            $impressions = (int) round($spend / 100 * $this->float(28, 46));
            $clicks = (int) round($impressions * $this->float(0.008, 0.021));
            $conversionValue = (int) round($spend * $roas);
            $conversions = max(0, (int) round($conversionValue / Money::fromRupees($this->rand(1400, 2600))));

            $rows[] = $this->insightRow($tenant, $date, $platform, $campaignId, 'total', [
                'spend' => $spend, 'impressions' => $impressions, 'clicks' => $clicks,
                'reach' => (int) round($impressions * 0.62),
                'conversions' => $conversions, 'conversion_value' => $conversionValue,
                'add_to_carts' => (int) round($clicks * $this->float(0.10, 0.22)),
                'checkouts' => (int) round($clicks * $this->float(0.04, 0.09)),
                'video_views_3s' => $platform === 'meta' ? (int) round($impressions * 0.31) : 0,
            ]);

            if ($platform === 'meta' && $dayOffset % 3 === 0) {
                foreach ([['18-24', 'female'], ['25-34', 'female'], ['25-34', 'male'], ['35-44', 'female'], ['45-54', 'female']] as [$age, $gender]) {
                    $share = $this->float(0.08, 0.32);
                    $breakdownRows[] = $this->insightRow($tenant, $date, $platform, $campaignId, 'demographic', [
                        'age' => $age, 'gender' => $gender,
                        'spend' => (int) round($spend * $share),
                        'impressions' => (int) round($impressions * $share),
                        'clicks' => (int) round($clicks * $share),
                        'conversions' => (int) round($conversions * $share),
                        'conversion_value' => (int) round($conversionValue * $share),
                    ]);
                }

                foreach (['facebook feed', 'instagram feed', 'instagram reels', 'instagram stories', 'audience network'] as $placement) {
                    $share = $this->float(0.06, 0.30);
                    $breakdownRows[] = $this->insightRow($tenant, $date, $platform, $campaignId, 'placement', [
                        'placement' => $placement,
                        'spend' => (int) round($spend * $share),
                        'impressions' => (int) round($impressions * $share),
                        'clicks' => (int) round($clicks * $share),
                        'conversions' => (int) round($conversions * $share),
                        'conversion_value' => (int) round($conversionValue * $share),
                    ]);
                }
            }
        }

        foreach (array_chunk([...$rows, ...$breakdownRows], 300) as $chunk) {
            DB::table('ad_insights_daily')->insert($chunk);
        }
    }

    /**
     * Bulk inserts need a uniform column list, so every insight row is built
     * from the same shape regardless of which breakdown it belongs to.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function insightRow(Tenant $tenant, string $date, string $platform, int $campaignId, string $breakdown, array $values): array
    {
        return [
            'tenant_id' => $tenant->id,
            'date' => $date,
            'platform' => $platform,
            'campaign_id' => $campaignId,
            'ad_set_id' => null,
            'ad_id' => null,
            'breakdown_key' => $breakdown,
            'placement' => null,
            'age' => null,
            'gender' => null,
            'country' => null,
            'state' => null,
            'spend' => 0,
            'impressions' => 0,
            'clicks' => 0,
            'reach' => 0,
            'conversions' => 0,
            'conversion_value' => 0,
            'add_to_carts' => 0,
            'checkouts' => 0,
            'video_views_3s' => 0,
            'video_views_thruplay' => 0,
            ...$values,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function seedAnalytics(Tenant $tenant): void
    {
        $channelGroups = [
            ['Paid Social', 'facebook', 'cpc', 0.29],
            ['Paid Search', 'google', 'cpc', 0.18],
            ['Organic Search', 'google', 'organic', 0.16],
            ['Direct', '(direct)', '(none)', 0.19],
            ['Organic Social', 'instagram', 'referral', 0.11],
            ['Email', 'klaviyo', 'email', 0.07],
        ];

        $rows = [];
        $cityRows = [];
        $pageRows = [];
        $demoRows = [];

        for ($dayOffset = self::ADS_DAYS; $dayOffset >= 0; $dayOffset--) {
            $date = $this->today->subDays($dayOffset)->toDateString();
            $daySessions = $this->rand(2400, 5200);

            foreach ($channelGroups as [$group, $source, $medium, $share]) {
                $sessions = (int) round($daySessions * $share * $this->float(0.8, 1.2));
                $users = (int) round($sessions * 0.86);
                $atc = (int) round($sessions * $this->float(0.055, 0.11));
                $checkouts = (int) round($atc * $this->float(0.34, 0.52));
                $purchases = (int) round($checkouts * $this->float(0.42, 0.66));

                $rows[] = [
                    'tenant_id' => $tenant->id, 'date' => $date, 'channel_group' => $group,
                    'source' => $source, 'medium' => $medium,
                    'campaign' => $medium === 'cpc' ? 'demo_campaign' : null,
                    'sessions' => $sessions, 'users' => $users,
                    'new_users' => (int) round($users * $this->float(0.55, 0.78)),
                    'engaged_sessions' => (int) round($sessions * $this->float(0.48, 0.68)),
                    'bounce_rate' => round($this->float(28, 52), 3),
                    'item_views' => (int) round($sessions * $this->float(1.4, 2.6)),
                    'add_to_carts' => $atc, 'checkouts' => $checkouts, 'purchases' => $purchases,
                    'revenue' => Money::fromRupees($purchases * $this->rand(1500, 2600)),
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }

            foreach (array_slice(array_keys(self::STATES), 0, 8) as $state) {
                $cityRows[] = [
                    'tenant_id' => $tenant->id, 'date' => $date,
                    'city' => self::CITIES[$state]['city'], 'region' => $state, 'country' => 'India',
                    'sessions' => (int) round($daySessions * self::STATES[$state]['share'] * $this->float(0.8, 1.2)),
                    'users' => (int) round($daySessions * self::STATES[$state]['share'] * 0.85),
                    'purchases' => $this->rand(2, 24),
                    'revenue' => Money::fromRupees($this->rand(4000, 52000)),
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }

            foreach ([
                ['/', 'Home'], ['/collections/all', 'Shop All'], ['/collections/skincare', 'Skincare'],
                ['/products/vitamin-c-face-serum-30ml', 'Vitamin C Face Serum'], ['/cart', 'Cart'], ['/checkout', 'Checkout'],
            ] as [$path, $title]) {
                $views = $this->rand(280, 4200);
                $pageRows[] = [
                    'tenant_id' => $tenant->id, 'date' => $date, 'page_path' => $path, 'page_title' => $title,
                    'views' => $views, 'users' => (int) round($views * 0.72),
                    'events' => (int) round($views * $this->float(1.8, 3.4)),
                    'avg_time_seconds' => round($this->float(22, 145), 2),
                    'bounce_rate' => round($this->float(22, 58), 3),
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }

            foreach ([['18-24', 'female'], ['25-34', 'female'], ['25-34', 'male'], ['35-44', 'female'], ['45-54', 'female'], ['55-64', 'female']] as [$age, $gender]) {
                $sessions = (int) round($daySessions * $this->float(0.05, 0.28));
                $demoRows[] = [
                    'tenant_id' => $tenant->id, 'date' => $date, 'age' => $age, 'gender' => $gender,
                    'sessions' => $sessions, 'users' => (int) round($sessions * 0.85),
                    'purchases' => $this->rand(0, 18),
                    'revenue' => Money::fromRupees($this->rand(0, 42000)),
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }

        foreach ([['analytics_daily', $rows], ['analytics_cities', $cityRows], ['analytics_pages', $pageRows], ['analytics_demographics', $demoRows]] as [$table, $data]) {
            foreach (array_chunk($data, 300) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }

        DB::table('analytics_realtime')->insert([
            'tenant_id' => $tenant->id,
            'captured_at' => now(),
            'active_users' => $this->rand(38, 190),
            'by_country' => json_encode(['India' => $this->rand(30, 170), 'United States' => $this->rand(1, 9), 'UAE' => $this->rand(1, 6)]),
            'by_page' => json_encode(['/' => $this->rand(8, 40), '/collections/all' => $this->rand(4, 30), '/checkout' => $this->rand(1, 12)]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedAbandonedCheckouts($tenant);
    }

    private function seedAbandonedCheckouts(Tenant $tenant): void
    {
        $rows = [];

        for ($i = 1; $i <= 180; $i++) {
            $abandonedAt = $this->today->subDays($this->rand(0, 30))->addHours($this->rand(0, 23));
            $recovered = $this->chance(0.13);

            $rows[] = [
                'tenant_id' => $tenant->id,
                'source' => 'shopify',
                'external_id' => 'demo-ac-'.$i,
                'abandoned_at' => $abandonedAt->setTimezone('UTC'),
                'cart_value' => Money::fromRupees($this->rand(699, 8400)),
                'items_count' => $this->rand(1, 4),
                'recovered' => $recovered,
                'recovered_at' => $recovered ? $abandonedAt->addHours($this->rand(2, 48))->setTimezone('UTC') : null,
                'recovery_url' => 'https://example.myshopify.com/checkout/recover/'.$i,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('abandoned_checkouts')->insert($rows);
    }

    private function seedSocial(Tenant $tenant): void
    {
        $accountId = DB::table('social_accounts')->insertGetId([
            'tenant_id' => $tenant->id,
            'platform' => 'instagram',
            'external_id' => 'demo-ig',
            'username' => str($tenant->name)->slug('')->toString(),
            'name' => $tenant->name,
            'followers_count' => 48200,
            'follows_count' => 312,
            'media_count' => 486,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fbId = DB::table('social_accounts')->insertGetId([
            'tenant_id' => $tenant->id,
            'platform' => 'facebook',
            'external_id' => 'demo-fb',
            'name' => $tenant->name,
            'followers_count' => 12400,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $captions = [
            'Three ways to style the indigo kurta set ✨ #ethnicwear #handloom',
            'Behind the scenes at our Jaipur block-printing unit 🪡 #madeinindia',
            'Your 5-step glass skin routine, ranked by our formulators 🧪 #skincare',
            'Restock alert — the sold-out serum is back 💛 #vitaminc',
            'Real results: 6 weeks of the rosemary scalp serum #haircare',
            'Festive edit is live. Free shipping this week only 🎁 #festive',
            'One co-ord, four occasions. Which is your favourite? #ootd',
            'How we source our cotton — and why it costs more #transparency',
        ];

        $rows = [];
        $dailyRows = [];
        $followers = 46800;

        for ($i = 0; $i < 42; $i++) {
            $publishedAt = $this->today->subDays($this->rand(0, self::ADS_DAYS))->addHours($this->rand(9, 21));
            $type = $this->chance(0.52) ? 'reel' : ($this->chance(0.7) ? 'post' : 'carousel');
            $reach = $type === 'reel' ? $this->rand(9000, 142000) : $this->rand(2400, 21000);
            $likes = (int) round($reach * $this->float(0.02, 0.075));
            $comments = (int) round($likes * $this->float(0.02, 0.09));
            $saves = (int) round($reach * $this->float(0.004, 0.028));
            $shares = (int) round($reach * $this->float(0.002, 0.019));

            $rows[] = [
                'tenant_id' => $tenant->id, 'social_account_id' => $accountId, 'platform' => 'instagram',
                'media_id' => 'demo-media-'.$i,
                'type' => $type,
                'caption' => $captions[$i % count($captions)],
                'permalink' => 'https://instagram.com/p/demo'.$i,
                'published_at' => $publishedAt->setTimezone('UTC'),
                'reach' => $reach,
                'impressions' => (int) round($reach * $this->float(1.05, 1.6)),
                'views' => $type === 'reel' ? (int) round($reach * $this->float(1.2, 2.1)) : 0,
                'likes' => $likes, 'comments' => $comments, 'shares' => $shares, 'saves' => $saves,
                'avg_watch_time' => $type === 'reel' ? round($this->float(3.2, 14.8), 2) : 0,
                'engagement_rate' => round(($likes + $comments + $saves + $shares) / max($reach, 1) * 100, 3),
                'hashtags' => json_encode($this->extractHashtags($captions[$i % count($captions)])),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        for ($dayOffset = self::ADS_DAYS; $dayOffset >= 0; $dayOffset--) {
            $followers += $this->rand(-20, 120);
            $date = $this->today->subDays($dayOffset)->toDateString();

            $dailyRows[] = [
                'tenant_id' => $tenant->id, 'social_account_id' => $accountId, 'date' => $date, 'platform' => 'instagram',
                'followers' => $followers, 'new_followers' => $this->rand(-20, 120),
                'reach' => $this->rand(12000, 96000), 'impressions' => $this->rand(18000, 140000),
                'profile_views' => $this->rand(320, 3400), 'website_clicks' => $this->rand(90, 1200),
                'views' => $this->rand(20000, 180000),
                'created_at' => now(), 'updated_at' => now(),
            ];

            $dailyRows[] = [
                'tenant_id' => $tenant->id, 'social_account_id' => $fbId, 'date' => $date, 'platform' => 'facebook',
                'followers' => 12400, 'new_followers' => $this->rand(-5, 30),
                'reach' => $this->rand(1800, 14000), 'impressions' => $this->rand(2400, 19000),
                'profile_views' => $this->rand(40, 480), 'website_clicks' => $this->rand(10, 180),
                'views' => $this->rand(1000, 12000),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        DB::table('social_posts')->insert($rows);
        foreach (array_chunk($dailyRows, 200) as $chunk) {
            DB::table('social_account_daily')->insert($chunk);
        }

        $audience = [];
        foreach (['city' => ['Mumbai', 'Bengaluru', 'Delhi', 'Hyderabad', 'Pune', 'Chennai'],
            'country' => ['India', 'United States', 'UAE', 'United Kingdom'],
            'gender_age' => ['F.18-24', 'F.25-34', 'F.35-44', 'M.25-34', 'M.35-44']] as $dimension => $buckets) {
            foreach ($buckets as $bucket) {
                $audience[] = [
                    'tenant_id' => $tenant->id, 'social_account_id' => $accountId,
                    'date' => $this->today->toDateString(), 'dimension' => $dimension,
                    'bucket' => $bucket, 'value' => $this->rand(400, 14000),
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }

        DB::table('social_audience_daily')->insert($audience);
    }

    /** @param list<object> $skus */
    private function seedReviews(Tenant $tenant, array $skus): void
    {
        $bodies = [
            5 => ['Absolutely love it. Fabric quality is better than the price suggests.', 'Third time ordering. Never disappoints.', 'Shipping was fast and packaging was lovely.'],
            4 => ['Good quality overall, colour is slightly different from the photo.', 'Happy with it, though delivery took a week.', 'Nice product, wish it came in more sizes.'],
            3 => ['It is okay. Not bad, not great for the price.', 'Average. Expected better stitching.'],
            2 => ['Sizing runs small — had to return.', 'Colour faded after two washes.'],
            1 => ['Arrived damaged and support was slow to respond.', 'Completely different from the listing photos.'],
        ];

        $names = ['Aditi S.', 'Rohan M.', 'Priya K.', 'Sneha R.', 'Vikram J.', 'Ananya P.', 'Karan D.', 'Meera N.'];
        $rows = [];

        for ($i = 1; $i <= 260; $i++) {
            $sku = $skus[$this->rand(0, count($skus) - 1)];
            $rating = match (true) {
                $this->chance(0.58) => 5,
                $this->chance(0.55) => 4,
                $this->chance(0.55) => 3,
                $this->chance(0.6) => 2,
                default => 1,
            };

            $rows[] = [
                'tenant_id' => $tenant->id,
                'sku_id' => $sku->id,
                'source' => 'judgeme',
                'external_id' => 'demo-rev-'.$i,
                'product_sku' => $sku->code,
                'rating' => $rating,
                'title' => $rating >= 4 ? 'Great buy' : ($rating === 3 ? 'It is fine' : 'Disappointed'),
                'body' => $bodies[$rating][$this->rand(0, count($bodies[$rating]) - 1)],
                'reviewer' => $names[$this->rand(0, count($names) - 1)],
                'verified' => $this->chance(0.82),
                'has_photos' => $this->chance(0.24),
                // sentiment stays NULL — it is filled by the LLM analysis job, never inferred from stars.
                'sentiment' => null,
                'reviewed_at' => $this->today->subDays($this->rand(0, self::DAYS))->setTimezone('UTC'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('reviews')->insert($chunk);
        }
    }

    private function rebuildRollups(Tenant $tenant): void
    {
        $from = $this->today->subDays(self::DAYS + 1);
        $to = $this->today->endOfDay();

        app(RebuildDailyMetrics::class)->handle($tenant, $from, $to);
        app(RebuildSkuRollup::class)->handle($tenant, $from, $to);
        app(RebuildStateRollup::class)->handle($tenant, $from, $to);
        app(RebuildAdSpendRollup::class)->handle($tenant, $from, $to);
        app(RebuildCustomerMetrics::class)->handle($tenant);
        app(RebuildCohorts::class)->handle($tenant);
        app(RebuildPincodeRisk::class)->handle($tenant);
    }

    private function resolveStatus(bool $isCod, float $stateRto, int $dayOffset): OrderStatus
    {
        // Recent orders have not had time to complete their lifecycle yet.
        if ($dayOffset < 3) {
            return $this->chance(0.55) ? OrderStatus::Confirmed : OrderStatus::Shipped;
        }

        if ($dayOffset < 8) {
            return $this->chance(0.6) ? OrderStatus::Shipped : OrderStatus::Delivered;
        }

        $rtoChance = $isCod ? $stateRto : $stateRto * 0.22;

        return match (true) {
            $this->chance(0.035) => OrderStatus::Cancelled,
            $this->chance($rtoChance) => OrderStatus::Rto,
            $this->chance(0.085) => OrderStatus::Returned,
            default => OrderStatus::Delivered,
        };
    }

    private function ordersForDay(CarbonImmutable $date, int $dayOffset): int
    {
        $base = 9;
        // Weekend lift, plus a slow upward growth trend across the window.
        $weekend = in_array($date->dayOfWeek, [0, 6], true) ? 1.35 : 1.0;
        $growth = 1 + ((self::DAYS - $dayOffset) / self::DAYS) * 0.55;
        // One sale spike so the anomaly and pacing widgets have a real event.
        $spike = $dayOffset === 24 ? 2.8 : 1.0;

        return max(1, (int) round($base * $weekend * $growth * $spike * $this->float(0.75, 1.3)));
    }

    /** @return array{code: string, name: string, type: string, share: float, commission: float, color: string, cod: float} */
    private function weightedChannel(): array
    {
        $roll = $this->float(0, 1);
        $cumulative = 0.0;

        foreach (self::CHANNELS as $channel) {
            $cumulative += $channel['share'];
            if ($roll <= $cumulative) {
                return $channel;
            }
        }

        return self::CHANNELS[0];
    }

    private function weightedState(): string
    {
        $roll = $this->float(0, 1);
        $cumulative = 0.0;

        foreach (self::STATES as $state => $config) {
            $cumulative += $config['share'];
            if ($roll <= $cumulative) {
                return $state;
            }
        }

        return array_key_first(self::STATES);
    }

    /** @return array{code: string, text: string} */
    private function weightedReason(): array
    {
        $roll = $this->float(0, 1);
        $cumulative = 0.0;

        foreach (self::RETURN_REASONS as $reason) {
            $cumulative += $reason['weight'];
            if ($roll <= $cumulative) {
                return ['code' => $reason['code'], 'text' => $reason['text']];
            }
        }

        return ['code' => 'changed_mind', 'text' => 'Changed my mind'];
    }

    /**
     * @param  list<object>  $skus
     * @return list<object>
     */
    private function weightedPool(array $skus): array
    {
        $pool = [];

        foreach ($skus as $sku) {
            $copies = (int) round($sku->weight * 10);
            for ($i = 0; $i < $copies; $i++) {
                $pool[] = $sku;
            }
        }

        return $pool;
    }

    private function discountCode(): string
    {
        $codes = ['WELCOME10', 'FESTIVE25', 'FLAT300', 'BOGO50', 'REPEAT15', 'INSTA20'];

        return $codes[$this->rand(0, count($codes) - 1)];
    }

    private function utmSource(): ?string
    {
        $sources = ['facebook', 'google', 'instagram', 'klaviyo', null, null];

        return $sources[$this->rand(0, count($sources) - 1)];
    }

    /** @return list<string> */
    private function extractHashtags(string $caption): array
    {
        preg_match_all('/#(\w+)/u', $caption, $matches);

        return array_values(array_unique($matches[1]));
    }

    private function rand(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    private function float(float $min, float $max): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }

    private function chance(float $probability): bool
    {
        return $this->float(0, 1) < $probability;
    }
}
