<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ImportStatus;
use App\Jobs\ProcessOfferImportJob;
use App\Models\OfferImport;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportOffersService
{
    /**
     * @param array{
     *     supplier: string,
     *     external_import_id: string,
     *     sent_at: string,
     *     offers: array<int, array<string, mixed>>
     * } $data
     * @throws Throwable
     */
    public function create(array $data): OfferImport
    {
        $supplier = Supplier::query()->where('code', $data['supplier'])->firstOrFail();

        return DB::transaction(function () use ($supplier, $data) {
            $existing = OfferImport::query()
                ->where('supplier_id', $supplier->id)
                ->where('external_import_id', $data['external_import_id'])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $import = OfferImport::query()->create([
                'supplier_id' => $supplier->id,
                'external_import_id' => $data['external_import_id'],
                'sent_at' => $data['sent_at'],
                'status' => ImportStatus::Pending,
                'total_offers' => count($data['offers']),
                'processed_offers' => 0,
                'payload' => $data['offers'],
            ]);

            ProcessOfferImportJob::dispatch($import->id);

            return $import;
        });
    }
}
