<?php

namespace App\Jobs;

use App\Models\Instrument;
use App\Models\MarketIndex;
use App\Models\Quote;
use App\Services\Brvm\BrvmScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefreshBrvmData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 30;

    public function handle(BrvmScraper $scraper): void
    {
        $lock = Cache::lock('jobs:refresh_brvm_data', 55);
        if (!$lock->get()) {
            return; // déjà en cours
        }
        try {
            // Instruments
            $instRows = $scraper->fetchInstruments();
            Log::channel('market')->info('Scraper: instruments fetched', ['count' => count($instRows)]);
            DB::transaction(function () use ($instRows) {
                foreach ($instRows as $r) {
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

            // Indices
            $idxRows = $scraper->fetchIndices();
            Log::channel('market')->info('Scraper: indices fetched', ['count' => count($idxRows)]);
            DB::transaction(function () use ($idxRows) {
                foreach ($idxRows as $r) {
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

            // Quotes
            $qtRows = $scraper->fetchQuotes();
            Log::channel('market')->info('Scraper: quotes fetched', ['count' => count($qtRows)]);
            DB::transaction(function () use ($qtRows) {
                foreach ($qtRows as $r) {
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
                }
            });
            Cache::put('brvm:last_refresh', now()->toDateTimeString(), now()->addHours(12));
            Log::channel('market')->info('Scraper: refresh completed', [
                'instruments' => count($instRows),
                'indices' => count($idxRows),
                'quotes' => count($qtRows),
            ]);
        } finally {
            optional($lock)->release();
        }
    }
}
