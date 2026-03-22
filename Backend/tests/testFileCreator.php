<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ConvertDocToDocx;
use App\Services\LibreOfficeBinResolver;
use App\Services\CreateFile;
use App\Services\DocxSplitMacroReplacer;
use App\Services\ManagerGenderResolver;
use App\Services\Metadata\DocxMetadataService;
use App\Services\Namer;

$projectDir = dirname(__DIR__);

$fakeLibre = $projectDir . '/_missing_libreoffice_for_tests_' . uniqid('', true);
$service = new CreateFile(
    $projectDir,
    new Namer(new ManagerGenderResolver()),
    new DocxMetadataService(),
    new ConvertDocToDocx($projectDir, new LibreOfficeBinResolver($fakeLibre, 'test')),
    new DocxSplitMacroReplacer()
);

$data = [
    'directory'    => '4 Tvarkos',
    'template'     => '3 Mobingo Tvarka 2023.docx',
    'companyName'  => 'Test Company',
    'code'         => 'TEST123',
    'documentDate' => '2026-02-21',
    'role'         => 'Administrator',
];

try {
    $result = $service->createWordDocument($data);
    echo "SUCCESS:\n$result\n";
} catch (Throwable $e) {
    echo "ERROR:\n" . $e->getMessage() . "\n";
}