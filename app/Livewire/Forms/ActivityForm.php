<?php

namespace App\Livewire\Forms;

use App\Enums\DatePrecision;
use App\Enums\HistoryKind;
use App\Models\Activity;
use App\Models\HistoryEvent;
use Illuminate\Support\Facades\DB;
use Livewire\Form;

/**
 * New / edit activity. Name and dates are optional because real records sometimes have neither.
 */
class ActivityForm extends Form
{
    public ?Activity $activity = null;

    public string $name = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public string $location = '';

    public string $notes = '';

    public function setActivity(Activity $activity): void
    {
        $this->activity = $activity;
        $this->name = (string) $activity->name;
        $this->starts_on = (string) $activity->starts_on?->toDateString();
        $this->ends_on = (string) $activity->ends_on?->toDateString();
        $this->location = (string) $activity->location;
        $this->notes = (string) $activity->notes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date', 'required_with:ends_on'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'starts_on.required_with' => 'Add the start date before an end date.',
            'ends_on.after_or_equal' => 'The end date can’t be before the start date.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'end date'];
    }

    /**
     * Create the activity and note it in "Recent changes".
     */
    public function store(): Activity
    {
        $this->validate();

        return DB::transaction(function (): Activity {
            $activity = Activity::create($this->attributes());

            HistoryEvent::create([
                'happened_on' => $activity->starts_on ?? today(),
                'date_precision' => DatePrecision::Day,
                'title' => "New activity: {$activity->display_name}",
                'detail' => $activity->date_label,
                'kind' => HistoryKind::Activity,
            ]);

            return $activity;
        });
    }

    public function update(): Activity
    {
        $this->validate();

        $this->activity->update($this->attributes());

        return $this->activity;
    }

    /**
     * @return array<string, string|null>
     */
    private function attributes(): array
    {
        $blankToNull = fn (string $value): ?string => trim($value) === '' ? null : trim($value);

        return [
            'name' => $blankToNull($this->name),
            'starts_on' => $blankToNull($this->starts_on),
            'ends_on' => $blankToNull($this->ends_on),
            'location' => $blankToNull($this->location),
            'notes' => $blankToNull($this->notes),
        ];
    }
}
