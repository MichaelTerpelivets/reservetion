<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\OfferImport;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private function createAvailableOffer(array $overrides = []): Offer
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $property = Property::factory()->create(['code' => 'BCN-0001', 'city' => 'Barcelona']);
        $import = OfferImport::factory()->create(['supplier_id' => $supplier->id]);

        return Offer::factory()->create(array_merge([
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'offer_import_id' => $import->id,
            'available_units' => 1,
            'expires_at' => now()->addDay(),
        ], $overrides));
    }

    public function test_reservation_creates_booking_and_decrements_units(): void
    {
        $offer = $this->createAvailableOffer(['available_units' => 2]);

        $response = $this->postJson('/api/offers/'.$offer->id.'/reservations', [
            'client_reference' => 'web-order-9f782b1c',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.client_reference', 'web-order-9f782b1c')
            ->assertJsonPath('data.offer_id', $offer->id)
            ->assertJsonPath('data.customer_name', 'John Smith')
            ->assertJsonPath('data.customer_email', 'john@example.com');

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
            'client_reference' => 'web-order-9f782b1c',
        ]);

        $this->assertSame(1, $offer->fresh()->available_units);
    }

    public function test_reservation_rejects_when_no_units_left(): void
    {
        $offer = $this->createAvailableOffer(['available_units' => 0]);

        $response = $this->postJson('/api/offers/'.$offer->id.'/reservations', [
            'client_reference' => 'web-order-empty',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_rejects_expired_offer(): void
    {
        $offer = $this->createAvailableOffer([
            'available_units' => 1,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/offers/'.$offer->id.'/reservations', [
            'client_reference' => 'web-order-expired',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['offer']);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_client_reference_must_be_unique(): void
    {
        $offer = $this->createAvailableOffer(['available_units' => 2]);

        Reservation::factory()->create([
            'offer_id' => $offer->id,
            'client_reference' => 'web-order-dup',
        ]);

        $response = $this->postJson('/api/offers/'.$offer->id.'/reservations', [
            'client_reference' => 'web-order-dup',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_reference']);
    }

    public function test_reservation_validation(): void
    {
        $offer = $this->createAvailableOffer();

        $this->postJson('/api/offers/'.$offer->id.'/reservations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_reference', 'customer_name', 'customer_email']);
    }
}
