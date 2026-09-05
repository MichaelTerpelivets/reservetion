<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class ReservationService
{
    /**
     * @param array{
     *     client_reference: string,
     *     customer_name: string,
     *     customer_email: string
     * } $data
     * @throws Throwable
     */
    public function reserve(Offer $offer, array $data): Reservation
    {
        try {
            return DB::transaction(function () use ($offer, $data) {
                /** @var Offer|null $lockedOffer */
                $lockedOffer = Offer::query()
                    ->whereKey($offer->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedOffer === null) {
                    throw ValidationException::withMessages([
                        'offer' => ['Offer not found.'],
                    ]);
                }

                if ($lockedOffer->expires_at->isPast()) {
                    throw ValidationException::withMessages([
                        'offer' => ['Offer has expired.'],
                    ]);
                }

                if ($lockedOffer->available_units < 1) {
                    throw new ConflictHttpException('No available units left for this offer.');
                }

                $lockedOffer->decrement('available_units');

                return Reservation::query()->create([
                    'offer_id' => $lockedOffer->id,
                    'client_reference' => $data['client_reference'],
                    'customer_name' => $data['customer_name'],
                    'customer_email' => $data['customer_email'],
                ]);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'client_reference' => ['The client reference has already been taken.'],
                ]);
            }

            throw $e;
        }
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;

        return $sqlState === '23000' || $driverCode === 1062 || $driverCode === 19;
    }
}
