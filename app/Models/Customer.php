<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RfmSegment;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $source
 * @property ?string $external_id
 * @property ?string $email_hash
 * @property ?string $masked_email
 * @property ?string $email_encrypted
 * @property ?string $phone_hash
 * @property ?string $masked_phone
 * @property ?string $phone_encrypted
 * @property ?string $name
 * @property ?string $city
 * @property ?string $state
 * @property ?string $pincode
 * @property ?CarbonImmutable $first_order_at
 * @property ?CarbonImmutable $last_order_at
 * @property int $orders_count
 * @property int $total_spent
 * @property int $aov
 * @property int $ltv
 * @property int $total_margin
 * @property int $returns_count
 * @property ?int $rfm_r
 * @property ?int $rfm_f
 * @property ?int $rfm_m
 * @property ?RfmSegment $rfm_segment
 * @property bool $is_vip
 * @property bool $accepts_marketing
 * @property ?int $churn_risk_score
 * @property ?int $days_since_last_order
 * @property ?int $avg_days_between_orders
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Customer extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['email_encrypted', 'phone_encrypted'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
            'is_vip' => 'boolean',
            'accepts_marketing' => 'boolean',
            'rfm_segment' => RfmSegment::class,
            'email_encrypted' => 'encrypted',
            'phone_encrypted' => 'encrypted',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public static function hashIdentity(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', mb_strtolower(trim($value)).config('app.key'));
    }

    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(3, mb_strlen($local) - 2)).'@'.$domain;
    }

    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return strlen($digits) < 4 ? '****' : str_repeat('*', strlen($digits) - 4).substr($digits, -4);
    }
}
