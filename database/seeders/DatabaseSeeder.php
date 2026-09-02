<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\RoleRegistry;
use App\Enums\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'kaira-living'],
            [
                'name' => 'Kaira Living',
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'fiscal_year_start' => 4,
                'plan' => Plan::Growth,
                'ai_credit_limit' => 500,
                'is_demo' => true,
                'onboarding_state' => ['step' => 'explore_demo', 'completed' => []],
            ],
        );

        app(PermissionSeeder::class)->seedRolesFor($tenant);

        app(TenantContext::class)->runAs($tenant, function () use ($tenant): void {
            $users = [
                ['Owner', 'owner@kairaliving.test', RoleRegistry::OWNER],
                ['Finance Lead', 'finance@kairaliving.test', RoleRegistry::FINANCE],
                ['Growth Lead', 'marketing@kairaliving.test', RoleRegistry::MARKETING],
                ['Ops Manager', 'ops@kairaliving.test', RoleRegistry::OPERATIONS],
                ['Analyst', 'analyst@kairaliving.test', RoleRegistry::ANALYST],
                ['Demo Explorer', 'demo@kairaliving.test', RoleRegistry::DEMO],
            ];

            foreach ($users as [$name, $email, $role]) {
                $user = User::query()->updateOrCreate(
                    ['email' => $email],
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $name,
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                        'is_demo' => $role === RoleRegistry::DEMO,
                        'is_active' => true,
                        'accent_color' => 'indigo',
                        'ai_credit_limit' => $role === RoleRegistry::DEMO ? 2 : 200,
                    ],
                );

                $user->syncRoles([$role]);
            }

            $this->command?->info('Seeding demo dataset for '.$tenant->name.'…');
            $this->call(DemoDataSeeder::class);
        });

        $this->command?->newLine();
        $this->command?->info('Sign in with owner@kairaliving.test / password');
    }
}
