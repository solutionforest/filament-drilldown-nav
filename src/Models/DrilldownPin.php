<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A page a user pinned in the drill-down sidebar (`DrilldownNavPlugin::pinnable(database: true)`).
 * Override with `pinnable(database: true, model: YourPin::class)` (same table columns).
 *
 * @property int $id
 * @property string $user_type
 * @property string $user_id
 * @property string $panel
 * @property string $tenant
 * @property string $url
 * @property string $url_hash
 * @property string $label
 */
class DrilldownPin extends Model
{
    protected $table = 'drilldown_nav_pins';

    protected $guarded = ['id'];
}
