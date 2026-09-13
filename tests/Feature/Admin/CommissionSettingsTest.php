<?php

declare(strict_types=1);

use App\Models\CostSetting;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->admin = $this->userFor($this->tenant);
});

it('saves a commission rate per payment method and reads it back', function (): void {
    $this->actingAs($this->admin)->putJson('/api/admin/settings', [
        'cost_settings' => [
            'cod_commission_pct' => 0.55,
            'upi_commission_pct' => 0.55,
            'cards_commission_pct' => 2,
            'dc_commission_pct' => 1.2,
            'netbanking_commission_pct' => 1.9,
            'wallets_commission_pct' => 1.9,
        ],
    ])->assertOk();

    $costs = CostSetting::query()->firstOrFail();

    expect($costs->cod_commission_pct)->toBe(0.55)
        ->and($costs->cards_commission_pct)->toBe(2.0)
        ->and($costs->dc_commission_pct)->toBe(1.2);

    $payload = $this->actingAs($this->admin)
        ->getJson('/api/admin/settings')
        ->assertOk()
        ->json('data.cost_settings');

    expect($payload['upi_commission_pct'])->toBe(0.55)
        ->and($payload['netbanking_commission_pct'])->toBe(1.9)
        ->and($payload['wallets_commission_pct'])->toBe(1.9);
});

it('starts every commission at zero rather than guessing a rate', function (): void {
    $payload = $this->actingAs($this->admin)
        ->getJson('/api/admin/settings')
        ->assertOk()
        ->json('data.cost_settings');

    // JSON gives a whole number back as an int, so the value is what matters here, not its type.
    expect((float) $payload['cod_commission_pct'])->toBe(0.0)
        ->and((float) $payload['cards_commission_pct'])->toBe(0.0);
});

it('refuses a commission that is not a percentage', function (): void {
    $this->actingAs($this->admin)
        ->putJson('/api/admin/settings', ['cost_settings' => ['cards_commission_pct' => 140]])
        ->assertStatus(422);

    $this->actingAs($this->admin)
        ->putJson('/api/admin/settings', ['cost_settings' => ['upi_commission_pct' => -1]])
        ->assertStatus(422);
});
