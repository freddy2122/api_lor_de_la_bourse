<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class BrvmScraperService
{
    private string $base = 'https://www.brvm.org';

    public function listCompanies(): array
    {
        // Page "sociétés cotées" (EN/FR). On tente en EN d'abord, sinon FR.
        $urls = [
            $this->base . '/en/emetteurs/societes-cotees',
            $this->base . '/fr/emetteurs/societes-cotees',
        ];
        foreach ($urls as $url) {
            try {
                $html = Http::timeout(12)->get($url)->body();
                if (!$html) continue;
                return $this->parseCompaniesList($html);
            } catch (\Throwable $e) {
                // try next
            }
        }
        return [];
    }

    public function companyDetail(string $slugOrUrl): array
    {
        $candidates = [];
        if (str_starts_with($slugOrUrl, 'http')) {
            $candidates[] = $slugOrUrl;
        } else {
            $slug = ltrim($slugOrUrl, '/');
            $candidates[] = $this->base . '/en/emetteurs/societes-cotees/' . $slug;
            $candidates[] = $this->base . '/fr/emetteurs/societes-cotees/' . $slug;
        }
        foreach ($candidates as $url) {
            try {
                $html = Http::timeout(12)->get($url)->body();
                if ($html) {
                    $parsed = $this->parseCompanyDetail($html, $url);
                    if (!empty($parsed)) return $parsed;
                }
            } catch (\Throwable $e) {
                // try next
            }
        }
        return [];
    }

    private function parseCompaniesList(string $html): array
    {
        $crawler = new Crawler($html);
        $rows = [];
        // La grille des sociétés peut être rendue en cartes. On détecte tous les liens vers /emetteurs/societes-cotees/<slug>
        $crawler->filter('a')->each(function (Crawler $a) use (&$rows) {
            $href = trim((string) $a->attr('href'));
            if (!$href) { return; }
            // Accepte: /emetteurs/societes-cotees/<slug>[/...][?query]
            if (!preg_match('#/emetteurs/(societes-cotees|listed-companies)/([\w\-]+)(?:/[^?]*)?(?:\?.*)?$#', $href, $m)) {
                return;
            }
            $slug = $m[2];
            $name = trim($a->text(''));
            if (!$slug) return;
            $rows[] = [
                'slug' => $slug,
                'name' => $name ?: null,
                'brvm_url' => str_starts_with($href, 'http') ? $href : rtrim($this->base, '/') . '/' . ltrim($href, '/'),
            ];
        });
        // Dédupliquer par slug et complétion du nom si multiple
        $bySlug = [];
        foreach ($rows as $r) {
            $key = $r['slug'];
            if (!isset($bySlug[$key])) $bySlug[$key] = $r;
            else if (!$bySlug[$key]['name'] && $r['name']) $bySlug[$key]['name'] = $r['name'];
        }
        return array_values($bySlug);
    }

    private function parseCompanyDetail(string $html, string $url): array
    {
        $c = new Crawler($html);
        $out = [
            'name' => null,
            'symbol' => null,
            'sector' => null,
            'country' => null,
            'headquarters' => null,
            'market_cap_fcfa' => null,
            'dividend_yield_pct' => null,
            'description' => null,
            'slug' => $this->slugFromUrl($url),
            'brvm_url' => $url,
        ];

        // Titre
        $titleNode = $c->filter('h1, h2, .page-title')->first();
        if ($titleNode->count()) {
            $out['name'] = trim($titleNode->text('')) ?: null;
        }

        // Petites infos en tableau/definition list
        $c->filter('table, .field, .views-field, dl, .node__content')->each(function (Crawler $node) use (&$out) {
            $txt = preg_replace('/\s+/', ' ', trim($node->text('')));
            if (!$txt) return;
            // Heuristiques
            if (!$out['symbol'] && preg_match('/\b(Ticker|Symbole|Symbol)\s*[:\-]?\s*([A-Z0-9\.-]{2,})/i', $txt, $m)) {
                $out['symbol'] = strtoupper($m[2]);
            }
            if (!$out['sector'] && preg_match('/\b(Secteur|Sector)\s*[:\-]?\s*([^|\n]+)/i', $txt, $m)) {
                $out['sector'] = trim($m[2]);
            }
            if (!$out['country'] && preg_match('/\b(Pays|Country)\s*[:\-]?\s*([^|\n]+)/i', $txt, $m)) {
                $out['country'] = trim($m[2]);
            }
            if (!$out['headquarters'] && preg_match('/\b(Si[eè]ge|Headquarters)\s*[:\-]?\s*([^|\n]+)/i', $txt, $m)) {
                $out['headquarters'] = trim($m[2]);
            }
            if (is_null($out['market_cap_fcfa']) && preg_match('/\b(Capitalisation|Market\s*Cap)\b[^0-9]*([0-9\s\.,]+)\s*(FCFA|XOF)?/i', $txt, $m)) {
                $out['market_cap_fcfa'] = $this->toNumber($m[2]);
            }
            if (is_null($out['dividend_yield_pct']) && preg_match('/\b(Rendement|Dividend\s*Yield)\b[^0-9\-\+]*([0-9\.,]+)\s*%/i', $txt, $m)) {
                $out['dividend_yield_pct'] = $this->toPercent($m[2]);
            }
        });

        // Description
        $descNode = $c->filter('article, .node, .field--name-body, .field-content, .content, .node__content p')->first();
        if ($descNode->count()) {
            $desc = trim($descNode->text(''));
            if ($desc && mb_strlen($desc) > 60) {
                $out['description'] = $desc;
            }
        }

        return $out;
    }

    public function fetchHomepageSummary(): array
    {
        $urls = [
            $this->base . '/fr',
            $this->base . '/en',
            $this->base . '/',
        ];
        foreach ($urls as $url) {
            try {
                $html = Http::timeout(10)->get($url)->body();
                if (!$html) continue;
                $c = new Crawler($html);
                $text = preg_replace('/\s+/', ' ', strip_tags($html));
                $out = [
                    'transactions_value_fcfa' => null,
                    'cap_actions_fcfa' => null,
                    'cap_obligations_fcfa' => null,
                    'last_update' => null,
                ];
                // Recherche tolérante via regex sur texte global
                if (preg_match('/Valeur des transactions[^0-9]*([0-9\s\.,]+)/i', $text, $m)) {
                    $out['transactions_value_fcfa'] = $this->toNumber($m[1]);
                }
                if (preg_match('/Capitalisation\s*Actions[^0-9]*([0-9\s\.,]+)/i', $text, $m)) {
                    $out['cap_actions_fcfa'] = $this->toNumber($m[1]);
                }
                if (preg_match('/Capitalisation\s+des\s+obligations[^0-9]*([0-9\s\.,]+)/i', $text, $m)) {
                    $out['cap_obligations_fcfa'] = $this->toNumber($m[1]);
                }
                if (preg_match('/Dern[ièe]re mise à jour[^:]*:\s*([^\n]+)/i', $text, $m)) {
                    $out['last_update'] = trim($m[1]);
                }
                // Si au moins un champ trouvé, on retourne
                if ($out['transactions_value_fcfa'] || $out['cap_actions_fcfa'] || $out['cap_obligations_fcfa'] || $out['last_update']) {
                    return $out;
                }
            } catch (\Throwable $e) {
                // try next
            }
        }
        return [
            'transactions_value_fcfa' => null,
            'cap_actions_fcfa' => null,
            'cap_obligations_fcfa' => null,
            'last_update' => null,
        ];
    }

    public function wikipediaFallbackDescription(string $companyName): ?string
    {
        try {
            // Recherche simple sur Wikipedia fr
            $search = 'https://fr.wikipedia.org/w/index.php?search=' . urlencode($companyName);
            $html = Http::timeout(10)->get($search)->body();
            if (!$html) return null;
            $c = new Crawler($html);
            $firstLink = $c->filter('#mw-content-text a')->first();
            if ($firstLink->count()) {
                $href = $firstLink->attr('href');
                if ($href && str_starts_with($href, '/wiki/')) {
                    $page = 'https://fr.wikipedia.org' . $href;
                    $ph = Http::timeout(10)->get($page)->body();
                    if ($ph) {
                        $pc = new Crawler($ph);
                        $p = $pc->filter('p')->first();
                        $txt = trim($p->text(''));
                        if ($txt && mb_strlen($txt) > 60) return $txt;
                    }
                }
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    private function slugFromUrl(string $url): ?string
    {
        if (preg_match('#/emetteurs/(societes-cotees|listed-companies)/([\w\-]+)(?:/[^?]*)?(?:\?.*)?$#', $url, $m)) return $m[2];
        return null;
    }

    private function toNumber(string $text): ?float
    {
        $t = trim($text);
        if ($t === '') return null;
        $t = str_replace(["\xC2\xA0", ' '], '', $t);
        $t = str_replace(['.', ','], ['', '.'], $t);
        if (!is_numeric($t)) return null;
        return (float) $t;
    }

    private function toPercent(string $text): ?float
    {
        $t = str_replace(['%', ' '], '', trim($text));
        if ($t === '') return null;
        $t = str_replace(['.', ','], ['', '.'], $t);
        if (!is_numeric($t)) return null;
        return (float) $t;
    }
}
