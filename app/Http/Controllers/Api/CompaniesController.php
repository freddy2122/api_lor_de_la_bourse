<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Societe;

class CompaniesController extends Controller
{
    // GET /api/market/companies?q=son&sector=Telecom&country=SN&per_page=20
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $sector = trim((string) $request->query('sector', ''));
        $country = trim((string) $request->query('country', ''));
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        $query = Societe::query();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%$q%")
                    ->orWhere('symbol', 'like', "%$q%")
                    ->orWhere('slug', 'like', "%$q%");
            });
        }
        if ($sector !== '') $query->where('sector', 'like', "%$sector%" );
        if ($country !== '') $query->where('country', 'like', "%$country%" );

        $paginator = $query->orderBy('name')->paginate($perPage)->appends($request->query());
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    // GET /api/market/companies/{id}
    // id peut être un slug ou un symbol
    public function show(string $id)
    {
        $soc = Societe::where('slug', $id)->orWhere('symbol', $id)->first();
        if (!$soc) return response()->json(['message' => 'Not found'], 404);
        return response()->json($soc);
    }
}
