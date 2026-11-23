<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Member;
use App\Models\Position;

class MemberSeeder extends JsonSeeder
{
    /**
     * Current positions in the members list date from the published Org Chart 2024.
     */
    private const ASSIGNED_ON = '2024-05-02';

    private const ASSIGNMENT_SOURCE = 'Org Chart 2024';

    /**
     * Seed members, their open position assignments and recorded status changes.
     *
     * Inactive members keep their position open on purpose: a seat held by an inactive
     * member is an issue the dashboard flags for reassignment.
     */
    public function run(): void
    {
        $chapterIds = Chapter::pluck('id', 'name');
        $positionIds = Position::pluck('id', 'code');

        foreach ($this->data('members.json') as $record) {
            $member = Member::create([
                'member_no' => $record['member_no'],
                'full_name' => $record['full_name'],
                'nickname' => $record['nickname'],
                'birth_month' => $record['birth_month'],
                'birth_day' => $record['birth_day'],
                'birth_year' => $record['birth_year'],
                'grade_level' => $record['grade_level'],
                'chapter_id' => $record['chapter'] === null ? null : $this->idFor($chapterIds, $record['chapter'], 'chapter'),
                'status' => $record['status'],
                'status_changed_on' => $record['status_changed_on'],
                'remarks' => $record['remarks'],
            ]);

            if ($record['current_position'] !== null) {
                $member->positionAssignments()->create([
                    'position_id' => $this->idFor($positionIds, $record['current_position'], 'position'),
                    'started_on' => self::ASSIGNED_ON,
                    'source' => self::ASSIGNMENT_SOURCE,
                ]);
            }

            if ($record['status_changed_on'] !== null) {
                $member->statusChanges()->create([
                    'from_status' => null,
                    'to_status' => $record['status'],
                    'changed_on' => $record['status_changed_on'],
                    'note' => 'From the members list. Earlier status not recorded.',
                ]);
            }
        }
    }
}
