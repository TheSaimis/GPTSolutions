<?php

declare (strict_types = 1);

namespace App\Services\Metadata;

final class FindTemplate
{
    public function __construct(
        private readonly string $projectDir
    ) {}

    /**
     * Find a DOCX template by templateId metadata.
     * Falls back to documentId for older templates.
     */
    public function findByTemplateId(string $templateId): ?string
    {
        $templatesDir = $this->projectDir . '/templates';

        if (! is_dir($templatesDir)) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($templatesDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (strtolower($file->getExtension()) !== 'docx') {
                continue;
            }

            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }

            $metadata = $this->readCustomMetadata($path);

            $candidateId = $metadata['templateId'] ?? $metadata['documentId'] ?? null;

            if ($candidateId === $templateId) {
                return $this->makeRelativePath($path);
            }
        }

        return null;
    }

    /**
     * Backward-compatible alias.
     */
    public function findByDocumentId(string $documentId): ?string
    {
        return $this->findByTemplateId($documentId);
    }

    /**
     * Read DOCX custom metadata.
     */
    private function readCustomMetadata(string $docxPath): array
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

    private function makeRelativePath(string $absolutePath): string
    {
        $templatesDir = $this->projectDir . '/templates';
        $normalizedBase = str_replace('\\', '/', realpath($templatesDir) ?: $templatesDir);
        $normalizedPath = str_replace('\\', '/', $absolutePath);
        if (str_starts_with($normalizedPath, $normalizedBase)) {
            return ltrim(substr($normalizedPath, strlen($normalizedBase)), '/');
        }
        return ltrim($normalizedPath, '/');
    }
}
