<?php

namespace App\Models;

use App\Enums\Branch;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A seat in the org structure: FOUNDER, CO-FOUNDER, TROP-1 to 15 (Main) and TROPS-1 to 15 (Ext).
 *
 * @property int $id
 * @property string $code
 * @property string $title
 * @property Branch|null $branch
 * @property int $sort
 */
#[Fillable(['code', 'title', 'branch', 'sort'])]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch' => Branch::class,
            'sort' => 'integer',
        ];
    }

    /**
     * Everyone who has held this seat, past and present.
     *
     * @return HasMany<PositionAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(PositionAssignment::class);
    }

    /**
     * The open assignment (no end date), if the seat is filled.
     *
     * @return HasOne<PositionAssignment, $this>
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(PositionAssignment::class)->whereNull('ended_on')->latestOfMany();
    }

    /**
     * Short label for pills: "TROP-9 · Main", "TROPS-2 · Ext", or the title for "Co-Founder".
     */
    public function shortLabel(): string
    {
        return $this->branch === null ? $this->title : "{$this->code} · {$this->branch->label()}";
    }

    /**
     * Long label for details: "TROP-9 (Main branch)", or the title for "Co-Founder".
     */
    public function longLabel(): string
    {
        return $this->branch === null ? $this->title : "{$this->code} ({$this->branch->label()} branch)";
    }

    /**
     * Founder and Co-Founder first, then TROP seats, then TROPS seats; within each, by sort.
     *
     * @param  Builder<Position>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query
            ->orderByRaw('case when branch is null then 0 when branch = ? then 1 else 2 end', [Branch::Trop->value])
            ->orderBy('sort');
    }

    /**
     * The number in a seat code: 16 for "TROP-16", null for "FOUNDER".
     */
    public function number(): ?int
    {
        return preg_match('/-(\d+)$/', $this->code, $matches) === 1 ? (int) $matches[1] : null;
    }
}
