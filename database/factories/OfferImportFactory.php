<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\OfferImport;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferImport>
 */
class OfferImportFactory extends Factory
{
    protected $model = OfferImport::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'external_import_id' => 'import-'.$this->faker->unique()->uuid(),
            'sent_at' => now(),
            'status' => ImportStatus::Pending,
            'total_offers' => 0,
            'processed_offers' => 0,
            'error' => null,
            'payload' => [],
            'completed_at' => null,
        ];
    }
}
