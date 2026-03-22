<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpWord\Escaper\Xml;

/**
 * PhpWord TemplateProcessor naudoja str_replace – jei Word skaido ${TipasPilnas} per kelis w:t,
 * makro lieka nekeičiamas. Po generavimo perrašome word/*.xml su regex, leidžiančiu XML žymes tarp dalių.
 */
final class DocxSplitMacroReplacer
{
    private readonly Xml $xmlEscaper;

    public function __construct()
    {
        $this->xmlEscaper = new Xml();
    }

    /**
     * @param array<string, string> $macros Kintamojo vardas be ${} (pvz. TipasPilnas) => reikšmė
     */
    public function apply(string $docxPath, array $macros): void
    {
        if ($macros === [] || ! is_file($docxPath)) {
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $part = $zip->getNameIndex($i);
            if ($part === false || ! preg_match('#^word/(document\\d*\\.xml|header\\d*\\.xml|footer\\d*\\.xml)$#', $part)) {
                continue;
            }
            $content = $zip->getFromIndex($i);
            if ($content === false || $content === '') {
                continue;
            }
            $updated = $content;
            foreach ($macros as $name => $value) {
                if ($name === '') {
                    continue;
                }
                $escaped = $this->xmlEscaper->escape($value);
                $replacement = '<w:r><w:t xml:space="preserve">' . $escaped . '</w:t></w:r>';
                $updated = $this->replaceMacroInXml($updated, (string) $name, $replacement);
            }
            if ($updated !== $content) {
                $zip->addFromString($part, $updated);
            }
        }

        $zip->close();
    }

    private function replaceMacroInXml(string $xml, string $macroName, string $replacementXml): string
    {
        $pattern = $this->buildSplitMacroPattern($macroName);
        $once    = preg_replace($pattern, $replacementXml, $xml, -1, $count);
        if ($count > 0) {
            return $once;
        }

        return $xml;
    }

    /**
     * ${TipasPilnas} → tarp Tipas ir Pilnas gali būti bet kokia XML žymių seka (Word „perlaužia“ makro).
     */
    private function buildSplitMacroPattern(string $macroName): string
    {
        $macroName = trim($macroName);
        if ($macroName === '') {
            return '/$^/u';
        }
        $segments = preg_split('/(?=[A-ZĄČĘĖĮŠŲŪŽ])/u', $macroName, -1, PREG_SPLIT_NO_EMPTY);
        if ($segments === false || count($segments) <= 1) {
            return '/\$\{' . preg_quote($macroName, '/') . '\}/u';
        }
        $quoted = array_map(static fn (string $s): string => preg_quote($s, '/'), $segments);

        return '/\$\{' . $quoted[0] . '(?:<[^>]+>)*' . implode('(?:<[^>]+>)*', array_slice($quoted, 1)) . '\}/u';
    }
}
