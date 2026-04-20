<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WarehouseOptionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'selected' => ['nullable', 'array'],
            'selected.*' => ['integer'],
        ]);

        if ($request->filled('selected')) {
            $warehouses = Warehouse::query()
                ->whereIn('id', $request->input('selected'))
                ->get(['id', 'name']);

            return response()->json($warehouses->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
            ]));
        }

        $search = strtolower((string) $request->query('search', ''));

        $rows = Warehouse::query()->select('id', 'name');

        if ($search !== '') {
            $rows->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
        }

        $results = $rows
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json($results->map(fn (Warehouse $warehouse): array => [
            'id' => $warehouse->id,
            'name' => $warehouse->name,
        ]));
    }
}
