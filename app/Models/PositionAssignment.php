<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PositionAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who held a position, and when. Never deleted: end it with ended_on and open a new row.
 *
 * member_id is null when the holder is not in the members list (the Founder).
 *
 * @property int $id
 * @property int $position_id
 * @property int|null $member_id
 * @property string|null $name_as_listed
 * @property CarbonImmutable|null $started_on
 * @property CarbonImmutable|null $ended_on
 * @property string|null $source
 * @property-read Position $position
 * @property-read Member|null $member
 */
#[Fillable(['position_id', 'member_id', 'name_as_listed', 'started_on', 'ended_on', 'source'])]
class PositionAssignment extends Model
{
    /** @use HasFactory<PositionAssignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
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

    /**
     * @param  Builder<PositionAssignment>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->whereNull('ended_on');
    }
}
