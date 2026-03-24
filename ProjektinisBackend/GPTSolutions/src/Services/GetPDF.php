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
     * Konvertuoja .docx/.doc/.xls/.xlsx failą į PDF naudojant LibreOffice.
     * Grąžina pilną kelią iki sugeneruoto PDF failo.
     *
     * @param string $relativePath Santykinis kelias (pvz. "4 Tvarkos/file.docx" arba "CompanyName/doc.docx")
     * @param string $baseDir      Bazinis katalogas nuo projectDir (pvz. "templates", "generated", "var/generated")
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
        if (!in_array($ext, ['doc', 'docx', 'xls', 'xlsx'], true)) {
            throw new \InvalidArgumentException(
                "Palaikomi tik .doc/.docx/.xls/.xlsx. Bandytas: " . $filePath
            );
        }

        $outputDir = $this->projectDir . '/var/pdf';

        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new \RuntimeException(
                "Nepavyko sukurti PDF katalogo: " . $outputDir
            );
        }

        if (!is_writable($outputDir)) {
            throw new \RuntimeException(
                "PDF katalogas nėra įrašomas: " . $outputDir
            );
        }

        $hash = md5($baseDir . $relativePath . (string) filemtime($filePath));
        $pdfName = pathinfo($filePath, PATHINFO_FILENAME) . '_' . $hash . '.pdf';
        $pdfPath = $outputDir . '/' . $pdfName;

        if (file_exists($pdfPath)) {
            return $pdfPath;
        }

        $bin = $this->libreOfficeBinResolver->resolve();

        $profileDir = '/tmp/libreoffice-profile-' . md5($filePath);
        if (!is_dir($profileDir) && !mkdir($profileDir, 0775, true) && !is_dir($profileDir)) {
            throw new \RuntimeException(
                "Nepavyko sukurti LibreOffice profilio katalogo: " . $profileDir
            );
        }

        if (!is_writable($profileDir)) {
            throw new \RuntimeException(
                "LibreOffice profilio katalogas nėra įrašomas: " . $profileDir
            );
        }

        putenv('HOME=/tmp');
        putenv('TMPDIR=/tmp');

        $profileUri = 'file://' . $profileDir;

        $command = sprintf(
            '%s --headless --nologo --nofirststartwizard -env:UserInstallation=%s --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($bin),
            escapeshellarg($profileUri),
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
            if (!rename($libreOutputPath, $pdfPath)) {
                throw new \RuntimeException(
                    "Nepavyko pervadinti PDF failo iš {$libreOutputPath} į {$pdfPath}"
                );
            }
        }

        if (!file_exists($pdfPath)) {
            throw new \RuntimeException(
                "PDF nesugeneruotas.\n" .
                "Tikėtasi: " . $pdfPath . "\n" .
                "LibreOffice output:\n" . implode("\n", $output)
            );
        }

        return $pdfPath;
    }
}