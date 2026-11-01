<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'code' => 'GRP-'.$this->faker->unique()->numerify('#####'),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'leader_member_id' => null,
            'is_active' => true,
        ];
    }
}
