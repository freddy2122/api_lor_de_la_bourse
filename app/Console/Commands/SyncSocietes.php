<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\BrvmScraperService;
use App\Models\Societe;

class SyncSocietes extends Command
{
    protected $signature = 'brvm:societes:sync {--limit= : Limiter le nombre de sociétés à traiter}';
    protected $description = 'Synchroniser les sociétés cotées BRVM (liste + détails + fallback Wikipedia)';

    public function handle(BrvmScraperService $scraper): int
    {
        $this->info('BRVM sociétés: listing...');
        $rows = $scraper->listCompanies();
        $limit = (int) ($this->option('limit') ?: 0);
        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }
        $this->info('Trouvé: ' . count($rows) . ' sociétés.');

        // Fallback: si la page liste BRVM ne renvoie rien (structure dynamique), initialiser depuis Instruments
        if (count($rows) === 0) {
            $this->warn('Aucune société trouvée via la page liste BRVM. Fallback depuis Instruments...');
            $instruments = \App\Models\Instrument::select(['ticker','name'])
                ->orderBy('ticker')
                ->when($limit > 0, fn($q) => $q->limit($limit))
                ->get();
            foreach ($instruments as $inst) {
                $slug = strtolower(preg_replace('/[^a-z0-9\-]+/i', '-', $inst->name ?: $inst->ticker));
                $rows[] = [
                    'slug' => trim($slug, '-'),
                    'name' => $inst->name ?: $inst->ticker,
                    // Tentative d'URL détail basée sur le slug généré
                    'brvm_url' => 'https://www.brvm.org/en/emetteurs/societes-cotees/' . trim($slug, '-'),
                    'symbol' => $inst->ticker,
                ];
            }
            $this->info('Fallback Instruments fourni: ' . count($rows) . ' entrées à initialiser.');
        }

        $count = 0; $updated = 0; $created = 0;
        foreach ($rows as $i => $r) {
            $slug = $r['slug'] ?? null;
            $url = $r['brvm_url'] ?? null;
            if (!$slug || !$url) continue;

            $this->line(sprintf('[%d/%d] %s', $i+1, count($rows), $slug));
            $detail = $scraper->companyDetail($url);
            if (empty($detail)) continue;

            // Fallback description si vide
            if (empty($detail['description']) && !empty($detail['name'])) {
                $desc = $scraper->wikipediaFallbackDescription($detail['name']);
                if ($desc) $detail['description'] = $desc;
            }

            // Upsert par slug prioritaire, sinon symbol
            $attrs = [ 'slug' => $detail['slug'] ?? $slug ];
            if (!empty($detail['symbol'])) {
                // Incrémenter clé de recherche si slug manquant
                $attrs = ['slug' => $attrs['slug']];
            }

            $payload = [
                'symbol' => $detail['symbol'] ?? ($r['symbol'] ?? null),
                'name' => $detail['name'] ?? ($r['name'] ?? $slug),
                'sector' => $detail['sector'] ?? null,
                'country' => $detail['country'] ?? null,
                'slug' => $detail['slug'] ?? $slug,
                'brvm_url' => $detail['brvm_url'] ?? $url,
                'headquarters' => $detail['headquarters'] ?? null,
                'market_cap_fcfa' => $detail['market_cap_fcfa'] ?? null,
                'dividend_yield_pct' => $detail['dividend_yield_pct'] ?? null,
                'description' => $detail['description'] ?? null,
                'extra' => null,
            ];

            $existing = Societe::where('slug', $payload['slug'])->first();
            if (!$existing && !empty($payload['symbol'])) {
                $existing = Societe::where('symbol', $payload['symbol'])->first();
            }

            if ($existing) {
                $existing->fill(array_filter($payload, fn($v) => $v !== null));
                $existing->save();
                $updated++;
            } else {
                Societe::create($payload);
                $created++;
            }
            $count++;
        }

        Log::channel('market')->info('BRVM societes sync completed', [
            'processed' => $count,
            'created' => $created,
            'updated' => $updated,
        ]);
        $this->info("Terminé: $count, créés: $created, mis à jour: $updated");
        return self::SUCCESS;
    }
}
