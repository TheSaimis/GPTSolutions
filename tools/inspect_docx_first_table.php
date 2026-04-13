<?php

/**
 * Diagnostika: kodėl Word lentelė gali plėstis horizontaliai vietoj teksto perkėlimo.
 * Paleiskite: php tools/inspect_docx_first_table.php "C:\kelias\į\failą.docx"
 */

declare(strict_types=1);

$path = $argv[1] ?? '';
if ($path === '' || ! is_readable($path)) {
    fwrite(STDERR, "Naudojimas: php tools/inspect_docx_first_table.php <kelias-į-.docx>\n");
    exit(1);
}

$W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "Nepavyko atidaryti ZIP (.docx)\n");
    exit(1);
}

$xml = $zip->getFromName('word/document.xml');
if ($xml === false || $xml === '') {
    fwrite(STDERR, "Nėra word/document.xml\n");
    $zip->close();
    exit(1);
}

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
if (@$dom->loadXML($xml) !== true) {
    fwrite(STDERR, "Netinkamas XML\n");
    $zip->close();
    exit(1);
}

$xpath = new DOMXPath($dom);
$xpath->registerNamespace('w', $W);

$tables = $xpath->query('//w:tbl');
if ($tables === false || $tables->length === 0) {
    echo "Lentelių document.xml nerasta.\n";
    $zip->close();
    exit(0);
}

$tbl = $tables->item(0);
if (! $tbl instanceof DOMElement) {
    $zip->close();
    exit(0);
}

echo "=== Pirmos lentelės w:tblPr (visas blokas) ===\n";
$tblPrNodes = $xpath->query('w:tblPr', $tbl);
if ($tblPrNodes !== false && $tblPrNodes->length > 0) {
    $tp = $tblPrNodes->item(0);
    if ($tp instanceof DOMElement) {
        echo $dom->saveXML($tp), "\n";
    }
} else {
    echo "(nėra tblPr)\n";
}

$styleId = null;
$tblStyle = $xpath->query('w:tblPr/w:tblStyle', $tbl);
if ($tblStyle !== false && $tblStyle->length > 0 && $tblStyle->item(0) instanceof DOMElement) {
    $styleId = $tblStyle->item(0)->getAttributeNS($W, 'val');
    if ($styleId === '') {
        $styleId = $tblStyle->item(0)->getAttribute('w:val');
    }
    echo "\n=== Lentelės stilius (w:tblStyle @w:val) ===\n", $styleId ?? '(tuščia)', "\n";
}

$stylesXml = $zip->getFromName('word/styles.xml');
$zip->close();

if ($styleId !== null && $styleId !== '' && is_string($stylesXml) && $stylesXml !== '') {
    echo "\n=== styles.xml: lentelės stilius w:styleId=\"{$styleId}\" ===\n";
    $sd = new DOMDocument();
    if (@$sd->loadXML($stylesXml) === true) {
        $sx = new DOMXPath($sd);
        $sx->registerNamespace('w', $W);
        $candidates = $sx->query('//w:style[@w:type="table"]');
        $found = false;
        if ($candidates !== false) {
            foreach ($candidates as $cand) {
                if (! $cand instanceof DOMElement) {
                    continue;
                }
                $sid = $cand->getAttributeNS($W, 'styleId');
                if ($sid === '') {
                    $sid = $cand->getAttribute('w:styleId');
                }
                if ($sid === $styleId) {
                    echo $sd->saveXML($cand), "\n";
                    $found = true;
                    break;
                }
            }
        }
        if (! $found) {
            echo "Stilius pagal styleId nerastas arba ne „table“ tipas.\n";
        }
    }
}

echo "\n=== Pirmos 3 eilutės: kiekvieno pirmo langelio w:tcPr (trumpai) ===\n";
$rows = $xpath->query('w:tr', $tbl);
$maxRows = min(3, $rows !== false ? $rows->length : 0);
for ($r = 0; $r < $maxRows; $r++) {
    $tr = $rows->item($r);
    if (! $tr instanceof DOMElement) {
        continue;
    }
    $tcList = $xpath->query('w:tc', $tr);
    $firstTc = ($tcList !== false && $tcList->length > 0) ? $tcList->item(0) : null;
    if (! $firstTc instanceof DOMElement) {
        continue;
    }
    $tcPr = $xpath->query('w:tcPr', $firstTc)->item(0);
    echo "--- Eilutė " . ($r + 1) . " pirmas langelis tcPr ---\n";
    if ($tcPr instanceof DOMElement) {
        echo $dom->saveXML($tcPr), "\n";
    } else {
        echo "(nėra tcPr)\n";
    }
}

echo "\n=== Dažnos priežastys, jei „Wrap text“ Word nepadeda ===\n";
echo "- w:tblLayout w:type=\"autofit\" — stulpeliai prisitaiko prie turinio ir gali „išsitempti“.\n";
echo "- Lentelės plotis w:tblW w:type=\"pct\" arti 100% + autofit — kombinacija gali elgtis netikėtai.\n";
echo "- Labai ilgas žodis be tarpų arba neperkeliami simboliai (URL, neperkeliamas tarpas NBSP).\n";
echo "- w:fitText — Word bando sutalpinti tekstą vienoje eilutėje (sutraukimas).\n";
echo "- Lentelės stilius (styles.xml) gali nustatyti noWrap / autofit sąlyginai (pirmam stulpeliui ir t. t.).\n";
