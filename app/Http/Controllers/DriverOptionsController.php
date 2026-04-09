<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DriverOptionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'selected' => ['nullable', 'array'],
            'selected.*' => ['integer'],
        ]);

        if ($request->filled('selected')) {
            $drivers = Driver::query()
                ->whereIn('id', $request->input('selected'))
                ->get(['id', 'company', 'phone', 'email']);

            return response()->json($drivers->map(fn (Driver $driver): array => [
                'id' => $driver->id,
                'name' => $driver->company ?: ($driver->phone ?: ($driver->email ?: ('Driver #'.$driver->id))),
            ]));
        }

        $search = strtolower((string) $request->query('search', ''));

        $rows = Driver::query()->select('id', 'company', 'phone', 'email');

        if ($search !== '') {
            $rows->where(function ($query) use ($search): void {
                $query->whereRaw('LOWER(company) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(phone) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$search.'%']);
            });
        }

        $results = $rows
            ->orderBy('company')
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json($results->map(fn (Driver $driver): array => [
            'id' => $driver->id,
            'name' => $driver->company ?: ($driver->phone ?: ($driver->email ?: ('Driver #'.$driver->id))),
        ]));
    }
}
