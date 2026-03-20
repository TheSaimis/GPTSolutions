<?php

declare (strict_types = 1);

namespace App\Services\Metadata;

final class DocxMetadataService
{
    /**
     * Prideda custom metaduomenis į DOCX. Jei savybė jau egzistuoja – neperrašo.
     * Išimtis: modifiedAt visada perrašomas.
     */
    public function setDocxCustomProperties(string $docxPath, array $properties): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($docxPath) !== true) {
            throw new \RuntimeException("Nepavyko atidaryti DOCX failo: {$docxPath}");
        }

        $customXml = $zip->getFromName('docProps/custom.xml');

        $doc                     = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput       = true;

        if ($customXml !== false) {
            $doc->loadXML($customXml);
            $root = $doc->documentElement;
        } else {
            $root = $doc->createElementNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/custom-properties',
                'Properties'
            );
            $root->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                'xmlns:vt',
                'http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes'
            );
            $doc->appendChild($root);
        }

        if (! $root instanceof \DOMElement) {
            $zip->close();
            throw new \RuntimeException('Nepavyko sukurti custom metadata šaknies.');
        }

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('cp', 'http://schemas.openxmlformats.org/officeDocument/2006/custom-properties');

        $nextPid = 2;
        foreach ($xpath->query('/cp:Properties/cp:property') as $node) {
            if ($node instanceof \DOMElement  && $node->hasAttribute('pid')) {
                $nextPid = max($nextPid, ((int) $node->getAttribute('pid')) + 1);
            }
        }

        foreach ($properties as $name => $value) {
            $name  = trim((string) $name);
            $value = (string) $value;

            if ($name === '') {
                continue;
            }

            $existing = $xpath->query('/cp:Properties/cp:property');
            $existingProperty = null;
            if ($existing !== false) {
                foreach ($existing as $prop) {
                    if ($prop instanceof \DOMElement && $prop->getAttribute('name') === $name) {
                        $existingProperty = $prop;
                        break;
                    }
                }
            }

            $nameNorm = strtolower(str_replace('_', '', $name));
            $alwaysOverwrite = ($nameNorm === 'modifiedat');
            if ($existingProperty !== null && !$alwaysOverwrite) {
                continue;
            }

            if ($existingProperty instanceof \DOMElement) {
                while ($existingProperty->firstChild) {
                    $existingProperty->removeChild($existingProperty->firstChild);
                }
                $vt = $doc->createElementNS(
                    'http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes',
                    'vt:lpwstr',
                    $value
                );
                $existingProperty->appendChild($vt);
                continue;
            }

            $property = $doc->createElementNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/custom-properties',
                'property'
            );
            $property->setAttribute('fmtid', '{D5CDD505-2E9C-101B-9397-08002B2CF9AE}');
            $property->setAttribute('pid', (string) $nextPid++);
            $property->setAttribute('name', $name);

            $vt = $doc->createElementNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes',
                'vt:lpwstr',
                $value
            );

            $property->appendChild($vt);
            $root->appendChild($property);
        }

        $zip->addFromString('docProps/custom.xml', $doc->saveXML());

        $this->ensureCustomPropsRelation($zip);
        $this->ensureCustomPropsContentType($zip);

        $zip->close();
    }

    private function ensureCustomPropsRelation(\ZipArchive $zip): void
    {
        $relsPath = '_rels/.rels';
        $relsXml  = $zip->getFromName($relsPath);

        if ($relsXml === false || str_contains($relsXml, 'custom-properties')) {
            return;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($relsXml);

        $root = $doc->documentElement;
        if (! $root instanceof \DOMElement) {
            return;
        }

        $relationship = $doc->createElement('Relationship');
        $relationship->setAttribute('Id', 'rIdCustomProperties');
        $relationship->setAttribute(
            'Type',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties'
        );
        $relationship->setAttribute('Target', 'docProps/custom.xml');

        $root->appendChild($relationship);

        $zip->addFromString($relsPath, $doc->saveXML());
    }

    private function ensureCustomPropsContentType(\ZipArchive $zip): void
    {
        $contentTypesPath = '[Content_Types].xml';
        $contentTypesXml  = $zip->getFromName($contentTypesPath);

        if ($contentTypesXml === false || str_contains($contentTypesXml, '/docProps/custom.xml')) {
            return;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($contentTypesXml);

        $root = $doc->documentElement;
        if (! $root instanceof \DOMElement) {
            return;
        }

        $override = $doc->createElement('Override');
        $override->setAttribute('PartName', '/docProps/custom.xml');
        $override->setAttribute(
            'ContentType',
            'application/vnd.openxmlformats-officedocument.custom-properties+xml'
        );

        $root->appendChild($override);

        $zip->addFromString($contentTypesPath, $doc->saveXML());
    }

    public function readDocxCustomProperties(string $docxPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return [];
        }
        $customXml = $zip->getFromName('docProps/custom.xml');
        $zip->close();
        if ($customXml === false) {
            return [];
        }
        $doc = new \DOMDocument();
        if (! @$doc->loadXML($customXml)) {
            return [];
        }
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace(
            'cp',
            'http://schemas.openxmlformats.org/officeDocument/2006/custom-properties'
        );
        $result = [];
        foreach ($xpath->query('/cp:Properties/cp:property') as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }
            $name = $node->getAttribute('name');
            if ($name === '') {
                continue;
            }
            $value = '';
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $value = $child->textContent;
                    break;
                }
            }
            $result[$name] = $value;
        }
        return $result;
    }
}
