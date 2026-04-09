<?php

declare (strict_types = 1);

namespace App\Services;

use App\Services\Metadata\DocxMetadataService;
use App\Services\Metadata\FindTemplate;

/**
 * Randa šablonus pagal įmonės katalogą po generated/: iš ten esančių failų
 * surenkami unikalūs templateId, tada iš templates/ medžio išsprendžiami failų vardai.
 */
final class FindTemplates
{
    public function __construct(
        private readonly string $projectDir,
        private readonly FindTemplate $findTemplate,
        private readonly DocxMetadataService $docxMetadataService,
    ) {}

    /**
     * Unikalūs šablonų pavadinimai (be plėtinio), po vieną eilutėje, pagal templateId
     * visuose generated/{companyDirectoryRelative} .docx/.xlsx failuose (rekursyviai).
     */
    public function buildAtliktiDarbaiText(string $companyDirectoryRelative): string
    {
        $rel = $this->normalizeGeneratedSubdir($companyDirectoryRelative);
        if ($rel === '') {
            return '';
        }

        $root = $this->projectDir . '/generated/' . $rel;
        $rootReal = realpath($root);
        if ($rootReal === false || ! is_dir($rootReal)) {
            return '';
        }

        $expectedGenerated = realpath($this->projectDir . '/generated');
        if ($expectedGenerated === false) {
            return '';
        }
        $underGenerated = $expectedGenerated . \DIRECTORY_SEPARATOR;
        if ($rootReal !== $expectedGenerated && ! str_starts_with($rootReal, $underGenerated)) {
            return '';
        }

        $templateIds = $this->collectUniqueTemplateIdsInOrder($rootReal);
        if ($templateIds === []) {
            return '';
        }

        $names = [];
        foreach ($templateIds as $templateId) {
            $relativeTemplatePath = $this->findTemplate->findByTemplateId($templateId);
            if ($relativeTemplatePath === null) {
                continue;
            }
            $base = pathinfo($relativeTemplatePath, PATHINFO_FILENAME);
            if ($base === '') {
                continue;
            }
            $names[$base] = true;
        }

        if ($names === []) {
            return '';
        }

        $list = array_keys($names);
        natcasesort($list);

        return implode("\n", $list);
    }

    /**
     * @return list<string>
     */
    private function collectUniqueTemplateIdsInOrder(string $absoluteDir): array
    {
        $ids   = [];
        $seen  = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['docx', 'xlsx'], true)) {
                continue;
            }
            $meta = $this->docxMetadataService->readDocxCustomProperties($file->getPathname());
            $tid  = trim((string) ($meta['templateId'] ?? ''));
            if ($tid === '') {
                $tid = trim((string) ($meta['documentId'] ?? ''));
            }
            if ($tid === '' || isset($seen[$tid])) {
                continue;
            }
            $seen[$tid] = true;
            $ids[] = $tid;
        }

        return $ids;
    }

    private function normalizeGeneratedSubdir(string $path): string
    {
        $p = trim(str_replace('\\', '/', $path));
        $p = trim($p, '/');
        if ($p === '' || str_contains($p, '..')) {
            return '';
        }

        return $p;
    }
}
