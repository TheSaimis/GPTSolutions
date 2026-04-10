<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Iš ZIP archyvo ištraukia tik Word / Excel failus ir įkelia juos per {@see AddWordDocument}
 * (OOXML failams uždedami custom metaduomenys).
 * Išlaikoma archyvo aplankų struktūra po pasirinktu katalogu (saugūs kelio segmentai).
 */
final class ZipTemplateImportService
{
    private const ALLOWED_EXT = ['doc', 'docx', 'xls', 'xlsx'];

    private const MAX_ENTRIES = 500;

    private const MAX_ENTRY_BYTES = 40 * 1024 * 1024;

    public function __construct(
        private readonly AddWordDocument $addWordDocument,
    ) {}

    /**
     * @return array{
     *   status: 'SUCCESS'|'PARTIAL'|'FAIL',
     *   results: list<array{source: string, status: 'SUCCESS'|'FAIL', file?: array, error?: string}>,
     *   skipped: list<array{name: string, reason: string}>,
     *   error?: string
     * }
     */
    public function import(UploadedFile $zip, string $directory, string $root): array
    {
        $skipped = [];
        $results = [];

        $ext = strtolower($zip->getClientOriginalExtension());
        if ($ext !== 'zip') {
            return [
                'status'  => 'FAIL',
                'error'   => 'Tik .zip archyvai.',
                'results' => [],
                'skipped' => [],
            ];
        }

        $zipPath = $zip->getRealPath();
        if ($zipPath === false || ! is_readable($zipPath)) {
            return [
                'status'  => 'FAIL',
                'error'   => 'ZIP failas neprieinamas.',
                'results' => [],
                'skipped' => [],
            ];
        }

        $archive = new \ZipArchive();
        if ($archive->open($zipPath) !== true) {
            return [
                'status'  => 'FAIL',
                'error'   => 'Nepavyko atidaryti ZIP archyvo.',
                'results' => [],
                'skipped' => [],
            ];
        }

        if ($archive->numFiles > self::MAX_ENTRIES) {
            $archive->close();

            return [
                'status'  => 'FAIL',
                'error'   => 'ZIP turi per daug įrašų (daugiausia ' . self::MAX_ENTRIES . ').',
                'results' => [],
                'skipped' => [],
            ];
        }

        /** @var array<string, array<string, true>> unikalūs failų vardai kiekviename tiksliniame aplanke */
        $usedPerTargetDir = [];

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $rawName = $archive->getNameIndex($i);
            if (! is_string($rawName)) {
                $skipped[] = ['name' => '#' . (string) $i, 'reason' => 'nepavyko nuskaityti įrašo pavadinimo'];
                continue;
            }

            // ZIP dažnai saugo kelius su „\“ (Windows); logikai naudojame „/“. Skaitymui pagal indeksą
            // (getFromIndex) nereikia sutampančio pavadinimo — getStream(su /) ant Windows dažnai FAIL.
            $logicalPath = str_replace('\\', '/', $rawName);
            if ($logicalPath === '' || str_ends_with($logicalPath, '/')) {
                continue;
            }

            if (str_contains($logicalPath, '..')) {
                $skipped[] = ['name' => $logicalPath, 'reason' => 'nesaugus kelias'];
                continue;
            }

            if (preg_match('#(^|/)__MACOSX(/|$)|(^|/)\._|(^|/)\.DS_Store$#', $logicalPath) === 1) {
                $skipped[] = ['name' => $logicalPath, 'reason' => 'sisteminis failas'];
                continue;
            }

            $baseExt = strtolower(pathinfo($logicalPath, PATHINFO_EXTENSION));
            if (! in_array($baseExt, self::ALLOWED_EXT, true)) {
                $skipped[] = ['name' => $logicalPath, 'reason' => 'ne Word / Excel'];
                continue;
            }

            $logicalBase = basename($logicalPath);
            if ($logicalBase === '' || str_starts_with($logicalBase, '.')) {
                $skipped[] = ['name' => $logicalPath, 'reason' => 'praleistas'];
                continue;
            }

            $dirFromZip = dirname($logicalPath);
            if ($dirFromZip === '.' || $dirFromZip === '') {
                $sanitizedSubdir = '';
            } else {
                $sanitizedSubdir = $this->sanitizeZipRelativeDirectory($dirFromZip);
                if ($sanitizedSubdir === null) {
                    $skipped[] = ['name' => $logicalPath, 'reason' => 'nesaugus aplankų kelias'];
                    continue;
                }
            }

            $targetDirectory = $this->mergeTemplateDirectory($directory, $sanitizedSubdir);

            $stat = $archive->statIndex($i);
            if ($stat === false) {
                $skipped[] = ['name' => $logicalPath, 'reason' => 'nepavyko skaityti metaduomenų'];
                continue;
            }

            $uncompressedSize = (int) ($stat['size'] ?? 0);
            if ($uncompressedSize > self::MAX_ENTRY_BYTES) {
                $skipped[] = ['name' => $logicalPath, 'reason' => 'failas per didelis'];
                continue;
            }

            if ($uncompressedSize === 0) {
                $skipped[] = ['name' => $logicalPath, 'reason' => 'tuščias failas'];
                continue;
            }

            $content = $archive->getFromIndex($i);
            if ($content === false || $content === '') {
                $skipped[] = ['name' => $logicalPath, 'reason' => 'nepavyko skaityti'];
                continue;
            }

            $dirKey = strtolower(str_replace('\\', '/', $targetDirectory));
            if (! isset($usedPerTargetDir[$dirKey])) {
                $usedPerTargetDir[$dirKey] = [];
            }

            $uniqueName = $this->allocateUniqueFilename($logicalBase, $usedPerTargetDir[$dirKey]);
            $tmpBase    = sys_get_temp_dir() . '/zipimp_' . bin2hex(random_bytes(12)) . '.' . $baseExt;

            try {
                if (file_put_contents($tmpBase, $content) === false) {
                    $skipped[] = ['name' => $logicalPath, 'reason' => 'laikinas įrašymas nepavyko'];
                    continue;
                }

                $uploaded = new UploadedFile(
                    $tmpBase,
                    $uniqueName,
                    null,
                    \UPLOAD_ERR_OK,
                    true
                );

                $result = $this->addWordDocument->addWordDocument($uploaded, $targetDirectory, $root);
                $status = $result['status'] ?? 'FAIL';

                $row = [
                    'source' => $logicalPath,
                    'status' => $status,
                ];
                if ($status === 'SUCCESS' && isset($result['file'])) {
                    $row['file'] = $result['file'];
                }
                if ($status === 'FAIL') {
                    $row['error'] = $result['error'] ?? 'unknown';
                }
                $results[] = $row;
            } finally {
                if (is_file($tmpBase)) {
                    @unlink($tmpBase);
                }
            }
        }

