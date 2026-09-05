<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $code = 'supplier-'.$this->faker->unique()->slug(2);

        return [
            'code' => $code,
            'name' => $this->faker->company(),
        ];
    }
}
