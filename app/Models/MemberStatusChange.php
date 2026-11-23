<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MemberStatusChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One status change for a member. Kept forever as history.
 *
 * from_status is null when the earlier status was not recorded.
 *
 * @property int $id
 * @property int $member_id
 * @property MemberStatus|null $from_status
 * @property MemberStatus $to_status
 * @property CarbonImmutable $changed_on
 * @property string|null $note
 */
#[Fillable(['member_id', 'from_status', 'to_status', 'changed_on', 'note'])]
class MemberStatusChange extends Model
{
    /** @use HasFactory<MemberStatusChangeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => MemberStatus::class,
            'to_status' => MemberStatus::class,
            'changed_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }
}
