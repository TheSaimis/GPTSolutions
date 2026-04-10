<?php

declare(strict_types=1);

namespace App\Services;

final class GetPDF
{
    public function __construct(
        private readonly string $projectDir,
        private readonly LibreOfficeBinResolver $libreOfficeBinResolver,
    ) {}

    /**
     * Konvertuoja .docx/.doc failą į PDF naudojant LibreOffice.
     * Grąžina pilną kelią iki sugeneruoto PDF failo.
     *
     * @param string $relativePath Santykinis kelias (pvz. "4 Tvarkos/file.docx" arba "CompanyName/doc.docx")
     * @param string $baseDir      Bazinis katalogas nuo projectDir (pvz. "templates", "var/generated")
     * @throws \InvalidArgumentException Jei failas nerastas arba kelias išeina iš baseDir ribų
     * @throws \RuntimeException Jei konvertavimas nepavyksta
     */
    public function convertToPdf(string $relativePath, string $baseDir = 'templates'): string
    {
        $relativePath = str_replace('\\', '/', urldecode($relativePath));
        $baseDir = trim(str_replace('\\', '/', $baseDir), '/');
        
        $sourceDir = $this->projectDir . '/' . $baseDir;
        $filePath = realpath($sourceDir . '/' . $relativePath);

        if ($filePath === false) {
            throw new \InvalidArgumentException(
                "Failas nerastas.\n" .
                "Ieškota: " . $sourceDir . '/' . $relativePath . "\n" .
                "ProjectDir: " . $this->projectDir . "\n" .
                "SourceDir: " . $sourceDir
            );
        }

        $sourceResolved = realpath($sourceDir);
        if ($sourceResolved === false || !str_starts_with($filePath, $sourceResolved)) {
            throw new \InvalidArgumentException(
                "Kelias išeina iš " . $baseDir . " ribų: " . $filePath
            );
        }
    
        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException(
                "Failas neprieinamas: " . $filePath
            );
        }

        // PHP caches stat() per path; after an in-place replace, filemtime/size can be stale and
        // we'd wrongly reuse an old cached PDF with the same hash.
        clearstatcache(true, $filePath);
    
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['doc', 'docx', 'xls', 'xlsx'], true)) {
            throw new \InvalidArgumentException(
                "Palaikomi tik .doc/.docx/.xls/.xlsx. Bandytas: " . $filePath
            );
        }
    
        $outputDir = $this->projectDir . '/var/pdf';
    
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }
    
        $hash = md5($baseDir . "\0" . $relativePath . "\0" . filemtime($filePath) . "\0" . filesize($filePath));
        $pdfName = pathinfo($filePath, PATHINFO_FILENAME) . '_' . $hash . '.pdf';
        $pdfPath = $outputDir . '/' . $pdfName;
    
        if (file_exists($pdfPath)) {
            return $pdfPath;
        }
    
        $bin = $this->libreOfficeBinResolver->resolve();
    
        $command = sprintf(
            '%s --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($bin),
            escapeshellarg($outputDir),
            escapeshellarg($filePath)
        );
    
        exec($command, $output, $exitCode);
    
        if ($exitCode !== 0) {
            throw new \RuntimeException(
                "PDF konvertavimas nepavyko.\n" .
                "Komanda: " . $command . "\n" .
                "Output:\n" . implode("\n", $output)
            );
        }
    
        $libreOutputName = pathinfo($filePath, PATHINFO_FILENAME) . '.pdf';
        $libreOutputPath = $outputDir . '/' . $libreOutputName;
    
        if (file_exists($libreOutputPath) && $libreOutputPath !== $pdfPath) {
            rename($libreOutputPath, $pdfPath);
        }
    
        if (!file_exists($pdfPath)) {
            throw new \RuntimeException(
                "PDF nesugeneruotas.\n" .
                "Tikėtasi: " . $pdfPath
            );
        }
    
        return $pdfPath;
    }

    /**
     * Konvertuoja failą pagal absoliutų kelią (turi būti projekto kataloge) į PDF.
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function convertAbsolutePathToPdf(string $absolutePath): string
    {
        $filePath = realpath($absolutePath);
        if ($filePath === false || ! is_readable($filePath)) {
            throw new \InvalidArgumentException('Failas neprieinamas: ' . $absolutePath);
        }

        $projectRoot = realpath($this->projectDir);
        if ($projectRoot === false) {
            throw new \RuntimeException('Projekto katalogas neprieinamas');
        }

        $normFile = str_replace('\\', '/', $filePath);
        $normRoot = str_replace('\\', '/', $projectRoot);
        if (! str_starts_with($normFile, $normRoot)) {
            throw new \InvalidArgumentException('Kelias turi būti projekto viduje');
        }

        clearstatcache(true, $filePath);

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['doc', 'docx', 'xls', 'xlsx'], true)) {
            throw new \InvalidArgumentException(
                'Palaikomi tik .doc/.docx/.xls/.xlsx. Bandytas: ' . $filePath
            );
        }

        $outputDir = $this->projectDir . '/var/pdf';
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $hash = md5('abs:' . $filePath . "\0" . filemtime($filePath) . "\0" . filesize($filePath));
        $pdfName = pathinfo($filePath, PATHINFO_FILENAME) . '_' . $hash . '.pdf';
        $pdfPath = $outputDir . '/' . $pdfName;

        if (file_exists($pdfPath)) {
            return $pdfPath;
        }

        $bin = $this->libreOfficeBinResolver->resolve();

        $command = sprintf(
            '%s --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($bin),
            escapeshellarg($outputDir),
            escapeshellarg($filePath)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                "PDF konvertavimas nepavyko.\n" .
                'Komanda: ' . $command . "\n" .
                'Output:' . "\n" . implode("\n", $output)
            );
        }

        $libreOutputName = pathinfo($filePath, PATHINFO_FILENAME) . '.pdf';
        $libreOutputPath = $outputDir . '/' . $libreOutputName;

        if (file_exists($libreOutputPath) && $libreOutputPath !== $pdfPath) {
            rename($libreOutputPath, $pdfPath);
        }

        if (! file_exists($pdfPath)) {
            throw new \RuntimeException('PDF nesugeneruotas. Tikėtasi: ' . $pdfPath);
        }

        return $pdfPath;
    }
}