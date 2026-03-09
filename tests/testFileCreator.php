<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\CreateFile;
use App\Services\ManagerGenderResolver;
use App\Services\Namer;

$projectDir = dirname(__DIR__);
$namer = new Namer(new ManagerGenderResolver());
$service = new CreateFile($projectDir, $namer);

$data = [
    'directory'    => '4 Tvarkos',
    'template'     => '3 Mobingo Tvarka 2023.docx',
    'companyName'  => 'Test Company',
    'code'         => 'TEST123',
    'documentDate' => '2026-02-21',
    'role'         => 'Administrator',
    'managerType'  => 'vadovas',
    'vardas'       => 'Tomas',
    'pavarde'      => 'Jonaitis',
];

try {
    $result = $service->createWordDocument($data);
    echo "SUCCESS:\n$result\n";
} catch (Throwable $e) {
    echo "ERROR:\n" . $e->getMessage() . "\n";
}