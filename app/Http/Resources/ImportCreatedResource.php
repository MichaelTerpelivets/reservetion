<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OfferImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OfferImport
 */
class ImportCreatedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
        ];
    }
}
