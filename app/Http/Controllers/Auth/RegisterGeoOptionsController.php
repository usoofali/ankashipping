<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RegisterGeoOptionsController extends Controller
{
    public function countries(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'selected' => ['nullable', 'integer'],
        ]);

        if ($request->filled('selected')) {
            $country = Country::query()
                ->whereKey($request->integer('selected'))
                ->first(['id', 'name']);

            return response()->json($country ? [$country->toArray()] : []);
        }

        $search = strtolower((string) $request->query('search', ''));

        $rows = Country::query()
            ->orderBy('name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
            })
            ->get(['id', 'name']);

        return response()->json($rows);
    }

    public function states(Request $request): JsonResponse
    {
        $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'selected' => ['nullable', 'integer'],
        ]);

        $countryId = (int) $request->input('country_id');

        if ($request->filled('selected')) {
            $state = State::query()
                ->where('country_id', $countryId)
                ->whereKey($request->integer('selected'))
                ->first(['id', 'name']);

            return response()->json($state ? [$state->toArray()] : []);
        }

        $search = strtolower((string) $request->query('search', ''));

        $rows = State::query()
            ->where('country_id', $countryId)
            ->orderBy('name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
            })
            ->get(['id', 'name']);

        return response()->json($rows);
    }

    public function cities(Request $request): JsonResponse
    {
        $request->validate([
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'selected' => ['nullable', 'integer'],
        ]);

        $stateId = (int) $request->input('state_id');

        if ($request->filled('selected')) {
            $city = City::query()
                ->where('state_id', $stateId)
                ->whereKey($request->integer('selected'))
                ->first(['id', 'name']);

            return response()->json($city ? [$city->toArray()] : []);
        }

        $search = strtolower((string) $request->query('search', ''));

        $rows = City::query()
            ->where('state_id', $stateId)
            ->orderBy('name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
            })
            ->get(['id', 'name']);

        return response()->json($rows);
    }
}
