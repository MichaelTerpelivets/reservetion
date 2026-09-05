<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OfferImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OfferImport
 */
class OfferImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier' => $this->supplier->code,
            'external_import_id' => $this->external_import_id,
            'sent_at' => $this->sent_at?->utc()->toIso8601String(),
            'status' => $this->status->value,
            'total_offers' => $this->total_offers,
            'processed_offers' => $this->processed_offers,
            'error' => $this->error,
            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'completed_at' => $this->completed_at?->utc()->toIso8601String(),
        ];
    }
}
