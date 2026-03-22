<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Konvertuoja senąjį Word .doc (OLE) į .docx (OOXML) per LibreOffice,
 * kad PhpWord TemplateProcessor galėtų apdoroti šabloną.
 */
final class ConvertDocToDocx
{
    public function __construct(
        private readonly string $projectDir,
        private readonly LibreOfficeBinResolver $libreOfficeBinResolver,
    ) {}

    /**
     * Jei kelias jau rodo į .docx, grąžina tą patį kelią. Jei .doc — konvertuoja ir grąžina
     * kelią iki podokumento kataloge var/docx-from-doc (su cache pagal failo turinį).
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function ensureDocxForTemplate(string $absolutePath): string
    {
        $real = realpath($absolutePath);
        if ($real === false || ! is_readable($real)) {
            throw new \InvalidArgumentException('Šablonas neprieinamas: ' . $absolutePath);
        }

        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if ($ext === 'docx') {
            return $real;
        }
        if ($ext !== 'doc') {
            throw new \InvalidArgumentException('Tik .doc arba .docx Word šablonai: ' . $absolutePath);
        }

        $outputDir = $this->projectDir . '/var/docx-from-doc';
        if (! is_dir($outputDir) && ! mkdir($outputDir, 0775, true) && ! is_dir($outputDir)) {
            throw new \RuntimeException('Nepavyko sukurti katalogo: ' . $outputDir);
        }

        $hash    = md5($real . (string) filemtime($real));
        $outName = pathinfo($real, PATHINFO_FILENAME) . '_' . $hash . '.docx';
        $outPath = $outputDir . '/' . $outName;

        if (file_exists($outPath) && is_readable($outPath)) {
            return $outPath;
        }

        $bin = $this->libreOfficeBinResolver->resolve();

        $workDir = $outputDir . '/_work_' . $hash;
        if (is_dir($workDir)) {
            $this->removeDirectory($workDir);
        }
        if (! mkdir($workDir, 0775, true) && ! is_dir($workDir)) {
            throw new \RuntimeException('Nepavyko sukurti laikino katalogo: ' . $workDir);
        }

        $srcInWork = $workDir . '/source.doc';
        if (! copy($real, $srcInWork)) {
            $this->removeDirectory($workDir);
            throw new \RuntimeException('Nepavyko nukopijuoti šablono konvertavimui: ' . $real);
        }

        $command = sprintf(
            '%s --headless --convert-to docx --outdir %s %s 2>&1',
            escapeshellarg($bin),
            escapeshellarg($workDir),
            escapeshellarg($srcInWork)
        );

        exec($command, $output, $exitCode);

        $produced = $workDir . '/source.docx';

        if ($exitCode !== 0 || ! file_exists($produced)) {
            $this->removeDirectory($workDir);
            throw new \RuntimeException(
                '.doc → .docx konvertavimas nepavyko.' . "\n" .
                'Komanda: ' . $command . "\n" .
                implode("\n", $output)
            );
        }

        if (file_exists($outPath)) {
            @unlink($outPath);
        }
        if (! rename($produced, $outPath)) {
            @copy($produced, $outPath);
            @unlink($produced);
        }

        $this->removeDirectory($workDir);

        if (! file_exists($outPath)) {
            throw new \RuntimeException('Nepavyko išsaugoti konvertuoto .docx: ' . $outPath);
        }

        return $outPath;
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
