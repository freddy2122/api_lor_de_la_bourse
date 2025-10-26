<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrdersController extends Controller
{
    // GET /api/admin/orders?status=pending
    public function index(Request $request)
    {
        if (!Schema::hasTable('orders')) {
            return response()->json([]);
        }

        $status = $request->query('status');

        try {
            $query = DB::table('orders');
            if ($status && Schema::hasColumn('orders', 'status')) {
                $query->where('status', $status);
            }

            // Select common fields if they exist
            $select = [];
            $columns = Schema::getColumnListing('orders');
            $map = [
                'id' => 'id',
                'client_name' => 'client_name',
                'type' => 'type',
                'ticker' => 'ticker',
                'amount' => 'amount',
                'created_at' => 'created_at',
            ];
            foreach ($map as $alias => $col) {
                if (in_array($col, $columns, true)) {
                    $select[] = $col . ' as ' . $alias;
                }
            }
            if (empty($select)) {
                // Fallback minimal select
                $select = ['id'];
            }
            $query->selectRaw(implode(',', $select));
            $query->orderBy('created_at', 'desc');

            $rows = $query->limit(20)->get();
            return response()->json($rows);
        } catch (\Throwable $e) {
            return response()->json([]);
        }
    }
}
