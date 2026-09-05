<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('???-####')),
            'name' => $this->faker->sentence(3),
            'city' => $this->faker->city(),
        ];
    }
}
