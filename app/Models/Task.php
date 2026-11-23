<?php

namespace App\Models;

use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

/**
 * A to-do item. Auto tasks (is_auto) are created by tasks:sync and keyed by source_key.
 *
 * @property int $id
 * @property string $title
 * @property string|null $detail
 * @property TaskCategory $category
 * @property TaskPriority|null $priority
 * @property CarbonImmutable|null $due_on
 * @property string|null $link_route
 * @property string|null $source_key
 * @property bool $is_auto
 * @property bool $auto_closed
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable(['title', 'detail', 'category', 'priority', 'due_on', 'link_route', 'source_key', 'is_auto', 'auto_closed', 'completed_at'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Where "Open" goes: a route name (e.g. "members.index") or a path inside the app (e.g. "/members/17").
     */
    public function linkUrl(): ?string
    {
        return match (true) {
            blank($this->link_route) => null,
            str_starts_with($this->link_route, '/') => url($this->link_route),
            Route::has($this->link_route) => route($this->link_route),
            default => null,
        };
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TaskCategory::class,
            'priority' => TaskPriority::class,
            'due_on' => 'date',
            'is_auto' => 'boolean',
            'auto_closed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
