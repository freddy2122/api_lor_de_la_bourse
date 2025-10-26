<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Models\MarketIndex;

class MarketController extends Controller
{
    // GET /api/market/top-movers?symbols=AAPL,MSFT,GOOGL
    public function topMovers(Request $request)
    {
        if (config('services.market.source') === 'scraper') {
            $rows = Cache::remember('market.top_movers.scraper', now()->addSeconds(45), function () {
                $instruments = Instrument::with(['latestQuote'])->get();
                $items = $instruments->map(function ($inst) {
                    $q = $inst->latestQuote;
                    if (!$q) { return null; }
                    return [
                        'symbol' => $inst->ticker,
                        'name' => $inst->name,
                        'changePct' => (float) ($q->change_pct ?? 0),
                    ];
                })->filter()->values();
                if ($items->isEmpty()) { return []; }
                return $items->sortByDesc(fn($r) => abs($r['changePct']))->take(5)->values()->all();
            });
            if (!empty($rows)) { return response()->json($rows); }
        }

        $symbolsParam = (string) $request->query('symbols', 'AAPL,MSFT,GOOGL');
        $symbols = collect(explode(',', $symbolsParam))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->take(5)
            ->values()
            ->all();

        $cacheKey = 'market.top_movers:' . implode(',', $symbols);
        return Cache::remember($cacheKey, now()->addSeconds(45), function () use ($symbols) {
            $apiBase = config('services.alphavantage.base', env('ALPHA_VANTAGE_BASE', 'https://www.alphavantage.co/query'));
            $apiKey = config('services.alphavantage.key', env('ALPHA_VANTAGE_KEY'));

            if (!$apiKey) {
                // Fallback mock if no key configured
                return response()->json([
                    ['symbol' => 'SONATEL', 'name' => 'Sonatel', 'changePct' => 1.45],
                    ['symbol' => 'TOTALCI', 'name' => 'Total CI', 'changePct' => -0.24],
                    ['symbol' => 'ECOBANK',  'name' => 'Ecobank',  'changePct' => 2.10],
                ]);
            }

            $results = [];
            foreach ($symbols as $sym) {
                try {
                    $res = Http::timeout(8)->get($apiBase, [
                        'function' => 'GLOBAL_QUOTE',
                        'symbol' => $sym,
                        'apikey' => $apiKey,
                    ]);
                    if ($res->ok()) {
                        $data = $res->json();
                        $q = $data['Global Quote'] ?? null;
                        if ($q) {
                            $pctStr = $q['10. change percent'] ?? '0%';
                            $pct = (float) str_replace('%', '', $pctStr);
                            $results[] = [
                                'symbol' => $q['01. symbol'] ?? $sym,
                                'name' => $q['01. symbol'] ?? $sym,
                                'changePct' => $pct,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore individual failures
                }
            }

            if (empty($results)) {
                // graceful fallback
                $results = [
                    ['symbol' => 'SONATEL', 'name' => 'Sonatel', 'changePct' => 1.45],
                    ['symbol' => 'TOTALCI', 'name' => 'Total CI', 'changePct' => -0.24],
                    ['symbol' => 'ECOBANK',  'name' => 'Ecobank',  'changePct' => 2.10],
                ];
            }

            return response()->json($results);
        });
    }

    // GET /api/market/summary
    public function summary(Request $request, \App\Services\BrvmScraperService $scraper)
    {
        $payload = Cache::remember('market.summary', now()->addSeconds(120), function () use ($scraper) {
            $lastRefresh = Cache::get('brvm:last_refresh');
            $indices = MarketIndex::get()->map(function ($m) {
                return [
                    'symbol' => $m->code,
                    'name' => $m->name,
                    'value' => is_null($m->last_value) ? null : (float) $m->last_value,
                    'changePct' => is_null($m->change_pct) ? null : (float) $m->change_pct,
                ];
            })->values()->all();

            $home = $scraper->fetchHomepageSummary();
            return [
                'transactions_value_fcfa' => $home['transactions_value_fcfa'] ?? null,
                'cap_actions_fcfa' => $home['cap_actions_fcfa'] ?? null,
                'cap_obligations_fcfa' => $home['cap_obligations_fcfa'] ?? null,
                'indices' => $indices,
                'last_update' => $home['last_update'] ?? $lastRefresh,
            ];
        });

        return response()->json($payload);
    }

    // GET /api/market/indices
    public function indices(Request $request)
    {
        if (config('services.market.source') === 'scraper') {
            return Cache::remember('market.indices.scraper', now()->addSeconds(60), function () {
                $rows = MarketIndex::orderBy('code')->get()->map(function ($m) {
                    return [
                        'symbol' => $m->code,
                        'name' => $m->name,
                        'changePct' => (float) ($m->change_pct ?? 0),
                    ];
                })->values()->all();
                if (empty($rows)) {
                    return [
                        ['symbol' => 'BRVM-Composite', 'name' => 'BRVM Composite', 'changePct' => 0.52],
                        ['symbol' => 'BRVM-30', 'name' => 'BRVM 30', 'changePct' => 0.31],
                        ['symbol' => 'BRVM-Agri', 'name' => 'BRVM Agriculture', 'changePct' => -0.14],
                    ];
                }
                return $rows;
            });
        }

        // Cache brièvement; indices changent moins fréquemment
        return Cache::remember('market.indices', now()->addSeconds(60), function () {
            $key = config('services.alphavantage.key', env('ALPHA_VANTAGE_KEY'));
            // If a real BRVM source exists later, replace this with the provider call
            $indices = [
                ['symbol' => 'BRVM-Composite', 'name' => 'BRVM Composite', 'changePct' => 0.52],
                ['symbol' => 'BRVM-30', 'name' => 'BRVM 30', 'changePct' => 0.31],
                ['symbol' => 'BRVM-Agri', 'name' => 'BRVM Agriculture', 'changePct' => -0.14],
            ];
            return response()->json($indices);
        });
    }

    // GET /api/market/quotes-list?symbols=AAPL,MSFT,GOOGL
    public function quotesList(Request $request)
    {
        if (config('services.market.source') === 'scraper') {
            $symbolsParam = (string) $request->query('symbols', '');
            $symbols = collect(explode(',', $symbolsParam))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->take(20)
                ->values()
                ->all();

            $cacheKey = 'market.quotes_list.scraper:' . implode(',', $symbols);
            $rows = Cache::remember($cacheKey, now()->addSeconds(45), function () use ($symbols) {
                $query = Instrument::with('latestQuote');
                if (!empty($symbols)) {
                    $query->whereIn('ticker', $symbols);
                }
                $items = $query->get()->map(function ($inst) {
                    $q = $inst->latestQuote;
                    if (!$q) { return null; }
                    return [
                        'ticker' => $inst->ticker,
                        'name' => $inst->name,
                        'price' => (float) ($q->last_price ?? 0),
                        'change' => (float) ($q->change_pct ?? 0),
                        'volume' => (int) ($q->volume ?? 0),
                    ];
                })->filter()->values()->all();
                return $items;
            });
            if (!empty($rows)) { return response()->json($rows); }
        }

        $symbolsParam = (string) $request->query('symbols', 'AAPL,MSFT,GOOGL');
        $symbols = collect(explode(',', $symbolsParam))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->take(20)
            ->values()
            ->all();

        $cacheKey = 'market.quotes_list:' . implode(',', $symbols);
        return Cache::remember($cacheKey, now()->addSeconds(45), function () use ($symbols) {
            $apiBase = config('services.alphavantage.base', env('ALPHA_VANTAGE_BASE', 'https://www.alphavantage.co/query'));
            $apiKey = config('services.alphavantage.key', env('ALPHA_VANTAGE_KEY'));

            // Fallback mock if no key
            if (!$apiKey) {
                return response()->json($this->mockQuotes());
            }

            $rows = [];
            foreach ($symbols as $sym) {
                try {
                    $res = Http::timeout(8)->get($apiBase, [
                        'function' => 'GLOBAL_QUOTE',
                        'symbol' => $sym,
                        'apikey' => $apiKey,
                    ]);
                    if ($res->ok()) {
                        $data = $res->json();
                        $q = $data['Global Quote'] ?? null;
                        if ($q) {
                            $pctStr = $q['10. change percent'] ?? '0%';
                            $pct = (float) str_replace('%', '', $pctStr);
                            $price = (float) ($q['05. price'] ?? 0);
                            $vol = (int) ($q['06. volume'] ?? 0);
                            $rows[] = [
                                'ticker' => $q['01. symbol'] ?? $sym,
                                'name' => $q['01. symbol'] ?? $sym,
                                'price' => $price,
                                'change' => $pct,
                                'volume' => $vol,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            if (empty($rows)) {
                $rows = $this->mockQuotes();
            }

            return response()->json($rows);
        });
    }

    private function mockQuotes(): array
    {
        return [
            ['ticker' => 'SONATEL', 'name' => 'Sonatel', 'price' => 17500, 'change' => 1.45, 'volume' => 120500],
            ['ticker' => 'TOTALCI', 'name' => "Total Côte d'Ivoire", 'price' => 2100, 'change' => -0.24, 'volume' => 88750],
            ['ticker' => 'ECOBANK', 'name' => 'Ecobank Transnational', 'price' => 20, 'change' => 0.00, 'volume' => 540100],
            ['ticker' => 'ORAGROUP', 'name' => 'Oragroup Togo', 'price' => 2800, 'change' => 2.15, 'volume' => 45200],
            ['ticker' => 'BOAC', 'name' => 'Bank of Africa - CI', 'price' => 6500, 'change' => -1.52, 'volume' => 67300],
            ['ticker' => 'CORIS', 'name' => 'Coris Bank Int.', 'price' => 9800, 'change' => 3.10, 'volume' => 31000],
            ['ticker' => 'SGC', 'name' => 'Société Générale CI', 'price' => 15200, 'change' => -2.50, 'volume' => 55900],
        ];
    }
}
