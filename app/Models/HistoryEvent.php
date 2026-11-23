<?php

namespace App\Models;

use App\Enums\DatePrecision;
use App\Enums\HistoryKind;
use Carbon\CarbonImmutable;
use Database\Factories\HistoryEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An entry on the org history timeline and in "Recent changes".
 *
 * @property int $id
 * @property CarbonImmutable $happened_on
 * @property DatePrecision $date_precision
 * @property string $title
 * @property string|null $detail
 * @property HistoryKind $kind
 */
#[Fillable(['happened_on', 'date_precision', 'title', 'detail', 'kind'])]
class HistoryEvent extends Model
{
    /** @use HasFactory<HistoryEventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'happened_on' => 'date',
            'date_precision' => DatePrecision::class,
            'kind' => HistoryKind::class,
        ];
    }

    /**
     * Newest first; events on the same day show the most recently recorded first.
     *
     * @param  Builder<HistoryEvent>  $query
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('happened_on')->orderByDesc('id');
    }
}
