<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\CreateFile;

$projectDir = dirname(__DIR__);

$service = new CreateFile($projectDir);

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