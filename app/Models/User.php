<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property ?int $tenant_id
 * @property string $name
 * @property string $email
 * @property ?CarbonImmutable $email_verified_at
 * @property string $password
 * @property string $accent_color
 * @property string $theme
 * @property int $permissions_version
 * @property bool $is_demo
 * @property bool $is_active
 * @property bool $must_change_password
 * @property int $ai_credits_used
 * @property int $ai_credit_limit
 * @property ?string $mfa_secret
 * @property bool $mfa_enabled
 * @property ?CarbonImmutable $mfa_confirmed_at
 * @property array $mfa_recovery_codes
 * @property ?CarbonImmutable $last_login_at
 * @property ?string $remember_token
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property ?CarbonImmutable $deleted_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'accent_color', 'theme',
        'is_demo', 'is_active', 'must_change_password', 'ai_credit_limit',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_enabled' => 'boolean',
            'mfa_confirmed_at' => 'datetime',
            'is_demo' => 'boolean',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return HasMany<AiChatSession, $this> */
    public function aiChatSessions(): HasMany
    {
        return $this->hasMany(AiChatSession::class);
    }

    public function aiCreditsRemaining(): int
    {
        return max(0, $this->ai_credit_limit - $this->ai_credits_used);
    }

    public function bumpPermissionsVersion(): void
    {
        $this->forceFill(['permissions_version' => $this->permissions_version + 1])->save();
    }

    /**
     * Flattened permission list the SPA caches and busts on permissions_version change.
     *
     * @return list<string>
     */
    public function flatPermissions(): array
    {
        return $this->getAllPermissions()->pluck('name')->unique()->values()->all();
    }

    public function roleName(): ?string
    {
        return $this->getRoleNames()->first();
    }
}
