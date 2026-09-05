<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\OfferImport;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        $checkIn = now()->addDays(10)->startOfDay();

        return [
            'supplier_id' => Supplier::factory(),
            'property_id' => Property::factory(),
            'offer_import_id' => OfferImport::factory(),
            'external_id' => 'offer-'.$this->faker->unique()->numerify('#####'),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkIn->copy()->addDays(5)->toDateString(),
            'max_guests' => 4,
            'price' => $this->faker->numberBetween(10000, 200000),
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->addDays(7),
        ];
    }
}
