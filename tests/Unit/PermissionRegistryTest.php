<?php

declare(strict_types=1);

use App\Domain\Access\PermissionRegistry;
use App\Domain\Access\RoleRegistry;

it('defines a permission for every widget in every module', function (): void {
    $all = PermissionRegistry::all();

    expect($all)->toHaveCount(count(array_unique($all)))
        ->and(count($all))->toBeGreaterThan(300);

    foreach (PermissionRegistry::modules() as $module => $config) {
        foreach (array_keys($config['widgets']) as $widget) {
            expect($all)->toContain("{$module}.{$widget}.view");
        }
    }
});

it('names permissions as module.widget.action', function (): void {
    foreach (PermissionRegistry::all() as $permission) {
        expect(substr_count($permission, '.'))->toBe(2, "Malformed permission: {$permission}");
    }
});

it('gives the owner everything and the analyst read-only access', function (): void {
    $owner = RoleRegistry::permissionsFor(RoleRegistry::OWNER);
    $analyst = RoleRegistry::permissionsFor(RoleRegistry::ANALYST);

    expect($owner)->toHaveCount(count(PermissionRegistry::all()))
        ->and($analyst)->not->toContain('admin.users.manage')
        ->and($analyst)->not->toContain('pii.unmask.view');

    foreach ($analyst as $permission) {
        expect($permission)->toEndWith('.view');
    }
});

it('keeps billing away from every role but the owner', function (): void {
    expect(RoleRegistry::permissionsFor(RoleRegistry::ADMIN))->not->toContain('admin.billing.manage')
        ->and(RoleRegistry::permissionsFor(RoleRegistry::OWNER))->toContain('admin.billing.manage');
});

it('keeps the demo role out of finance detail and admin entirely', function (): void {
    $demo = RoleRegistry::permissionsFor(RoleRegistry::DEMO);

    expect($demo)->not->toContain('admin.users.view')
        ->and($demo)->not->toContain('connectors.credentials.manage')
        ->and($demo)->toContain('dashboard.kpi_strip.view');
});
