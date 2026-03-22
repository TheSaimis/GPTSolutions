<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Randa LibreOffice „soffice“ vykdomąjį failą: pirmiausia pagal LIBREOFFICE_BIN,
 * tada pagal tipinius OS katalogus, PATH, Windows registrą, WinGet/Scoop/Chocolatey (ne test aplinkoje).
 */
final class LibreOfficeBinResolver
{
    public function __construct(
        private readonly string $configuredPath,
        private readonly string $kernelEnv,
    ) {}

    public function resolve(): string
    {
        $configured = trim($this->configuredPath, "\"' ");
        $candidates = [];

        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $allowFallback = $this->kernelEnv !== 'test';
        if ($allowFallback) {
            $candidates = array_merge($candidates, $this->fallbackCandidates());
        }

        $tried = [];
        foreach (array_unique(array_filter($candidates, static fn (string $p): bool => $p !== '')) as $path) {
            $tried[] = $path;
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        if ($allowFallback) {
            $finder = new ExecutableFinder();
            $fromPath = $finder->find('soffice');
            if ($fromPath !== null && is_file($fromPath) && is_readable($fromPath)) {
                return $fromPath;
            }
            $tried[] = 'PATH (ExecutableFinder: soffice)';

            if (DIRECTORY_SEPARATOR === '\\') {
                $fromReg = $this->findFromWindowsRegistry();
                if ($fromReg !== null) {
                    return $fromReg;
                }
                $tried[] = 'Windows registro (HKLM/HKCU LibreOffice)';
            }
        }

        throw new \RuntimeException(
            "LibreOffice nerastas.\n" .
            "Šiame kompiuteryje nerastas soffice.exe (LibreOffice neįdiegtas arba įdiegtas nestandartinėje vietoje).\n" .
            "Įdiekite: „winget install -e --id TheDocumentFoundation.LibreOffice“ arba https://www.libreoffice.org/download/\n" .
            "Arba .env nustatykite LIBREOFFICE_BIN į pilną kelią iki soffice.exe.\n" .
            "Bandyti keliai:\n" . implode("\n", $tried)
        );
    }

    /**
     * @return list<string>
     */
    private function fallbackCandidates(): array
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $pf = rtrim((string) (getenv('ProgramFiles') ?: 'C:\\Program Files'), '\\');
            $pfx86 = rtrim((string) (getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)'), '\\');
            $pw64 = getenv('ProgramW6432');
            $local = $this->windowsLocalAppData();
            $profile = $this->windowsUserProfile();
            $programData = rtrim((string) (getenv('ProgramData') ?: 'C:\\ProgramData'), '\\');

            $out = [
                $pf . '\\LibreOffice\\program\\soffice.exe',
                $pfx86 . '\\LibreOffice\\program\\soffice.exe',
                $pf . '\\OpenOffice 4\\program\\soffice.exe',
                $pfx86 . '\\OpenOffice 4\\program\\soffice.exe',
            ];
            if ($pw64) {
                $out[] = rtrim((string) $pw64, '\\') . '\\LibreOffice\\program\\soffice.exe';
            }
            if ($local) {
                $base = rtrim((string) $local, '\\');
                $out[] = $base . '\\Programs\\LibreOffice\\program\\soffice.exe';
                foreach (glob($base . '\\Microsoft\\WinGet\\Packages\\TheDocumentFoundation.LibreOffice_*\\LibreOffice\\program\\soffice.exe') ?: [] as $p) {
                    $out[] = $p;
                }
            }
            if ($profile) {
                $home = rtrim((string) $profile, '\\');
                foreach ([
                    $home . '\\scoop\\apps\\libreoffice\\current\\LibreOffice\\program\\soffice.exe',
                    $home . '\\scoop\\apps\\libreoffice-fresh\\current\\LibreOffice\\program\\soffice.exe',
                ] as $p) {
                    $out[] = $p;
                }
            }
            foreach (glob($programData . '\\chocolatey\\lib\\libreoffice*', \GLOB_ONLYDIR) ?: [] as $libDir) {
                foreach (glob($libDir . '\\tools\\*', \GLOB_ONLYDIR) ?: [] as $toolsDir) {
                    $p = $toolsDir . '\\program\\soffice.exe';
                    if (is_file($p)) {
                        $out[] = $p;
                    }
                }
            }

            return $out;
        }

        return [
            '/usr/bin/soffice',
            '/usr/lib/libreoffice/program/soffice',
            '/snap/bin/libreoffice',
        ];
    }

    private function findFromWindowsRegistry(): ?string
    {
        $roots = [
            'HKLM\\SOFTWARE\\LibreOffice\\LibreOffice',
            'HKLM\\SOFTWARE\\WOW6432Node\\LibreOffice\\LibreOffice',
            'HKCU\\SOFTWARE\\LibreOffice\\LibreOffice',
        ];

        foreach ($roots as $key) {
            $process = new Process(['reg', 'query', $key, '/s'], null, null, null, 12.0);
            try {
                $process->run();
            } catch (\Throwable) {
                continue;
            }
            if (! $process->isSuccessful()) {
                continue;
            }
            foreach (preg_split('/\r\n|\r|\n/', $process->getOutput()) as $line) {
                $line = trim($line);
                if ($line === '' || ! str_contains($line, 'REG_SZ')) {
                    continue;
                }
                if (! preg_match('/REG_SZ\s+(.+)$/i', $line, $m)) {
                    continue;
                }
                $value = trim($m[1], " \t\"");
                if ($value === '') {
                    continue;
                }
                if (str_ends_with(strtolower($value), '.exe') && is_file($value) && is_readable($value)) {
                    return $value;
                }
                $candidate = rtrim($value, '\\') . '\\soffice.exe';
                if (is_file($candidate) && is_readable($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * IIS / FastCGI dažnai neperduoda LOCALAPPDATA į getenv — bandome HOMEDRIVE+HOMEPATH ir $_SERVER.
     */
    private function windowsLocalAppData(): ?string
    {
        $v = getenv('LOCALAPPDATA');
        if (is_string($v) && $v !== '') {
            return $v;
        }
        if (isset($_SERVER['LOCALAPPDATA']) && is_string($_SERVER['LOCALAPPDATA']) && $_SERVER['LOCALAPPDATA'] !== '') {
            return $_SERVER['LOCALAPPDATA'];
        }
        $home = $this->windowsUserProfile();
        if ($home !== null) {
            return $home . '\\AppData\\Local';
        }
        $drive = getenv('HOMEDRIVE') ?: 'C:';
        $homePath = getenv('HOMEPATH');
        if (is_string($homePath) && $homePath !== '') {
            return rtrim($drive, '\\') . rtrim($homePath, '\\') . '\\AppData\\Local';
        }

        return null;
    }

    private function windowsUserProfile(): ?string
    {
        $v = getenv('USERPROFILE');
        if (is_string($v) && $v !== '') {
            return rtrim($v, '\\');
        }
        if (isset($_SERVER['USERPROFILE']) && is_string($_SERVER['USERPROFILE']) && $_SERVER['USERPROFILE'] !== '') {
            return rtrim($_SERVER['USERPROFILE'], '\\');
        }
        $drive = getenv('HOMEDRIVE') ?: 'C:';
        $homePath = getenv('HOMEPATH');
        if (is_string($homePath) && $homePath !== '') {
            return rtrim(rtrim($drive, '\\') . rtrim($homePath, '\\'), '\\');
        }

        return null;
    }
}