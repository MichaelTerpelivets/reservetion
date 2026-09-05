<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\OfferImport;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySearchTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplierA;

    private Supplier $supplierB;

    private OfferImport $import;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplierA = Supplier::factory()->create(['code' => 'supplier-a', 'name' => 'Supplier A']);
        $this->supplierB = Supplier::factory()->create(['code' => 'supplier-b', 'name' => 'Supplier B']);
        $this->import = OfferImport::factory()->create(['supplier_id' => $this->supplierA->id]);
    }

    private function createOffer(array $attributes = []): Offer
    {
        $property = isset($attributes['property_id'])
            ? Property::query()->findOrFail($attributes['property_id'])
            : Property::factory()->create([
                'code' => $attributes['property_code'] ?? 'BCN-0001',
                'name' => $attributes['property_name'] ?? 'Apartment near Sagrada Familia',
                'city' => $attributes['city'] ?? 'Barcelona',
            ]);

        unset($attributes['property_code'], $attributes['property_name'], $attributes['city']);

        return Offer::factory()->create(array_merge([
            'supplier_id' => $this->supplierA->id,
            'property_id' => $property->id,
            'offer_import_id' => $this->import->id,
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 72500,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->addDays(3),
        ], $attributes));
    }

    public function test_search_requires_query_parameters(): void
    {
        $this->getJson('/api/properties')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['check_in', 'check_out', 'guests']);
    }

    public function test_search_returns_cheapest_offer_per_property(): void
    {
        $property = Property::factory()->create([
            'code' => 'BCN-0001',
            'name' => 'Apartment near Sagrada Familia',
            'city' => 'Barcelona',
        ]);

        $this->createOffer([
            'property_id' => $property->id,
            'external_id' => 'offer-expensive',
            'price' => 90000,
        ]);

        $cheap = $this->createOffer([
            'property_id' => $property->id,
            'supplier_id' => $this->supplierB->id,
            'external_id' => 'offer-cheap',
            'price' => 50000,
        ]);

        $response = $this->getJson('/api/properties?city=Barcelona&check_in=2026-10-10&check_out=2026-10-15&guests=2&page=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCN-0001')
            ->assertJsonPath('data.0.best_offer.id', $cheap->id)
            ->assertJsonPath('data.0.best_offer.price', 50000)
            ->assertJsonPath('data.0.best_offer.supplier', 'supplier-b')
            ->assertJsonStructure([
                'data',
                'next',
                'prev',
                'per_page',
            ]);
    }

    public function test_search_excludes_expired_zero_units_and_insufficient_guests(): void
    {
        $this->createOffer([
            'external_id' => 'expired',
            'expires_at' => now()->subHour(),
            'price' => 1000,
        ]);

        $this->createOffer([
            'property_code' => 'BCN-0002',
            'external_id' => 'no-units',
            'available_units' => 0,
            'price' => 2000,
        ]);

        $this->createOffer([
            'property_code' => 'BCN-0003',
            'external_id' => 'too-small',
            'max_guests' => 1,
            'price' => 3000,
        ]);

        $valid = $this->createOffer([
            'property_code' => 'BCN-0004',
            'external_id' => 'valid',
            'price' => 4000,
            'max_guests' => 4,
            'available_units' => 1,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/properties?check_in=2026-10-10&check_out=2026-10-15&guests=2');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCN-0004')
            ->assertJsonPath('data.0.best_offer.id', $valid->id);
    }

    public function test_search_filters_by_city(): void
    {
        $this->createOffer([
            'property_code' => 'BCN-0001',
            'city' => 'Barcelona',
            'external_id' => 'bcn',
        ]);

        $this->createOffer([
            'property_code' => 'MAD-0001',
            'city' => 'Madrid',
            'external_id' => 'mad',
        ]);

        $response = $this->getJson('/api/properties?city=Madrid&check_in=2026-10-10&check_out=2026-10-15&guests=2');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.city', 'Madrid')
            ->assertJsonPath('data.0.code', 'MAD-0001');
    }

    public function test_search_pagination_fields(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createOffer([
                'property_code' => 'BCN-000'.$i,
                'external_id' => 'offer-'.$i,
                'price' => 10000 * $i,
            ]);
        }

        $page1 = $this->getJson('/api/properties?check_in=2026-10-10&check_out=2026-10-15&guests=2&page=1&per_page=2');
        $page1->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('prev', null);
        $this->assertNotNull($page1->json('next'));

        $page2 = $this->getJson('/api/properties?check_in=2026-10-10&check_out=2026-10-15&guests=2&page=2&per_page=2');
        $page2->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('next', null);
        $this->assertNotNull($page2->json('prev'));
    }
}
