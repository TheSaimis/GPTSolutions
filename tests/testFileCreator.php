<?php

require __DIR__ . '/bootstrap.php';

use App\Kernel;
use App\Services\CreateFile;

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

/** @var CreateFile $service */
$service = $kernel->getContainer()->get(CreateFile::class);

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
