<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\Offer;
use App\Models\OfferImport;
use App\Models\Property;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessOfferImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $importId) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $claimed = DB::transaction(function () {
            $import = OfferImport::query()
                ->lockForUpdate()
                ->find($this->importId);

            if ($import === null || $import->status !== ImportStatus::Pending) {
                return null;
            }

            $import->update([
                'status' => ImportStatus::Processing,
            ]);

            return $import;
        });

        if ($claimed === null) {
            return;
        }

        try {
            $offers = $claimed->payload ?? [];

            foreach ($offers as $offerData) {
                $property = Property::query()->updateOrCreate(
                    ['code' => $offerData['property']['code']],
                    [
                        'name' => $offerData['property']['name'],
                        'city' => $offerData['property']['city'],
                    ]
                );

                Offer::query()->updateOrCreate(
                    [
                        'supplier_id' => $claimed->supplier_id,
                        'external_id' => $offerData['external_id'],
                    ],
                    [
                        'property_id' => $property->id,
                        'offer_import_id' => $claimed->id,
                        'check_in' => $offerData['check_in'],
                        'check_out' => $offerData['check_out'],
                        'max_guests' => $offerData['max_guests'],
                        'price' => $offerData['price'],
                        'currency' => strtoupper($offerData['currency']),
                        'available_units' => $offerData['available_units'],
                        'expires_at' => $offerData['expires_at'],
                    ]
                );

                $claimed->increment('processed_offers');
            }

            $claimed->update([
                'status' => ImportStatus::Completed,
                'completed_at' => now(),
                'error' => null,
                'payload' => null,
            ]);
        } catch (Throwable $e) {
            $claimed->update([
                'status' => ImportStatus::Failed,
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
