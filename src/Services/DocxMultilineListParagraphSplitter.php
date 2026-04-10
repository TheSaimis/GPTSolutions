<?php

declare(strict_types=1);

namespace App\Services;

/**
 * PhpWord TemplateProcessor dažnai pakeičia ${atliktiDarbai} \n į <w:br/> tame pačiame w:p —
 * Word rodo kelias eilutes, bet sąrašo ženklas lieka tik prie pirmos. Skaidome į kelis w:p
 * su ta pačia w:pPr (w:numPr). Taip pat palaikomas tikras \n w:t tekste.
 */
final class DocxMultilineListParagraphSplitter
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function expandInDocx(string $docxPath): void
    {
        if (! is_file($docxPath)) {
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
            $updated = $this->expandMultilineParagraphsInXml($content);
            if ($updated !== $content) {
                $zip->addFromString($part, $updated);
            }
        }

        $zip->close();
    }

    private function expandMultilineParagraphsInXml(string $xml): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        if (@$dom->loadXML($xml) !== true) {
            return $xml;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::W_NS);

        $paragraphs = $xpath->query('//w:p');
        if ($paragraphs === false || $paragraphs->length === 0) {
            return $xml;
        }

        /** @var list<\DOMElement> $pNodes */
        $pNodes = [];
        for ($i = 0; $i < $paragraphs->length; $i++) {
            $n = $paragraphs->item($i);
            if ($n instanceof \DOMElement) {
                $pNodes[] = $n;
            }
        }

        foreach ($pNodes as $p) {
            if (! $this->paragraphHasListNumbering($p, $xpath)) {
                continue;
            }

            $lines = $this->paragraphLinesFromBrAndText($p);
            if (count($lines) <= 1) {
                $fullText = $this->paragraphPlainText($p);
                if ($fullText === '' || ! preg_match('/[\r\n]/', $fullText)) {
                    continue;
                }
                $split = preg_split('/\r\n|\n|\r/', $fullText, -1, PREG_SPLIT_NO_EMPTY);
                if ($split === false) {
                    continue;
                }
                $lines = array_values(array_filter(array_map(trim(...), $split), static fn(string $l): bool => $l !== ''));
            }
            if (count($lines) <= 1) {
                continue;
            }

            $pPr = null;
            foreach ($p->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->namespaceURI === self::W_NS && $child->localName === 'pPr') {
                    $pPr = $child;
                    break;
                }
            }

            $styleRun = $this->findFirstTextRunForStyle($p, $xpath);
            $pPrTemplate = $pPr !== null ? $pPr->cloneNode(true) : null;

            while ($p->firstChild) {
                $p->removeChild($p->firstChild);
            }
            if ($pPrTemplate !== null) {
                $p->appendChild($pPrTemplate);
            }
            $p->appendChild($this->createRunWithTextPreservingStyle($dom, $styleRun, $lines[0]));

            $anchor = $p;
            for ($i = 1; $i < count($lines); $i++) {
                $newP = $dom->createElementNS(self::W_NS, 'w:p');
                if ($pPr !== null) {
                    $newP->appendChild($pPr->cloneNode(true));
                }
                $newP->appendChild($this->createRunWithTextPreservingStyle($dom, $styleRun, $lines[$i]));
                $parent = $anchor->parentNode;
                if ($parent === null) {
                    break;
                }
                $next = $anchor->nextSibling;
                if ($next !== null) {
                    $parent->insertBefore($newP, $next);
                } else {
                    $parent->appendChild($newP);
                }
                $anchor = $newP;
            }
        }

        $root = $dom->documentElement;
        if ($root === null) {
            return $xml;
        }

        $out = $dom->saveXML($root);
        if ($out === false) {
            return $xml;
        }

        $decl = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        if (str_starts_with(trim($xml), '<?xml')) {
            return $decl . "\n" . $out;
        }

        return $out;
    }

    private function paragraphHasListNumbering(\DOMElement $p, \DOMXPath $xpath): bool
    {
        $n = $xpath->query('./w:pPr/w:numPr', $p);

        return $n !== false && $n->length > 0;
    }

    /**
     * Eilutės iš eilės w:r ir <w:br/> / <w:cr/> + w:t (kaip daro PhpWord iš \n).
     *
     * @return list<string>
     */
    private function paragraphLinesFromBrAndText(\DOMElement $p): array
    {
        $segments = [];
        $current = '';

        foreach ($p->childNodes as $node) {
            if (! $node instanceof \DOMElement || $node->namespaceURI !== self::W_NS || $node->localName !== 'r') {
                continue;
            }

            foreach ($node->childNodes as $inner) {
                if (! $inner instanceof \DOMElement || $inner->namespaceURI !== self::W_NS) {
                    continue;
                }
                $ln = $inner->localName;
                if ($ln === 't') {
                    $current .= $inner->textContent;
                } elseif ($ln === 'br' || $ln === 'cr') {
                    $segments[] = $current;
                    $current = '';
                }
            }
        }
        $segments[] = $current;

        $lines = [];
        foreach ($segments as $seg) {
            $parts = preg_split('/\r\n|\n|\r/', $seg, -1, PREG_SPLIT_NO_EMPTY);
            if ($parts === false) {
                $t = trim($seg);
                if ($t !== '') {
                    $lines[] = $t;
                }

                continue;
            }
            foreach ($parts as $part) {
                $t = trim($part);
                if ($t !== '') {
                    $lines[] = $t;
                }
            }
        }

        return $lines;
    }

    private function paragraphPlainText(\DOMElement $p): string
    {
        $xpath = new \DOMXPath($p->ownerDocument);
        $xpath->registerNamespace('w', self::W_NS);
        $textNodes = $xpath->query('.//w:t', $p);
        if ($textNodes === false || $textNodes->length === 0) {
            return '';
        }

        $parts = [];
        for ($i = 0; $i < $textNodes->length; $i++) {
            $t = $textNodes->item($i);
            if ($t !== null) {
                $parts[] = $t->textContent;
            }
        }

        return implode('', $parts);
    }

    private function findFirstTextRunForStyle(\DOMElement $p, \DOMXPath $xpath): ?\DOMElement
    {
        $runs = $xpath->query('./w:r[.//w:t]', $p);
        if ($runs === false || $runs->length === 0) {
            return null;
        }
        $first = $runs->item(0);

        return $first instanceof \DOMElement ? $first : null;
    }

    private function createRunWithTextPreservingStyle(\DOMDocument $dom, ?\DOMElement $styleRun, string $text): \DOMElement
    {
        if ($styleRun !== null) {
            $r = $styleRun->cloneNode(true);
            $remove = [];
            foreach ($r->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->namespaceURI === self::W_NS && $child->localName === 'rPr') {
                    continue;
                }
                $remove[] = $child;
            }
            foreach ($remove as $c) {
                $r->removeChild($c);
            }
            $t = $dom->createElementNS(self::W_NS, 'w:t');
            if ($text !== '' && (preg_match('/^\s/u', $text) === 1 || preg_match('/\s$/u', $text) === 1 || str_contains($text, '  '))) {
                $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            }
            $t->appendChild($dom->createTextNode($text));
            $r->appendChild($t);

            return $r;
        }

        return $this->createPlainTextRun($dom, $text);
    }

    private function createPlainTextRun(\DOMDocument $dom, string $text): \DOMElement
    {
        $r = $dom->createElementNS(self::W_NS, 'w:r');
        $t = $dom->createElementNS(self::W_NS, 'w:t');
        if ($text !== '' && (preg_match('/^\s/u', $text) === 1 || preg_match('/\s$/u', $text) === 1 || str_contains($text, '  '))) {
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        }
        $t->appendChild($dom->createTextNode($text));
        $r->appendChild($t);

        return $r;
    }
}
