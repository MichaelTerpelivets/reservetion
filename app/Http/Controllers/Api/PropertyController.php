<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchPropertiesRequest;
use App\Http\Resources\PropertyResource;
use App\Services\PropertySearchService;
use Illuminate\Http\JsonResponse;

class PropertyController extends Controller
{
    public function __construct(
        private readonly PropertySearchService $propertySearchService
    ) {}

    public function index(SearchPropertiesRequest $request): JsonResponse
    {
        $paginator = $this->propertySearchService->search($request->validated());

        return response()->json([
            'data' => PropertyResource::collection($paginator->getCollection())->resolve(),
            'next' => $paginator->nextPageUrl(),
            'prev' => $paginator->previousPageUrl(),
            'per_page' => $paginator->perPage(),
        ]);
    }
}
