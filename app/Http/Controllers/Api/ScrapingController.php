<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Brvm\BrvmScraper;
use App\Models\Instrument;
use App\Models\Quote;
use App\Models\MarketIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ScrapingController extends Controller
{
    public function runInstruments(BrvmScraper $scraper)
    {
        $rows = $scraper->fetchInstruments();
        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                Instrument::updateOrCreate(
                    ['ticker' => $r['ticker']],
                    [
                        'name' => $r['name'] ?? $r['ticker'],
                        'sector' => $r['sector'] ?? null,
                        'isin' => $r['isin'] ?? null,
                        'currency' => $r['currency'] ?? 'XOF',
                        'status' => $r['status'] ?? 'listed',
                    ]
                );
            }
        });
        return response()->json(['ok' => true, 'count' => count($rows)]);
    }

    public function runIndices(BrvmScraper $scraper)
    {
        $rows = $scraper->fetchIndices();
        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                MarketIndex::updateOrCreate(
                    ['code' => $r['code']],
                    [
                        'name' => $r['name'] ?? $r['code'],
                        'last_value' => $r['last_value'] ?? null,
                        'change_abs' => $r['change_abs'] ?? null,
                        'change_pct' => $r['change_pct'] ?? null,
                        'market_timestamp' => $r['market_timestamp'] ?? now(),
                    ]
                );
            }
        });
        return response()->json(['ok' => true, 'count' => count($rows)]);
    }

    public function runQuotes(Request $request, BrvmScraper $scraper)
    {
        $tickers = collect((string) $request->query('tickers', ''))
            ->explode(',')->map(fn($t) => trim($t))->filter()->values()->all();

        $rows = $scraper->fetchQuotes($tickers);
        $count = 0;
        DB::transaction(function () use ($rows, &$count) {
            foreach ($rows as $r) {
                $inst = Instrument::firstOrCreate(
                    ['ticker' => $r['ticker']],
                    ['name' => $r['ticker'], 'currency' => 'XOF']
                );
                Quote::create([
                    'instrument_id' => $inst->id,
                    'last_price' => $r['last_price'] ?? null,
                    'open' => $r['open'] ?? null,
                    'high' => $r['high'] ?? null,
                    'low' => $r['low'] ?? null,
                    'previous_close' => $r['previous_close'] ?? null,
                    'change_abs' => $r['change_abs'] ?? null,
                    'change_pct' => $r['change_pct'] ?? null,
                    'volume' => $r['volume'] ?? null,
                    'value_traded' => $r['value_traded'] ?? null,
                    'market_timestamp' => $r['market_timestamp'] ?? now(),
                ]);
                $count++;
            }
        });
        return response()->json(['ok' => true, 'count' => $count]);
    }

    public function status()
    {
        $lastQuoteAt = Quote::max('market_timestamp');
        $instruments = Instrument::count();
        $quotesToday = Quote::whereDate('created_at', now()->toDateString())->count();
        $indices = MarketIndex::count();
        $lastRefresh = Cache::get('brvm:last_refresh');

        return response()->json([
            'last_refresh' => $lastRefresh,
            'last_quote_at' => $lastQuoteAt,
            'instruments_count' => $instruments,
            'quotes_today' => $quotesToday,
            'indices_count' => $indices,
            'mode' => config('services.market.source'),
        ]);
    }
}
