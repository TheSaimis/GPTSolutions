<?php

declare(strict_types=1);

namespace App\Services;

final class GetPDF
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $libreOfficeBin,
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
    
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['doc', 'docx'], true)) {
            throw new \InvalidArgumentException(
                "Palaikomi tik .doc/.docx. Bandytas: " . $filePath
            );
        }
    
        $outputDir = $this->projectDir . '/var/pdf';
    
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }
    
        $hash = md5($baseDir . $relativePath . filemtime($filePath));
        $pdfName = pathinfo($filePath, PATHINFO_FILENAME) . '_' . $hash . '.pdf';
        $pdfPath = $outputDir . '/' . $pdfName;
    
        if (file_exists($pdfPath)) {
            return $pdfPath;
        }
    
        $bin = trim($this->libreOfficeBin, "\"'");
    
        if (!file_exists($bin)) {
            throw new \RuntimeException(
                "LibreOffice nerastas.\n" .
                "Ieškota: " . $bin
            );
        }
    
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
}
