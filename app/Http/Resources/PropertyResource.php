<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Property
 */
class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bestOffer = $this->bestOfferPayload;

        return [
            'code' => $this->code,
            'name' => $this->name,
            'city' => $this->city,
            'best_offer' => [
                'id' => $bestOffer->id,
                'supplier' => $bestOffer->supplier,
                'price' => $bestOffer->price,
                'currency' => $bestOffer->currency,
                'available_units' => $bestOffer->available_units,
                'expires_at' => Carbon::parse($bestOffer->expires_at)->utc()->toIso8601String(),
            ],
        ];
    }
}
