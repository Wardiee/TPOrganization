<?php

namespace App\Models;

use Database\Factories\OrgChartSeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One seat on a published org chart, with the name exactly as printed on the chart.
 *
 * @property int $id
 * @property int $org_chart_id
 * @property int $position_id
 * @property int|null $member_id
 * @property string $name_as_listed
 * @property string|null $note
 */
#[Fillable(['org_chart_id', 'position_id', 'member_id', 'name_as_listed', 'note'])]
class OrgChartSeat extends Model
{
    /** @use HasFactory<OrgChartSeatFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<OrgChart, $this>
     */
    public function orgChart(): BelongsTo
    {
        return $this->belongsTo(OrgChart::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }
}
