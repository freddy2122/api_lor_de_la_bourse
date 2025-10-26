<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketDataMockController extends Controller
{
    // GET /api/market/quotes?symbols=ABC,DEF
    public function quotes(Request $request)
    {
        $symbols = collect(explode(',', (string) $request->query('symbols', 'BRVM10')))
            ->map(fn($s) => strtoupper(trim($s)))
            ->filter();

        $now = now()->toIso8601String();
        $data = $symbols->map(function ($sym) use ($now) {
            $price = round(mt_rand(1000, 2000) / 10, 2); // 100.00 - 200.00
            $change = round(mt_rand(-50, 50) / 100, 2); // -0.50 - 0.50
            $percent = $price ? round(($change / ($price - $change)) * 100, 2) : 0;
            return [
                'symbol' => $sym,
                'price' => $price,
                'change' => $change,
                'percent' => $percent,
                'time' => $now,
            ];
        })->values();

        return response()->json([
            'source' => 'mock',
            'realtime' => true,
            'data' => $data,
        ]);
    }

    // GET /api/market/stream?symbols=ABC,DEF  (Server-Sent Events)
    public function stream(Request $request)
    {
        $symbols = collect(explode(',', (string) $request->query('symbols', 'BRVM10')))
            ->map(fn($s) => strtoupper(trim($s)))
            ->filter();

        $response = new StreamedResponse(function () use ($symbols) {
            // Send headers for SSE
            echo ": ok\n\n"; // comment to initialize
            @ob_flush(); @flush();

            // Keep state per symbol
            $state = [];
            foreach ($symbols as $sym) {
                $state[$sym] = [
                    'price' => round(mt_rand(1000, 2000) / 10, 2),
                ];
            }

            // Stream loop (limit to ~2 minutes for demo)
            $start = time();
            while (!connection_aborted() && (time() - $start) < 120) {
                $now = now()->toIso8601String();
                $updates = [];
                foreach ($symbols as $sym) {
                    $prev = $state[$sym]['price'];
                    $delta = round(mt_rand(-10, 10) / 100, 2); // -0.10..0.10
                    $price = max(1, round($prev + $delta, 2));
                    $change = round($price - $prev, 2);
                    $percent = $prev ? round(($change / $prev) * 100, 2) : 0;
                    $state[$sym]['price'] = $price;
                    $updates[] = [
                        'symbol' => $sym,
                        'price' => $price,
                        'change' => $change,
                        'percent' => $percent,
                        'time' => $now,
                    ];
                }
                $payload = json_encode(['source' => 'mock', 'realtime' => true, 'data' => $updates]);
                echo "event: quotes\n";
                echo "data: {$payload}\n\n";
                @ob_flush(); @flush();
                usleep(900000); // ~0.9s
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no'); // for nginx
        return $response;
    }
}
