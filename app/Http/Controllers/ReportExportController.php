<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Activity;
use App\Models\Member;
use App\Models\Position;
use App\Services\AttendanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Spreadsheet (CSV) downloads from the Reports page. They open in Excel and Google Sheets.
 *
 * Private notes are never included. Downloads are for signed-in, authorized users only and are
 * never cached.
 */
class ReportExportController extends Controller
{
    /**
     * Every member with their details and attendance in the chosen period.
     */
    public function members(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Member::class);

        $report = AttendanceReport::forYear($this->year($request));
        $rows = collect($report->memberRows(lowestFirst: false))->sortBy(fn (array $row) => $row['member']->full_name);

        $lines = [[
            'Member no.', 'Full name', 'Nickname', 'Status', 'Positions', 'Chapter', 'Grade level',
            'Birthday', 'Age', 'Date joined', 'Attended', 'Required', 'Excused', 'Attendance rate',
        ]];

        foreach ($rows as $row) {
            $member = $row['member'];
            $lines[] = [
                $member->member_no,
                $member->full_name,
                $member->nickname,
                $member->status->label(),
                $member->currentPositions()->map(fn (Position $position) => $position->code)->implode(', '),
                $member->chapter?->name,
                $member->grade_level,
                $member->shortBirthdayLabel(),
                $member->age ?? ($member->hasBirthday() ? 'Birth year missing' : null),
                $member->joined_on?->format('M j, Y'),
                $row['attended'],
                $row['required'],
                $row['excused'],
                $row['rate'] === null ? null : round($row['rate'] * 100).'%',
            ];
        }

        return $this->csv($lines, 'members', $report->year);
    }

    /**
     * One row per member, one column per activity: Present, Excused, Absent or Not required.
     */
    public function attendance(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Member::class);
        Gate::authorize('viewAny', Activity::class);

        $report = AttendanceReport::forYear($this->year($request));
        $activities = $report->activities()->reverse()->values();
        $grid = $report->grid();

        $lines = [[
            'Member', 'Status',
            ...$activities->map(fn (Activity $activity) => $activity->display_name.' ('.$activity->date_label.')'),
        ]];

        foreach (Member::query()->orderBy('full_name')->get() as $member) {
            $lines[] = [
                $member->full_name,
                $member->status->label(),
                ...$activities->map(fn (Activity $activity) => ($grid[$member->id][$activity->id] ?? AttendanceStatus::Absent)->label()),
            ];
        }

        return $this->csv($lines, 'attendance', $report->year);
    }

    private function year(Request $request): ?int
    {
        $year = $request->integer('year');

        return in_array($year, AttendanceReport::years(), true) ? $year : null;
    }

    /**
     * @param  list<list<string|int|float|null>>  $lines
     */
    private function csv(array $lines, string $name, ?int $year): StreamedResponse
    {
        $filename = 'tropang-potchi-'.$name.'-'.($year ?? 'all-time').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($lines): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // Byte-order mark so Excel reads names like "Cañas" correctly.
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($lines as $line) {
                fputcsv($out, array_map($this->safeCell(...), $line), escape: '');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Spreadsheets run cells that start with = + - @ as formulas; prefix those so they stay text.
     */
    private function safeCell(string|int|float|null $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
