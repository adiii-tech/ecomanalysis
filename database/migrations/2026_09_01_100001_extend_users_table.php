<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('accent_color', 16)->default('indigo')->after('password');
            $table->string('theme', 16)->default('system')->after('accent_color');
            $table->unsignedInteger('permissions_version')->default(1)->after('theme');
            $table->boolean('is_demo')->default(false)->after('permissions_version');
            $table->boolean('is_active')->default(true)->after('is_demo');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->unsignedInteger('ai_credits_used')->default(0)->after('must_change_password');
            $table->unsignedInteger('ai_credit_limit')->default(50)->after('ai_credits_used');
            $table->string('mfa_secret')->nullable()->after('ai_credit_limit');
            $table->boolean('mfa_enabled')->default(false)->after('mfa_secret');
            $table->timestamp('mfa_confirmed_at')->nullable()->after('mfa_enabled');
            $table->json('mfa_recovery_codes')->nullable()->after('mfa_confirmed_at');
            $table->timestamp('last_login_at')->nullable()->after('mfa_recovery_codes');
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn([
                'accent_color', 'theme', 'permissions_version', 'is_demo', 'is_active',
                'must_change_password', 'ai_credits_used', 'ai_credit_limit', 'mfa_secret',
                'mfa_enabled', 'mfa_confirmed_at', 'mfa_recovery_codes', 'last_login_at', 'deleted_at',
            ]);
        });
    }
};
