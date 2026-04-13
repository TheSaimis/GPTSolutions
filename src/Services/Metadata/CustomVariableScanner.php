<?php

declare(strict_types=1);

namespace App\Services\Metadata;

/**
 * Iš OOXML (.docx / .xlsx) ištraukia ${vardas} žymas, kurios nėra įmonės rekvizitų sąraše
 * ir nėra papildomame ignoravimo sąraše (žr. {@see FlowMacroIgnores}).
 *
 * Logika suderinta su frontend {@code wordVariableParser.ts}.
 */
final class CustomVariableScanner
{
    /**
     * Multipart / POST laukas `customVariableIgnorePlaceholders`: JSON masyvas arba paprastas masyvas.
     *
     * @return list<string>
     */
    public static function parseIgnoreListFromRequest(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return array_values(array_filter(
                array_map(static fn ($v): string => trim((string) $v), $raw),
                static fn (string $v): bool => $v !== ''
            ));
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return [];
        }
        $decoded = json_decode($s, true);
        if (is_array($decoded)) {
            return array_values(array_filter(
                array_map(static fn ($v): string => trim((string) $v), $decoded),
                static fn (string $v): bool => $v !== ''
            ));
        }

        return [];
    }

    /**
     * Pilni leidžiami ${...} placeholderiai (įmonės rekvizitai ir bendri makro) —
     * sinchronizuoti su LPSK Projektinis `lib/types/Company.ts` → `wordVariables`.
     *
     * @var list<string>
     */
    private const COMPANY_REQUISITE_PLACEHOLDERS = [
        '${kompanija}',
        '${companyName}',
        '${companyDirectory}',
        '${atliktiDarbai}',

        '${tipas}',
        '${tipasPilnas}',
        '${TIPASPILNAS}',
        '${tipasKompaktiskas}',
        '${TIPASKOMPAKTISKAS}',

        '${adresas}',
        '${Miestas}',
        '${kodas}',
        '${code}',

        '${data}',
        '${documentDate}',
        '${pagrindas}',
        '${dataSkaitmenimis}',

        '${role}',
        '${lytis}',

        '${vadovas}',
        '${vadovo}',
        '${vadovui}',
        '${vadovą}',
        '${vadovu}',
        '${vadove}',
        '${vadovėje}',
        '${vadovei}',
        '${vadovę}',
        '${vadovasNom}',
        '${vadovasKreip}',
        '${vadoves}',
        '${vadovai}',

        '${vardas}',
        '${vardo}',
        '${vardui}',
        '${vardą}',
        '${vardu}',
        '${vardviet}',
        '${varde}',
        '${vardes}',

        '${pavarde}',
        '${pavardes}',
        '${pavardui}',
        '${pavardą}',
        '${pavardu}',
        '${pavardviet}',
        '${pavardeS}',
        '${pavardo}',
    ];

    /**
     * @param list<string> $ignorePlaceholders Papildomi pilni „${x}“ arba tik vardai „x“
     *
     * @return list<string> Unikalūs vidiniai vardai be ${} (originali registracija kaip šablone)
     */
    public function listUnknownPlaceholders(string $absolutePath, array $ignorePlaceholders = []): array
    {
        $absolutePath = str_replace('\\', '/', $absolutePath);
        if ($absolutePath === '' || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return [];
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $text = match ($ext) {
            'docx' => $this->extractTextFromDocx($absolutePath),
            'xlsx' => $this->extractTextFromXlsx($absolutePath),
            default => '',
        };

        if ($text === '') {
            return [];
        }

        return $this->extractUnknownVariableNames($text, $ignorePlaceholders);
    }

    /**
     * @param list<string> $ignorePlaceholders
     *
     * @return list<string>
     */
    public function extractUnknownVariableNames(string $text, array $ignorePlaceholders = []): array
    {
        $ignoreLower = $this->buildIgnoredFullLowerSet($ignorePlaceholders);

        $result  = [];
        $seenKey = [];

        if (preg_match_all('/\$\{([^}]+)\}/', $text, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $full       = $match[0];
                $inner = $match[1];
                $fullLower  = mb_strtolower($full, 'UTF-8');
                if (isset($ignoreLower[$fullLower])) {
                    continue;
                }
                $dedupeKey = mb_strtolower($inner, 'UTF-8');
                if (isset($seenKey[$dedupeKey])) {
                    continue;
                }
                $seenKey[$dedupeKey] = true;
                $result[]            = $inner;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $ignorePlaceholders
     *
     * @return array<string, true>
     */
    private function buildIgnoredFullLowerSet(array $ignorePlaceholders): array
    {
        $set = [];
        foreach (self::COMPANY_REQUISITE_PLACEHOLDERS as $p) {
            $set[mb_strtolower($p, 'UTF-8')] = true;
        }
        foreach ($ignorePlaceholders as $raw) {
            $t = trim((string) $raw);
            if ($t === '') {
                continue;
            }
            if (str_starts_with($t, '${') && str_ends_with($t, '}')) {
                $set[mb_strtolower($t, 'UTF-8')] = true;
            } else {
                $set[mb_strtolower('${' . $t . '}', 'UTF-8')] = true;
            }
        }

        return $set;
    }

    private function extractTextFromDocx(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false || $xml === '') {
            return '';
        }

        $parts = [];
        if (preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $m)) {
            foreach ($m[1] as $chunk) {
                $parts[] = $this->decodeXmlText($chunk);
            }
        }

        return implode('', $parts);
    }

    private function extractTextFromXlsx(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $chunks = [];

        $shared = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared !== false && $shared !== '') {
            if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $shared, $m)) {
                foreach ($m[1] as $chunk) {
                    $chunks[] = $this->decodeXmlText($chunk);
                }
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false || ! preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $entryName)) {
                continue;
            }
            $sheet = $zip->getFromIndex($i);
            if ($sheet === false || $sheet === '') {
                continue;
            }
            if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $sheet, $m)) {
                foreach ($m[1] as $chunk) {
                    $chunks[] = $this->decodeXmlText($chunk);
                }
            }
        }

        $zip->close();

        return implode('', $chunks);
    }

    private function decodeXmlText(string $xml): string
    {
        return str_replace(
            ['&amp;', '&lt;', '&gt;', '&quot;', '&#39;'],
            ['&', '<', '>', '"', "'"],
            $xml
        );
    }
}
