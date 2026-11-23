<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Chapter;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state: an active member with a complete birthday.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $birthday = fake()->dateTimeBetween('-22 years', '-11 years');

        return [
            'full_name' => fake()->firstName().' '.fake()->lastName(),
            'nickname' => fake()->firstName(),
            'birth_month' => (int) $birthday->format('n'),
            'birth_day' => (int) $birthday->format('j'),
            'birth_year' => (int) $birthday->format('Y'),
            'grade_level' => fake()->randomElement(['Grade 7', 'Grade 9', 'Grade 11', '1st Year College']),
            'chapter_id' => Chapter::factory(),
            'status' => MemberStatus::Active,
        ];
    }

    public function floating(): static
    {
        return $this->state(['status' => MemberStatus::Floating]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => MemberStatus::Inactive]);
    }

    /**
     * Month and day known, year not recorded.
     */
    public function withoutBirthYear(): static
    {
        return $this->state(['birth_year' => null]);
    }

    /**
     * No birthday recorded at all.
     */
    public function withoutBirthday(): static
    {
        return $this->state(['birth_month' => null, 'birth_day' => null, 'birth_year' => null]);
    }

    public function bornOn(int $year, int $month, int $day): static
    {
        return $this->state(['birth_year' => $year, 'birth_month' => $month, 'birth_day' => $day]);
    }
}
