<?php

namespace App\Models;

use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable(['title', 'detail', 'category', 'priority', 'due_on', 'link_route', 'source_key', 'is_auto', 'completed_at'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

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
            'completed_at' => 'datetime',
        ];
    }
}
