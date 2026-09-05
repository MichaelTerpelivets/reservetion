<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessOfferImportJob;
use App\Models\OfferImport;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Throwable;

class ImportOffersTest extends TestCase
{
    use RefreshDatabase;

    private function importPayload(array $overrides = []): array
    {
        return array_merge([
            'supplier' => 'supplier-a',
            'external_import_id' => 'import-2026-09-01-001',
            'sent_at' => '2026-09-01T10:00:00Z',
            'offers' => [
                [
                    'external_id' => 'offer-a-10001',
                    'property' => [
                        'code' => 'BCN-0001',
                        'name' => 'Apartment near Sagrada Familia',
                        'city' => 'Barcelona',
                    ],
                    'check_in' => '2026-10-10',
                    'check_out' => '2026-10-15',
                    'max_guests' => 4,
                    'price' => 72500,
                    'currency' => 'EUR',
                    'available_units' => 2,
                    'expires_at' => '2026-09-10T23:59:59Z',
                ],
            ],
        ], $overrides);
    }

    public function test_import_requires_valid_payload_and_existing_supplier(): void
    {
        $response = $this->postJson('/api/imports', [
            'supplier' => 'unknown-supplier',
            'external_import_id' => 'import-1',
            'sent_at' => '2026-09-01T10:00:00Z',
            'offers' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['supplier', 'offers']);
    }

    public function test_import_returns_202_and_dispatches_job(): void
    {
        Queue::fake();
        Supplier::factory()->create(['code' => 'supplier-a', 'name' => 'Supplier A']);

        $response = $this->postJson('/api/imports', $this->importPayload());

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseCount('offer_imports', 1);
        Queue::assertPushed(ProcessOfferImportJob::class);
    }

    public function test_duplicate_import_is_idempotent_and_does_not_redispatch_job(): void
    {
        Queue::fake();
        Supplier::factory()->create(['code' => 'supplier-a', 'name' => 'Supplier A']);

        $payload = $this->importPayload();

        $first = $this->postJson('/api/imports', $payload);
        $first->assertStatus(202);
        $importId = $first->json('data.id');

        Queue::assertPushed(ProcessOfferImportJob::class, 1);

        $second = $this->postJson('/api/imports', $payload);
        $second->assertStatus(202)
            ->assertJsonPath('data.id', $importId);

        $this->assertDatabaseCount('offer_imports', 1);
        Queue::assertPushed(ProcessOfferImportJob::class, 1);
    }

    public function test_job_creates_property_and_offers_and_completes_import(): void
    {
        Supplier::factory()->create(['code' => 'supplier-a', 'name' => 'Supplier A']);

        $response = $this->postJson('/api/imports', $this->importPayload());
        $response->assertStatus(202);

        $import = OfferImport::query()->firstOrFail();
        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(1, $import->processed_offers);
        $this->assertNotNull($import->completed_at);
        $this->assertNull($import->payload);

        $this->assertDatabaseHas('properties', [
            'code' => 'BCN-0001',
            'city' => 'Barcelona',
        ]);

        $this->assertDatabaseHas('offers', [
            'external_id' => 'offer-a-10001',
            'price' => 72500,
            'available_units' => 2,
        ]);
    }

    public function test_existing_offer_is_updated_by_later_import(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a', 'name' => 'Supplier A']);

        $this->postJson('/api/imports', $this->importPayload())->assertStatus(202);

        $this->postJson('/api/imports', $this->importPayload([
            'external_import_id' => 'import-2026-09-01-002',
            'offers' => [
                [
                    'external_id' => 'offer-a-10001',
                    'property' => [
                        'code' => 'BCN-0001',
                        'name' => 'Apartment near Sagrada Familia',
                        'city' => 'Barcelona',
                    ],
                    'check_in' => '2026-10-10',
                    'check_out' => '2026-10-15',
                    'max_guests' => 4,
                    'price' => 65000,
                    'currency' => 'EUR',
                    'available_units' => 1,
                    'expires_at' => '2026-09-11T23:59:59Z',
                ],
            ],
        ]))->assertStatus(202);

        $this->assertDatabaseCount('offers', 1);
        $this->assertDatabaseHas('offers', [
            'supplier_id' => $supplier->id,
            'external_id' => 'offer-a-10001',
            'price' => 65000,
            'available_units' => 1,
        ]);
    }

    public function test_show_import_returns_status_payload(): void
    {
        Supplier::factory()->create(['code' => 'supplier-a', 'name' => 'Supplier A']);

        $create = $this->postJson('/api/imports', $this->importPayload());
        $importId = $create->json('data.id');

        $response = $this->getJson('/api/imports/'.$importId);

        $response->assertOk()
            ->assertJsonPath('data.id', $importId)
            ->assertJsonPath('data.supplier', 'supplier-a')
            ->assertJsonPath('data.external_import_id', 'import-2026-09-01-001')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_offers', 1)
            ->assertJsonPath('data.processed_offers', 1)
            ->assertJsonPath('data.error', null);
    }

    public function test_job_skips_when_import_is_not_pending(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $import = OfferImport::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => ImportStatus::Completed,
            'payload' => [
                [
                    'external_id' => 'offer-skipped',
                    'property' => [
                        'code' => 'BCN-0099',
                        'name' => 'Skipped',
                        'city' => 'Barcelona',
                    ],
                    'check_in' => '2026-10-10',
                    'check_out' => '2026-10-15',
                    'max_guests' => 2,
                    'price' => 1000,
                    'currency' => 'EUR',
                    'available_units' => 1,
                    'expires_at' => now()->addDay()->toIso8601String(),
                ],
            ],
        ]);

        (new ProcessOfferImportJob($import->id))->handle();

        $this->assertDatabaseCount('offers', 0);
        $this->assertSame(ImportStatus::Completed, $import->fresh()->status);
    }

    public function test_job_marks_import_failed_when_payload_is_invalid(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $import = OfferImport::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => ImportStatus::Pending,
            'payload' => [
                ['external_id' => 'broken-offer'],
            ],
        ]);

        try {
            (new ProcessOfferImportJob($import->id))->handle();
            $this->fail('Expected the job to throw after marking the import as failed.');
        } catch (Throwable) {
        }

        $import->refresh();

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertNotNull($import->error);
        $this->assertNotNull($import->completed_at);
    }
}
