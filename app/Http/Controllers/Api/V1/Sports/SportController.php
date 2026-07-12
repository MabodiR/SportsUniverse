<?php

namespace App\Http\Controllers\Api\V1\Sports;

use App\Domain\Sports\Models\Sport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class SportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return JsonResource::collection(Sport::query()->where('is_active', true)->with(['positions' => fn ($q) => $q->where('is_active', true)->orderBy('name')])->orderBy('sort_order')->orderBy('name')->get());
    }
}
