<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Member;

class ActivitySeeder extends JsonSeeder
{
    /**
     * Seed activities and their present/excused attendance rows. Absent members have no row.
     * Run after MemberSeeder.
     */
    public function run(): void
    {
        $activityIds = collect();

        foreach ($this->data('activities.json') as $record) {
            $activity = Activity::create([
                'name' => $record['name'],
                'starts_on' => $record['starts_on'],
                'ends_on' => $record['ends_on'],
            ]);

            $activityIds->put($record['key'], $activity->id);
        }

        $memberIds = Member::pluck('id', 'full_name');

        foreach ($this->data('attendance.json') as $record) {
            Attendance::create([
                'activity_id' => $this->idFor($activityIds, $record['activity'], 'activity'),
                'member_id' => $this->idFor($memberIds, $record['member'], 'member'),
                'status' => $record['status'],
            ]);
        }
    }
}
