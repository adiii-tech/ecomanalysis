<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Access\PermissionRegistry;
use App\Domain\Access\RoleProvisioner;
use App\Domain\Access\RoleRegistry;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ActivityLog;
use App\Models\Benchmark;
use App\Models\CostSetting;
use App\Models\Invitation;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Support\Facades\Tenant;
use App\Support\MetricCache;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminController extends Controller
{
    public function users(): JsonResponse
    {
        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roleName(),
                'is_active' => $user->is_active,
                'is_demo' => $user->is_demo,
                'mfa_enabled' => $user->mfa_enabled,
                'must_change_password' => $user->must_change_password,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'ai_credits_used' => $user->ai_credits_used,
                'ai_credit_limit' => $user->ai_credit_limit,
                // A user with overrides no longer matches their role exactly.
                'has_overrides' => $user->permissions()->count() > 0,
            ]);

        $invites = Invitation::query()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->get()
            ->map(static fn (Invitation $invite): array => [
                'id' => $invite->id,
                'email' => $invite->email,
                'role' => $invite->role,
                'expires_at' => $invite->expires_at->toIso8601String(),
                'expired' => $invite->expires_at->isPast(),
                'send_count' => $invite->send_count,
            ]);

        return ApiResponse::ok([
            'users' => $users->all(),
            'invitations' => $invites->all(),
            'roles' => $this->assignableRoles(),
            'seat_limit' => Tenant::current()->plan->seatLimit(),
            'seats_used' => $users->where('is_active', true)->count(),
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $tenant = Tenant::current();

        $validated = $request->validate([
            'email' => ['required', 'email', Rule::unique('invitations')->where('tenant_id', $tenant->id)],
            'name' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in($this->assignableRoles())],
        ]);

        $activeSeats = User::query()->where('is_active', true)->count();

        if ($activeSeats >= $tenant->plan->seatLimit()) {
            return ApiResponse::error(
                sprintf('Your %s plan allows %d seats and %d are in use.', $tenant->plan->label(), $tenant->plan->seatLimit(), $activeSeats),
                422,
            );
        }

        $invitation = Invitation::query()->create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'token' => Str::random(64),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(14),
        ]);

        activity('admin')->performedOn($invitation)
            ->withProperties(['email' => $invitation->email, 'role' => $invitation->role])
            ->log('user.invited');

        return ApiResponse::ok([
            'id' => $invitation->id,
            // Mail is not wired in this environment, so the link is returned
            // for the admin to pass on rather than silently going nowhere.
            'accept_url' => url('/accept-invite/'.$invitation->token),
        ], message: 'Invite created.');
    }

    public function resendInvite(int $invitation): JsonResponse
    {
        $model = Invitation::query()->find($invitation);

        if ($model === null || $model->accepted_at !== null) {
            return ApiResponse::error('That invite is no longer pending.', 404);
        }

        $model->forceFill([
            'expires_at' => now()->addDays(14),
            'send_count' => $model->send_count + 1,
        ])->save();

        return ApiResponse::ok(['accept_url' => url('/accept-invite/'.$model->token)], message: 'Invite refreshed.');
    }

    public function revokeInvite(int $invitation): JsonResponse
    {
        Invitation::query()->find($invitation)?->forceFill(['revoked_at' => now()])->save();

        return ApiResponse::ok(null, message: 'Invite revoked.');
    }

    public function updateUser(Request $request, int $user): JsonResponse
    {
        $model = User::query()->find($user);

        if ($model === null) {
            return ApiResponse::error('User not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'role' => ['sometimes', Rule::in($this->assignableRoles())],
            'is_active' => ['sometimes', 'boolean'],
            'must_change_password' => ['sometimes', 'boolean'],
            'ai_credit_limit' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        // Locking yourself out is the one mistake with no in-app recovery.
        if ($model->id === $request->user()->id && ($validated['is_active'] ?? true) === false) {
            return ApiResponse::error('You cannot disable your own account.', 422);
        }

        if (isset($validated['role'])) {
            app(RoleProvisioner::class)->ensureFor(Tenant::id());
            $model->syncRoles([$validated['role']]);
            $model->bumpPermissionsVersion();
            unset($validated['role']);
        }

        $model->forceFill($validated)->save();

        activity('admin')->performedOn($model)->withProperties($validated)->log('user.updated');

        return ApiResponse::ok(null, message: 'User updated.');
    }

    public function resetPassword(Request $request, int $user): JsonResponse
    {
        $model = User::query()->find($user);

        if ($model === null) {
            return ApiResponse::error('User not found.', 404);
        }

        $temporary = Str::password(16);

        $model->forceFill([
            'password' => Hash::make($temporary),
            'must_change_password' => true,
        ])->save();

        activity('admin')->performedOn($model)->log('user.password_reset');

        return ApiResponse::ok(
            ['temporary_password' => $temporary],
            message: 'Password reset. Share this once — they must change it at next sign-in.',
        );
    }

    /**
     * The per-user permission editor: role grants shown as the baseline, with
     * explicit overrides layered on top and a reset back to the role.
     */
    public function permissions(int $user): JsonResponse
    {
        $model = User::query()->with('roles')->find($user);

        if ($model === null) {
            return ApiResponse::error('User not found.', 404);
        }

        $fromRole = $model->getPermissionsViaRoles()->pluck('name')->all();
        $direct = $model->permissions->pluck('name')->all();

        $modules = collect(PermissionRegistry::modules())
            ->map(static function (array $config, string $module) use ($fromRole, $direct): array {
                $permissions = collect(PermissionRegistry::forModule($module))
                    ->map(static fn (string $permission): array => [
                        'permission' => $permission,
                        'widget' => explode('.', $permission)[1],
                        'action' => explode('.', $permission)[2],
                        'label' => PermissionRegistry::label($permission),
                        'from_role' => in_array($permission, $fromRole, true),
                        'granted_directly' => in_array($permission, $direct, true),
                        'effective' => in_array($permission, $fromRole, true) || in_array($permission, $direct, true),
                    ])
                    ->values();

                return [
                    'module' => $module,
                    'label' => $config['label'],
                    'permissions' => $permissions->all(),
                    'granted' => $permissions->where('effective', true)->count(),
                    'total' => $permissions->count(),
                ];
            })
            ->values();

        return ApiResponse::ok([
            'user' => ['id' => $model->id, 'name' => $model->name, 'role' => $model->roleName()],
            'modules' => $modules->all(),
            'override_count' => count($direct),
        ]);
    }

    public function updatePermissions(Request $request, int $user): JsonResponse
    {
        $model = User::query()->find($user);

        if ($model === null) {
            return ApiResponse::error('User not found.', 404);
        }

        $validated = $request->validate([
            'grant' => ['array'],
            'grant.*' => ['string', Rule::in(PermissionRegistry::all())],
        ]);

        $model->syncPermissions($validated['grant'] ?? []);
        $model->bumpPermissionsVersion();

        activity('admin')->performedOn($model)
            ->withProperties(['override_count' => count($validated['grant'] ?? [])])
            ->log('user.permissions_updated');

        return ApiResponse::ok(null, message: 'Permissions saved.');
    }

    public function resetPermissions(int $user): JsonResponse
    {
        $model = User::query()->find($user);

        if ($model === null) {
            return ApiResponse::error('User not found.', 404);
        }

        $model->syncPermissions([]);
        $model->bumpPermissionsVersion();

        activity('admin')->performedOn($model)->log('user.permissions_reset');

        return ApiResponse::ok(null, message: 'Reset to role defaults.');
    }

    public function roles(): JsonResponse
    {
        app(RoleProvisioner::class)->ensureFor(Tenant::id());

        $roles = $this->tenantRoles()->orderBy('name')->get();

        // `withCount('users')` cannot be used here: spatie resolves the related
        // model from the role's own guard_name, which a count subquery does not
        // carry. Counting the pivots directly is both correct and cheaper.
        $userCounts = DB::table(config('permission.table_names.model_has_roles'))
            ->where(self::teamKey(), Tenant::id())
            ->selectRaw('role_id, COUNT(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $permissionCounts = DB::table(config('permission.table_names.role_has_permissions'))
            ->whereIn('role_id', $roles->pluck('id')->all())
            ->selectRaw('role_id, COUNT(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $rows = $roles->map(static fn (Role $role): array => [
            'id' => $role->id,
            'name' => $role->name,
            'description' => RoleRegistry::descriptions()[$role->name] ?? 'Custom role.',
            'is_builtin' => in_array($role->name, RoleRegistry::names(), true),
            'users_count' => (int) ($userCounts[$role->id] ?? 0),
            'permission_count' => (int) ($permissionCounts[$role->id] ?? 0),
        ]);

        return ApiResponse::ok([
            'rows' => $rows->all(),
            'catalogue' => collect(PermissionRegistry::modules())
                ->map(static fn (array $config, string $module): array => [
                    'module' => $module,
                    'label' => $config['label'],
                    'permissions' => PermissionRegistry::forModule($module),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function role(int $role): JsonResponse
    {
        $model = $this->tenantRoles()->with('permissions:id,name')->find($role);

        if ($model === null) {
            return ApiResponse::error('Role not found.', 404);
        }

        return ApiResponse::ok([
            'id' => $model->id,
            'name' => $model->name,
            'is_builtin' => in_array($model->name, RoleRegistry::names(), true),
            'permissions' => $model->permissions->pluck('name')->all(),
        ]);
    }

    public function saveRole(Request $request, ?int $role = null): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9_]+$/'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionRegistry::all())],
        ]);

        $model = $role !== null ? $this->tenantRoles()->find($role) : null;

        if ($role !== null && $model === null) {
            return ApiResponse::error('Role not found.', 404);
        }

        // Built-in roles are the safety net every tenant relies on.
        if ($model !== null && in_array($model->name, RoleRegistry::names(), true) && $model->name !== $validated['name']) {
            return ApiResponse::error('Built-in roles cannot be renamed. Create a custom role instead.', 422);
        }

        $model ??= Role::findOrCreate($validated['name'], 'web');
        $model->syncPermissions($validated['permissions'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('admin')->performedOn($model)
            ->withProperties(['permission_count' => count($validated['permissions'] ?? [])])
            ->log('role.saved');

        return ApiResponse::ok(['id' => $model->id], message: 'Role saved.');
    }

    public function audit(Request $request): JsonResponse
    {
        $rows = ActivityLog::query()
            ->when($request->filled('log'), fn ($q) => $q->where('log_name', $request->string('log')))
            ->latest('id')
            ->limit(300)
            ->get()
            ->map(static fn (ActivityLog $entry): array => [
                'id' => $entry->id,
                'log' => $entry->log_name,
                'description' => $entry->description,
                'causer' => $entry->causer instanceof User ? $entry->causer->name : 'System',
                'subject_type' => $entry->subject_type ? class_basename($entry->subject_type) : null,
                'properties' => $entry->properties,
                'created_at' => $entry->created_at?->toIso8601String(),
            ]);

        return ApiResponse::ok([
            'rows' => $rows->all(),
            'logs' => ActivityLog::query()->distinct()->pluck('log_name')->filter()->values()->all(),
        ]);
    }

    public function settings(): JsonResponse
    {
        $tenant = Tenant::current();
        $costs = CostSetting::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        $benchmarks = Benchmark::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        $notifications = NotificationSetting::query()->firstOrNew(['tenant_id' => $tenant->id]);

        return ApiResponse::ok([
            'tenant' => [
                'name' => $tenant->name,
                'currency' => $tenant->currency,
                'timezone' => $tenant->timezone,
                'fiscal_year_start' => $tenant->fiscal_year_start,
                'plan' => $tenant->plan->value,
                'plan_label' => $tenant->plan->label(),
                'is_demo' => $tenant->is_demo,
            ],
            'plan_limits' => [
                'connectors' => $tenant->plan->connectorLimit(),
                'seats' => $tenant->plan->seatLimit(),
                'ai_credits' => $tenant->plan->aiCredits(),
                'history_days' => $tenant->plan->historyWindowDays(),
                'alert_rules' => $tenant->plan->alertRuleLimit(),
                'chart_insights' => $tenant->plan->hasChartInsights(),
            ],
            // Costs are stored in paise; the editor works in rupees.
            'cost_settings' => [
                'packaging_cost' => Money::toRupees($costs->packaging_cost),
                'per_order_fixed_cost' => Money::toRupees($costs->per_order_fixed_cost),
                'cod_charge' => Money::toRupees($costs->cod_charge),
                'return_handling_cost' => Money::toRupees($costs->return_handling_cost),
                'rto_handling_cost' => Money::toRupees($costs->rto_handling_cost),
                'default_shipping_cost' => Money::toRupees($costs->default_shipping_cost),
                'monthly_fixed_opex' => Money::toRupees($costs->monthly_fixed_opex),
                'gateway_fee_pct' => (float) $costs->gateway_fee_pct,
                'gst_mode' => $costs->gst_mode,
            ],
            'tenant_profile' => [
                'gst_state' => $tenant->gst_state,
                'gstin' => $tenant->gstin,
            ],
            'notifications' => [
                'email_recipients' => $notifications->email_recipients ?? [],
                'slack_webhook_url' => $notifications->slack_webhook_url,
                'whatsapp_phone_number_id' => $notifications->whatsapp_phone_number_id,
                // The token is never returned; only whether one is stored.
                'whatsapp_token_set' => filled($notifications->whatsapp_token),
                'whatsapp_template' => $notifications->whatsapp_template,
                'whatsapp_recipients' => $notifications->whatsapp_recipients ?? [],
                'daily_brief_recipients' => $notifications->daily_brief_recipients ?? [],
                'daily_brief_hour' => $notifications->daily_brief_hour,
                'weekly_review_recipients' => $notifications->weekly_review_recipients ?? [],
                'weekly_review_day' => $notifications->weekly_review_day,
                'monthly_pnl_recipients' => $notifications->monthly_pnl_recipients ?? [],
            ],
            'benchmarks' => [
                'target_roas' => (float) $benchmarks->target_roas,
                'target_margin_pct' => (float) $benchmarks->target_margin_pct,
                'target_repeat_rate' => (float) $benchmarks->target_repeat_rate,
                'dispatch_sla_days' => $benchmarks->dispatch_sla_days,
                'delivery_sla_days' => $benchmarks->delivery_sla_days,
                'rto_threshold_pct' => (float) $benchmarks->rto_threshold_pct,
                'return_threshold_pct' => (float) $benchmarks->return_threshold_pct,
                'days_of_cover_threshold' => $benchmarks->days_of_cover_threshold,
                'monthly_revenue_target' => Money::toRupees($benchmarks->monthly_revenue_target),
            ],
        ]);
    }

    public function saveSettings(Request $request, MetricCache $cache): JsonResponse
    {
        $validated = $request->validate([
            'cost_settings' => ['array'],
            'cost_settings.packaging_cost' => ['numeric', 'min:0'],
            'cost_settings.per_order_fixed_cost' => ['numeric', 'min:0'],
            'cost_settings.cod_charge' => ['numeric', 'min:0'],
            'cost_settings.return_handling_cost' => ['numeric', 'min:0'],
            'cost_settings.rto_handling_cost' => ['numeric', 'min:0'],
            'cost_settings.default_shipping_cost' => ['numeric', 'min:0'],
            'cost_settings.monthly_fixed_opex' => ['numeric', 'min:0'],
            'cost_settings.gateway_fee_pct' => ['numeric', 'min:0', 'max:100'],
            'benchmarks' => ['array'],
            'benchmarks.target_roas' => ['numeric', 'min:0'],
            'benchmarks.target_margin_pct' => ['numeric', 'min:0', 'max:100'],
            'benchmarks.target_repeat_rate' => ['numeric', 'min:0', 'max:100'],
            'benchmarks.dispatch_sla_days' => ['integer', 'min:0', 'max:30'],
            'benchmarks.delivery_sla_days' => ['integer', 'min:0', 'max:60'],
            'benchmarks.rto_threshold_pct' => ['numeric', 'min:0', 'max:100'],
            'benchmarks.return_threshold_pct' => ['numeric', 'min:0', 'max:100'],
            'benchmarks.days_of_cover_threshold' => ['integer', 'min:1', 'max:365'],
            'benchmarks.monthly_revenue_target' => ['numeric', 'min:0'],
            'tenant_profile' => ['array'],
            'tenant_profile.gst_state' => ['nullable', 'string', 'max:64'],
            'tenant_profile.gstin' => ['nullable', 'string', 'max:20'],
            'notifications' => ['array'],
            'notifications.email_recipients' => ['array', 'max:20'],
            'notifications.email_recipients.*' => ['email'],
            'notifications.slack_webhook_url' => ['nullable', 'url', 'max:512'],
            'notifications.whatsapp_phone_number_id' => ['nullable', 'string', 'max:64'],
            'notifications.whatsapp_token' => ['nullable', 'string', 'max:512'],
            'notifications.whatsapp_template' => ['nullable', 'string', 'max:96'],
            'notifications.whatsapp_recipients' => ['array', 'max:20'],
            'notifications.whatsapp_recipients.*' => ['string', 'max:20'],
            'notifications.daily_brief_recipients' => ['array', 'max:20'],
            'notifications.daily_brief_recipients.*' => ['email'],
            'notifications.daily_brief_hour' => ['integer', 'min:0', 'max:23'],
            'notifications.weekly_review_recipients' => ['array', 'max:20'],
            'notifications.weekly_review_recipients.*' => ['email'],
            'notifications.weekly_review_day' => ['integer', 'min:0', 'max:6'],
            'notifications.monthly_pnl_recipients' => ['array', 'max:20'],
            'notifications.monthly_pnl_recipients.*' => ['email'],
        ]);

        $tenant = Tenant::current();

        if (isset($validated['cost_settings'])) {
            $costs = $validated['cost_settings'];

            foreach (['packaging_cost', 'per_order_fixed_cost', 'cod_charge', 'return_handling_cost', 'rto_handling_cost', 'default_shipping_cost', 'monthly_fixed_opex'] as $field) {
                if (isset($costs[$field])) {
                    $costs[$field] = Money::fromRupees($costs[$field]);
                }
            }

            CostSetting::query()->updateOrCreate(['tenant_id' => $tenant->id], $costs);
        }

        if (isset($validated['benchmarks'])) {
            $benchmarks = $validated['benchmarks'];

            if (isset($benchmarks['monthly_revenue_target'])) {
                $benchmarks['monthly_revenue_target'] = Money::fromRupees($benchmarks['monthly_revenue_target']);
            }

            Benchmark::query()->updateOrCreate(['tenant_id' => $tenant->id], $benchmarks);
        }

        if (isset($validated['tenant_profile'])) {
            $tenant->forceFill($validated['tenant_profile'])->save();
        }

        if (isset($validated['notifications'])) {
            $notifications = $validated['notifications'];

            // An empty token field means "leave the stored one alone", not
            // "delete it" — the editor never receives the current value.
            if (blank($notifications['whatsapp_token'] ?? null)) {
                unset($notifications['whatsapp_token']);
            }

            NotificationSetting::query()->updateOrCreate(['tenant_id' => $tenant->id], $notifications);
        }

        // Cost settings move every margin number, so every cached widget is stale.
        $cache->bust($tenant->id);

        $audited = $validated;
        unset($audited['notifications']['whatsapp_token']);

        activity('admin')->withProperties($audited)->log('settings.updated');

        return ApiResponse::ok(
            null,
            message: 'Saved. Margin figures update on the next rollup rebuild — run it now from Connectors, or wait for tonight.',
        );
    }

    /**
     * Built-in roles are always assignable even before a tenant has anyone in
     * them, so validate against the registry rather than the rows that happen
     * to exist.
     *
     * @return list<string>
     */
    private function assignableRoles(): array
    {
        return app(RoleProvisioner::class)->assignableFor(Tenant::id());
    }

    /**
     * Spatie does not scope its own models, so every role query has to carry
     * the tenant filter explicitly.
     *
     * @return Builder<Role>
     */
    private function tenantRoles(): Builder
    {
        return Role::query()->where(self::teamKey(), Tenant::id());
    }

    private static function teamKey(): string
    {
        return config('permission.column_names.team_foreign_key', 'team_id');
    }
}
