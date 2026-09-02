<?php

declare(strict_types=1);

namespace App\Domain\Connectors;

use App\Domain\Connectors\Contracts\Connector;
use App\Models\Connector as ConnectorModel;
use App\Support\TenantContext;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class ConnectorRegistry
{
    /**
     * Phase 1 drivers pull live data. Phase 2 entries implement the same
     * interface but are stubs until their driver lands.
     *
     * @var array<string, class-string<Connector>>
     */
    private const DRIVERS = [
        // Phase 1
        'shopify' => Drivers\ShopifyConnector::class,
        'unicommerce' => Drivers\UnicommerceConnector::class,
        'meta' => Drivers\MetaConnector::class,
        'google_ads' => Drivers\GoogleAdsConnector::class,
        'ga4' => Drivers\Ga4Connector::class,
        'ithink' => Drivers\IthinkConnector::class,
        'judgeme' => Drivers\JudgeMeConnector::class,

        // Phase 2 — interface built, driver stubbed
        'shiprocket' => Drivers\Stubs\ShiprocketConnector::class,
        'delhivery' => Drivers\Stubs\DelhiveryConnector::class,
        'bluedart' => Drivers\Stubs\BluedartConnector::class,
        'razorpay' => Drivers\Stubs\RazorpayConnector::class,
        'cashfree' => Drivers\Stubs\CashfreeConnector::class,
        'easebuzz' => Drivers\Stubs\EasebuzzConnector::class,
        'gokwik' => Drivers\Stubs\GokwikConnector::class,
        'amazon_sp_api' => Drivers\Stubs\AmazonSpApiConnector::class,
        'flipkart_seller' => Drivers\Stubs\FlipkartSellerConnector::class,
        'meesho' => Drivers\Stubs\MeeshoConnector::class,
        'nykaa' => Drivers\Stubs\NykaaConnector::class,
        'whatsapp_cloud_api' => Drivers\Stubs\WhatsAppCloudConnector::class,
        'klaviyo' => Drivers\Stubs\KlaviyoConnector::class,
        'mailchimp' => Drivers\Stubs\MailchimpConnector::class,
        'tally' => Drivers\Stubs\TallyConnector::class,
        'zoho_books' => Drivers\Stubs\ZohoBooksConnector::class,
        'woocommerce' => Drivers\Stubs\WooCommerceConnector::class,
        'tiktok_ads' => Drivers\Stubs\TikTokAdsConnector::class,
        'pinterest_ads' => Drivers\Stubs\PinterestAdsConnector::class,
        'microsoft_clarity' => Drivers\Stubs\MicrosoftClarityConnector::class,
    ];

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys(self::DRIVERS);
    }

    public function has(string $id): bool
    {
        return isset(self::DRIVERS[$id]);
    }

    public function make(string $id): Connector
    {
        $class = self::DRIVERS[$id] ?? throw new InvalidArgumentException("Unknown connector [{$id}].");

        return app($class);
    }

    /**
     * A driver bound to the tenant's stored connector row, if one exists.
     */
    public function forTenant(string $id): Connector
    {
        $driver = $this->make($id);
        $model = ConnectorModel::query()
            ->where('tenant_id', app(TenantContext::class)->requireId())
            ->where('connector_id', $id)
            ->first();

        return $model !== null && method_exists($driver, 'bind') ? $driver->bind($model) : $driver;
    }

    /** @return Collection<int, Connector> */
    public function all(): Collection
    {
        return collect($this->ids())->map(fn (string $id): Connector => $this->make($id));
    }

    /** @return Collection<int, Connector> */
    public function live(): Collection
    {
        return $this->all()->reject(fn (Connector $c): bool => $c->isStub())->values();
    }

    /**
     * Catalogue payload for the connectors page.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(): array
    {
        return $this->all()->map(fn (Connector $c): array => [
            'id' => $c->id(),
            'label' => $c->label(),
            'summary' => $c->summary(),
            'auth_type' => $c->authType()->value,
            'auth_label' => $c->authType()->label(),
            'entities' => $c->syncableEntities(),
            'fields' => $c->credentialFields(),
            'cadence' => $c->syncCadence(),
            'is_stub' => $c->isStub(),
            'webhooks' => $c->webhookTopics(),
        ])->all();
    }
}
