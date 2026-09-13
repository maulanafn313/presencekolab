<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ApiListing
{
    /** Existing consumers receive the same array; pagination is explicitly negotiated. */
    public static function get(Builder $query, Request $request): array
    {
        $input = $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,200']);
        $page = $query->paginate($input['per_page'] ?? 50);

        return ['data' => $page->items(), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]];
    }
}
