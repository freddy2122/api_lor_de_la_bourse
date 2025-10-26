<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class StatsController extends Controller
{
    // GET /api/admin/stats
    public function summary(Request $request)
    {
        return Cache::remember('admin.stats.summary', now()->addSeconds(30), function () {
            $now = Carbon::now();
            $yesterday = $now->copy()->subDay();

            $newClients24h = 0;
            $kycPending = 0;
            $pendingOrders = 0;
            $dayVolume = 0;

            // Users created in last 24h with role=client
            if (Schema::hasTable('users')) {
                try {
                    $newClients24h = DB::table('users')
                        ->when(Schema::hasColumn('users', 'role'), function ($q) {
                            $q->where('role', 'client');
                        })
                        ->where('created_at', '>=', $yesterday)
                        ->count();
                } catch (\Throwable $e) {
                    $newClients24h = 0;
                }
            }

            // KYC pending in account_opening_requests
            if (Schema::hasTable('account_opening_requests')) {
                try {
                    $kycPending = DB::table('account_opening_requests')
                        ->when(Schema::hasColumn('account_opening_requests', 'status'), function ($q) {
                            $q->where('status', 'pending');
                        })
                        ->count();
                } catch (\Throwable $e) {
                    $kycPending = 0;
                }
            }

            // Orders pending (best-effort if table exists)
            if (Schema::hasTable('orders')) {
                try {
                    $pendingOrders = DB::table('orders')
                        ->when(Schema::hasColumn('orders', 'status'), function ($q) {
                            $q->where('status', 'pending');
                        }, function ($q) {
                            // no status column => assume all are pending-like
                        })
                        ->count();
                } catch (\Throwable $e) {
                    $pendingOrders = 0;
                }
            }

            // Day volume (sum executed today) if orders table has amount + executed_at
            if (Schema::hasTable('orders')) {
                try {
                    $dayVolume = DB::table('orders')
                        ->when(Schema::hasColumn('orders', 'executed_at'), function ($q) use ($now) {
                            $q->whereDate('executed_at', $now->toDateString());
                        }, function ($q) use ($now) {
                            // fallback use created_at
                            if (Schema::hasColumn('orders', 'created_at')) {
                                $q->whereDate('created_at', $now->toDateString());
                            }
                        })
                        ->when(Schema::hasColumn('orders', 'amount'), function ($q) {
                            $q->selectRaw('COALESCE(SUM(amount),0) as total');
                        }, function ($q) {
                            // No amount column, return 0
                            $q->selectRaw('0 as total');
                        })
                        ->value('total') ?? 0;
                } catch (\Throwable $e) {
                    $dayVolume = 0;
                }
            }

            return response()->json([
                'new_clients_24h' => (int) $newClients24h,
                'kyc_pending_count' => (int) $kycPending,
                'pending_orders_count' => (int) $pendingOrders,
                'day_volume' => (float) $dayVolume,
            ]);
        });
    }
}
