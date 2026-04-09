<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shipper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShipperOptionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'selected' => ['nullable', 'array'],
            'selected.*' => ['integer'],
        ]);

        if ($request->filled('selected')) {
            $shippers = Shipper::query()
                ->with('user:id,name')
                ->whereIn('id', $request->input('selected'))
                ->get(['id', 'user_id', 'company_name']);

            return response()->json($shippers->map(fn ($s) => [
                'id' => $s->id,
                'name' => ($s->company_name ?: $s->user?->name).($s->company_name && $s->user?->name ? " ({$s->user->name})" : ''),
            ]));
        }

        $search = strtolower((string) $request->query('search', ''));

        $rows = Shipper::query()
            ->with('user:id,name')
            ->join('users', 'shippers.user_id', '=', 'users.id')
            ->select('shippers.id', 'shippers.user_id', 'shippers.company_name');

        if ($search !== '') {
            $rows->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(users.name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(shippers.company_name) LIKE ?', ['%'.$search.'%']);
            });
        }

        $results = $rows->limit(20)->get();

        return response()->json($results->map(fn ($s) => [
            'id' => $s->id,
            'name' => ($s->company_name ?: $s->user?->name).($s->company_name && $s->user?->name ? " ({$s->user->name})" : ''),
        ]));
    }
}