        $archive->close();

        $successCount = 0;
        $failCount    = 0;
        foreach ($results as $r) {
            if (($r['status'] ?? '') === 'SUCCESS') {
                ++$successCount;
            } else {
                ++$failCount;
            }
        }

        if ($successCount === 0 && $results === []) {
            return [
                'status'  => 'FAIL',
                'error'   => 'Archyve nerasta tinkamų .doc/.docx/.xls/.xlsx failų.',
                'results' => [],
                'skipped' => $skipped,
            ];
        }

        $overall = $failCount === 0 ? 'SUCCESS' : ($successCount > 0 ? 'PARTIAL' : 'FAIL');

        return [
            'status'  => $overall,
            'results' => $results,
            'skipped' => $skipped,
        ];
    }

    /**
     * Sujungia UI pasirinktą katalogą su sanituotu ZIP poaplankiu.
     */
    private function mergeTemplateDirectory(string $baseDirectory, string $sanitizedZipSubdir): string
    {
        $base = trim(str_replace('\\', '/', $baseDirectory), '/');
        $sub  = trim(str_replace('\\', '/', $sanitizedZipSubdir), '/');

        if ($sub === '') {
            return $base;
        }

        if ($base === '') {
            return $sub;
        }

        return $base . '/' . $sub;
    }

    /**
     * ZIP įrašo kelias be failo pavadinimo — tik aplankai, be „..“ ir netinkamų simbolių.
     *
     * @return string|null null jei kelias nesaugus
     */
    private function sanitizeZipRelativeDirectory(string $dir): ?string
    {
        $dir = trim(str_replace('\\', '/', $dir), '/');
        if ($dir === '') {
            return '';
        }

        $parts = explode('/', $dir);
        $out   = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.' || $part === '..') {
                return null;
            }

            if (preg_match('/[\\\\\\/:\*\?"<>\|\x00-\x1F]/u', $part) === 1) {
                return null;
            }

            $out[] = $part;
        }

        return implode('/', $out);
    }

    /**
     * @param array<string, true> $usedNames
     */
    private function allocateUniqueFilename(string $logicalBase, array &$usedNames): string
    {
        $logicalBase = str_replace(['\\', '/'], '_', $logicalBase);
        $key         = strtolower($logicalBase);
        if (! isset($usedNames[$key])) {
            $usedNames[$key] = true;

            return $logicalBase;
        }

        $stem = pathinfo($logicalBase, PATHINFO_FILENAME);
        $ext  = pathinfo($logicalBase, PATHINFO_EXTENSION);
        $n    = 2;
        do {
            $candidate = $stem . '_' . $n . ($ext !== '' ? '.' . $ext : '');
            $k         = strtolower($candidate);
            ++$n;
        } while (isset($usedNames[$k]));

        $usedNames[$k] = true;

        return $candidate;
    }
}
