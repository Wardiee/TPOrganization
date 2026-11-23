<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\OrgChartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A published, read-only snapshot of the org chart, e.g. "Org Chart 2024".
 *
 * @property int $id
 * @property string $title
 * @property CarbonImmutable $published_on
 * @property CarbonImmutable|null $updated_on
 */
#[Fillable(['title', 'published_on', 'updated_on'])]
class OrgChart extends Model
{
    /** @use HasFactory<OrgChartFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'updated_on' => 'date',
        ];
    }

    /**
     * @return HasMany<OrgChartSeat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(OrgChartSeat::class);
    }
}
