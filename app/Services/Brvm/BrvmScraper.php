<?php

namespace App\Services\Brvm;

use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class BrvmScraper
{
    public function fetchIndices(): array
    {
        $html = $this->fetch('/fr/indices');
        if (!$html) return [];
        $crawler = new Crawler($html);
        $now = now()->toDateTimeString();
        $rows = [];
        $crawler->filter('table tbody tr')->each(function (Crawler $tr) use (&$rows, $now) {
            $cols = $tr->filter('td');
            if ($cols->count() < 3) return;
            $name = trim($cols->eq(0)->text(''));
            $last = $this->toNumber($cols->eq(1)->text(''));
            $chg = $this->toPercent($cols->eq(2)->text(''));
            if ($name === '') return;
            $code = Str::of($name)->upper()->replace(' ', '-')->value();
            $rows[] = [
                'code' => $code,
                'name' => $name,
                'last_value' => $last,
                'change_abs' => null,
                'change_pct' => $chg,
                'market_timestamp' => $now,
            ];
        });
        return $rows;
    }

    public function fetchInstruments(): array
    {
        $rows = [];
        $maxPages = 10; // sécurité
        for ($offset = 0; $offset < $maxPages * 10; $offset += 10) {
            $html = $this->fetch('/fr/cours-actions/' . $offset);
            if (!$html) break;
            $crawler = new Crawler($html);
            $countBefore = count($rows);
            $crawler->filter('table tbody tr')->each(function (Crawler $tr) use (&$rows) {
                $cols = $tr->filter('td');
                if ($cols->count() < 2) return;
                $ticker = Str::of($cols->eq(0)->text(''))->trim()->upper()->value();
                $name = trim($cols->eq(1)->text(''));
                if ($ticker === '' || $name === '') return;
                $rows[] = [
                    'ticker' => $ticker,
                    'name' => $name,
                    'sector' => null,
                    'isin' => null,
                    'currency' => 'XOF',
                    'status' => 'listed',
                ];
            });
            if (count($rows) === $countBefore) break; // page vide
        }
        return $rows;
    }

    public function fetchQuotes(array $tickers = []): array
    {
        $now = now()->toDateTimeString();
        $rows = [];
        $allowed = $tickers ? collect($tickers)->map(fn($t) => Str::upper(trim($t)))->flip() : null;
        $maxPages = 10; // sécurité
        for ($offset = 0; $offset < $maxPages * 10; $offset += 10) {
            $html = $this->fetch('/fr/cours-actions/' . $offset);
            if (!$html) break;
            $crawler = new Crawler($html);
            $countBefore = count($rows);
            $crawler->filter('table tbody tr')->each(function (Crawler $tr) use (&$rows, $allowed, $now) {
                $cols = $tr->filter('td');
                if ($cols->count() < 6) return;
                $ticker = Str::of($cols->eq(0)->text(''))->trim()->upper()->value();
                if ($ticker === '') return;
                if ($allowed && !$allowed->has($ticker)) return;
                $last = $this->toNumber($cols->eq(2)->text(''));
                $prev = $this->toNumber($cols->eq(3)->text(''));
                $chgPct = $this->toPercent($cols->eq(4)->text(''));
                $vol = (int) $this->toNumber($cols->eq(5)->text(''));
                $rows[] = [
                    'ticker' => $ticker,
                    'last_price' => $last,
                    'previous_close' => $prev,
                    'change_abs' => $last !== null && $prev !== null ? ($last - $prev) : null,
                    'change_pct' => $chgPct,
                    'volume' => $vol,
                    'value_traded' => null,
                    'market_timestamp' => $now,
                ];
            });
            if (count($rows) === $countBefore) break;
        }

        $html2 = $this->fetch('/fr/volumes/0');
        if ($html2) {
            $map = [];
            (new Crawler($html2))->filter('table tbody tr')->each(function (Crawler $tr) use (&$map) {
                $cols = $tr->filter('td');
                if ($cols->count() < 3) return;
                $ticker = Str::of($cols->eq(0)->text(''))->trim()->upper()->value();
                if ($ticker === '') return;
                $value = $this->toNumber($cols->eq(2)->text(''));
                $map[$ticker] = $value;
            });
            foreach ($rows as &$r) {
                if (isset($map[$r['ticker']])) {
                    $r['value_traded'] = $map[$r['ticker']];
                }
            }
        }

        return $rows;
    }

    protected function fetch(string $path): ?string
    {
        $base = config('services.market.brvm.base_url') ?: 'https://www.brvm.org';
        $ua = config('services.market.brvm.user_agent', 'SGI-Scraper/1.0');
        $client = new Client([
            'timeout' => 12,
            'headers' => [
                'User-Agent' => $ua,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
        ]);
        try {
            $res = $client->get(rtrim($base, '/') . $path);
            if ($res->getStatusCode() === 200) {
                return (string) $res->getBody();
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    protected function toNumber(string $text): ?float
    {
        $t = trim($text);
        if ($t === '') return null;
        $t = str_replace(["\xC2\xA0", ' '], '', $t);
        $t = str_replace(['.', ','], ['', '.'], $t);
        if (!is_numeric($t)) return null;
        return (float) $t;
    }

    protected function toPercent(string $text): ?float
    {
        $t = str_replace(['%', ' '], '', trim($text));
        if ($t === '') return null;
        $t = str_replace(['.', ','], ['', '.'], $t);
        if (!is_numeric($t)) return null;
        return (float) $t;
    }
}

