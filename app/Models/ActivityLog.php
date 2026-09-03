<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * The audit trail is per tenant: one brand's admin must never read another's
 * exports, permission changes or connector activity.
 *
 * @property int $id
 * @property ?int $tenant_id
 * @property ?string $log_name
 * @property string $description
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property ?string $event
 * @property ?string $causer_type
 * @property ?int $causer_id
 * @property array $attribute_changes
 * @property array $properties
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class ActivityLog extends BaseActivity
{
    use BelongsToTenant;
}
