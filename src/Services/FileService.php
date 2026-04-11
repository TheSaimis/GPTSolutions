<?php

declare (strict_types = 1);

namespace App\Services;

/**
 * Bendras failÅ³ servisas â€“ operuoja su bet kuriuo katalogu.
 * BaseDir perduodamas per kiekvienÄ… metodÄ… ir tikrinamas.
 *
 * @param string $baseDir Katalogas nuo projectDir (pvz. "templates", "generated")
 */
final class FileService
{
    private const SUCCESS = 'SUCCESS';
    private const FAIL    = 'FAIL';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * GrÄ…Å¾ina pilnÄ… kelÄ¯ ir tikrina, kad jis bÅ«tÅ³ baseDir ribose.
     */
    public function resolvePath(string $baseDir, string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            return null;
        }

        $baseFull = $this->getBaseFullPath($baseDir);
        if ($baseFull === null) {
            return null;
        }

        $fullPath = $baseFull . '/' . $path;
        $resolved = realpath($fullPath);
        if ($resolved === false || ! str_starts_with($resolved, $baseFull)) {
            return null;
        }

        return $resolved;
    }

    /**
     * Soft-delete: perkelia failÄ… Ä¯ /deleted/{baseDir}/{path} struktÅ«rÄ….
     *
     * @return 'SUCCESS'|'FAIL'
     */
    public function delete(string $baseDir, string $path, array $allowedExtensions = ['doc', 'docx', 'xls', 'xlsx', 'url']): string
    {
        $fullPath = $this->resolvePath($baseDir, $path);
        if ($fullPath === null) {
            return self::FAIL;
        }

        try {
            if (! is_file($fullPath)) {
                return self::FAIL;
            }
            if ($allowedExtensions !== [] && ! $this->isAllowedExtension($fullPath, $allowedExtensions)) {
                return self::FAIL;
            }

            $deletedDir = $this->projectDir . '/deleted/' . $baseDir . '/' . dirname($path);
            if (! is_dir($deletedDir)) {
                mkdir($deletedDir, 0775, true);
            }
            $dest = $deletedDir . '/' . basename($path);
            return rename($fullPath, $dest) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    /**
     * @return 'SUCCESS'|'FAIL'
     */
    public function rename(string $baseDir, string $path, string $newName, array $allowedExtensions = ['doc', 'docx', 'xls', 'xlsx', 'url']): string
    {
        $fullPath = $this->resolvePath($baseDir, $path);
        if ($fullPath === null) {
            return self::FAIL;
        }

        $newName = trim(str_replace(['\\', '/'], '', $newName));
        if ($newName === '' || ($allowedExtensions !== [] && ! $this->isAllowedExtension($newName, $allowedExtensions))) {
            return self::FAIL;
        }

        $baseFull = $this->getBaseFullPath($baseDir);
        if ($baseFull === null) {
            return self::FAIL;
        }

        $directory   = dirname($path);
        $newRelPath  = ($directory !== '.' ? $directory . '/' : '') . $newName;
        $newFullPath = $baseFull . '/' . $newRelPath;

        try {
            if (! is_file($fullPath) || file_exists($newFullPath)) {
                return self::FAIL;
            }
            return rename($fullPath, $newFullPath) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    /**
     * Perkelia failÄ… Ä¯ kitÄ… direktorijÄ… tame paÄiame baseDir.
     *
     * @return 'SUCCESS'|'FAIL'
     */
    public function move(string $baseDir, string $path, string $newDirectory, array $allowedExtensions = ['doc', 'docx', 'xls', 'xlsx', 'url']): string
    {
        $fullPath = $this->resolvePath($baseDir, $path);
        if ($fullPath === null || ! is_file($fullPath)) {
            return self::FAIL;
        }

        if ($allowedExtensions !== [] && ! $this->isAllowedExtension($fullPath, $allowedExtensions)) {
            return self::FAIL;
        }

        $baseFull = $this->getBaseFullPath($baseDir);
        if ($baseFull === null) {
            return self::FAIL;
        }

        $newDirectory = trim(str_replace('\\', '/', $newDirectory), '/');
        if (str_contains($newDirectory, '..')) {
            return self::FAIL;
        }

        $fileName    = basename($fullPath);
        $newDirFull  = $baseFull . '/' . $newDirectory;
        $newFullPath = $newDirFull . '/' . $fileName;

        if (file_exists($newFullPath)) {
            return self::FAIL;
        }

        try {
            if (! is_dir($newDirFull)) {
                mkdir($newDirFull, 0775, true);
            }
            return rename($fullPath, $newFullPath) ? self::SUCCESS : self::FAIL;
        } catch (\Throwable) {
            return self::FAIL;
        }
    }

    /**
     * Sukuria Windows tipo interneto nuorodos failÄ… (.url) su [InternetShortcut] sekcija.
     *
     * @return array{status: 'SUCCESS'|'FAIL', file?: array<string, mixed>, error?: string}
     */
    public function createInternetShortcut(string $baseDir, string $directory, string $displayName, string $targetUrl): array
    {
        $baseFull = $this->getBaseFullPath($baseDir);
        if ($baseFull === null) {
            return ['status' => self::FAIL, 'error' => 'Invalid base directory'];
        }

        if (! filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return ['status' => self::FAIL, 'error' => 'Invalid URL'];
        }
        $scheme = parse_url($targetUrl, PHP_URL_SCHEME);
        if (! is_string($scheme) || ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return ['status' => self::FAIL, 'error' => 'Only http and https URLs are allowed'];
        }

        $directory = trim(str_replace('\\', '/', $directory), '/');
        if (str_contains($directory, '..')) {
            return ['status' => self::FAIL, 'error' => 'Netinkamas katalogas'];
        }

        $filename = $this->sanitizeShortcutFileName($displayName);
        $targetDir = $directory !== '' ? $baseFull . '/' . $directory : $baseFull;
        $resolved = realpath($targetDir);
        if ($resolved === false || ! is_dir($resolved) || ! str_starts_with($resolved, $baseFull)) {
            return ['status' => self::FAIL, 'error' => 'Paskirties katalogas nerastas'];
        }

        $fullPath = $resolved . '/' . $filename;
        if (file_exists($fullPath)) {
            return ['status' => self::FAIL, 'error' => 'Nuoroda tokiu pavadinimu jau yra'];
        }

        $ini = "[InternetShortcut]\r\nURL=" . $targetUrl . "\r\n";
        if (file_put_contents($fullPath, $ini, LOCK_EX) === false) {
            return ['status' => self::FAIL, 'error' => 'Nepavyko įrašyti nuorodos failo'];
        }

        $relPath = $directory !== '' ? $directory . '/' . $filename : $filename;

        $entry = [
            'name'       => $filename,
            'type'       => 'file',
            'size'       => filesize($fullPath) ?: 0,
            'path'       => $relPath,
            'createdAt'  => date('Y-m-d H:i:s', filectime($fullPath)),
            'modifiedAt' => date('Y-m-d H:i:s', filemtime($fullPath)),
            'metadata'   => $this->readUrlShortcutMetadata($fullPath),
        ];

        return ['status' => self::SUCCESS, 'file' => $entry];
    }

    /**
     * GrÄ…Å¾ina katalogo turinÄ¯ (katalogai ir failai).
     *
     * @return array{name: string, type: 'directory'|'file', path: string, children?: array}[]
     */
    public function listDirectory(string $baseDir, string $path = ''): array
    {
        $baseFull = $this->getBaseFullPath($baseDir);
        if ($baseFull === null) {
            return [];
        }

        $targetPath = $path !== '' ? $baseFull . '/' . trim(str_replace('\\', '/', $path), '/') : $baseFull;
        $resolved   = realpath($targetPath);
        if ($resolved === false || ! is_dir($resolved) || ! str_starts_with($resolved, $baseFull)) {
            return [];
        }

        $result = [];
        $items  = scandir($resolved) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '~') || $item === 'desktop.ini') {
                continue;
            }
            $itemPath = $resolved . '/' . $item;
            $relPath  = $path !== '' ? $path . '/' . $item : $item;
            if (is_dir($itemPath)) {
                $result[] = [
                    'name'     => $item,
                    'type'     => 'directory',
                    'path'     => $relPath,
                    'children' => $this->listDirectory($baseDir, $relPath),
                ];
            } else {
                $entry = [
                    'name'       => $item,
                    'type'       => 'file',
                    'size'       => filesize($itemPath),
                    'path'       => $relPath,
                    'createdAt'  => date('Y-m-d H:i:s', filectime($itemPath)),
                    'modifiedAt' => date('Y-m-d H:i:s', filemtime($itemPath)),
                ];

                $ext = strtolower(pathinfo($itemPath, PATHINFO_EXTENSION));
                if ($ext === 'docx' || $ext === 'xlsx') {
                    $entry['metadata'] = $this->readDocxMetadata($itemPath);
                } elseif ($ext === 'url') {
                    $entry['metadata'] = [
                        'core'   => [],
                        'custom' => [
                            'mimeType' => 'application/internet-shortcut',
                            'linkUrl'  => $this->readInternetShortcutUrl($itemPath),
                        ],
                    ];
                }

                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * GrÄ…Å¾ina dokumento metaduomenis (core + custom). Tik .docx ir .xlsx failams.
     *
     * @return array{path: string, filename: string, metadata: array{core: array, custom: array}}|null
     */
    public function getFileMetadata(string $baseDir, string $path): ?array
    {
        $resolved = $this->resolvePath($baseDir, $path);
        if ($resolved === null || ! is_file($resolved)) {
            return null;
        }

        $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        if (! in_array($ext, ['docx', 'xlsx'], true)) {
            return null;
        }

        $metadata = $this->readDocxMetadata($resolved);

        return [
            'path'     => $path,
            'filename' => basename($path),
            'metadata' => $metadata,
        ];
    }

    /**
     * GrÄ…Å¾ina iÅ¡trintÅ³ dokumentÅ³ katalogo turinÄ¯. StruktÅ«ra: deleted/{baseDir}/{path}.
     *
     * @param string $baseDir templates|generated arba tuÅ¡Äia (tada grÄ…Å¾ina abu)
     * @return array{baseDir: string, path: string, name: string, type: string, size?: int, children?: array}[]
     */
    public function listDeleted(string $baseDir = ''): array
    {
        $deletedRoot = $this->projectDir . '/deleted';
        if (! is_dir($deletedRoot)) {
            return [];
        }

        $result = [];
        $dirs   = $baseDir !== '' ? [$baseDir] : ['templates', 'generated', 'archive'];

        foreach ($dirs as $dir) {
            if (! in_array($dir, ['templates', 'generated', 'archive'], true)) {
                continue;
            }
            $fullDir = $deletedRoot . '/' . $dir;
            if (! is_dir($fullDir)) {
                continue;
            }
            $items = $this->listDeletedRecursive($fullDir, $deletedRoot . '/' . $dir, $dir);
            foreach ($items as $item) {
                $item['baseDir'] = $dir;
                $result[]        = $item;
            }
        }

        return $result;
    }

    /**
     * @return array{path: string, name: string, type: string, size?: int, modifiedAt?: string, children?: array}[]
     */
    private function listDeletedRecursive(string $fullPath, string $baseFull, string $baseDir): array
    {
        $result = [];
        $items  = scandir($fullPath) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '~') || $item === 'desktop.ini') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;
            $relPath  = substr($itemPath, strlen($baseFull) + 1);
            $relPath  = str_replace('\\', '/', $relPath);

            if ($baseDir !== '') {
                $relPath = $baseDir . ($relPath !== '' ? '/' . $relPath : '');
            }

            if (is_dir($itemPath)) {
                $result[] = [
                    'path'     => $relPath,
                    'name'     => $item,
                    'type'     => 'directory',
                    'children' => $this->listDeletedRecursive($itemPath, $baseFull, $baseDir),
                ];
            } else {
                $ext = strtolower(pathinfo($itemPath, PATHINFO_EXTENSION));
                if (! in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'url'], true)) {
                    continue;
                }

                $entry = [
                    'path'       => $relPath,
                    'name'       => $item,
                    'type'       => 'file',
                    'size'       => filesize($itemPath),
                    'createdAt'  => date('Y-m-d H:i:s', filectime($itemPath)),
                    'modifiedAt' => date('Y-m-d H:i:s', filemtime($itemPath)),
                ];

                if ($ext === 'docx' || $ext === 'xlsx') {
                    $entry['metadata'] = $this->readDocxMetadata($itemPath);
                } elseif ($ext === 'url') {
                    $entry['metadata'] = [
                        'core'   => [],
                        'custom' => [
                            'mimeType' => 'application/internet-shortcut',
                            'linkUrl'  => $this->readInternetShortcutUrl($itemPath),
                        ],
                    ];
                }

                $result[] = $entry;
            }
        }
        return $result;
    }

    /**
     * Atkuria failÄ… iÅ¡ /deleted/{baseDir}/{path} atgal Ä¯ {baseDir}/{path}.
     *
     * @return 'SUCCESS'|'FAIL'
     */
    public function restore(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return self::FAIL;
        }
    
        [$baseDir, $relativePath] = array_pad(explode('/', $path, 2), 2, null);
    
        if (
            $baseDir === null ||
            $relativePath === null ||
            !in_array($baseDir, ['templates', 'generated', 'archive'], true)
        ) {
            return self::FAIL;
        }
    
        $deletedBase = realpath($this->projectDir . '/deleted/' . $baseDir);
        $resolved = $deletedBase ? realpath($deletedBase . '/' . $relativePath) : false;
    
        if (
            $deletedBase === false ||
            $resolved === false ||
            !is_file($resolved) ||
            !str_starts_with($resolved, $deletedBase)
        ) {
            return self::FAIL;
        }
    
        $targetFull = $this->getBaseFullPath($baseDir);
        if ($targetFull === null) {
            return self::FAIL;
        }
    
        $targetPath = $targetFull . '/' . $relativePath;
        $targetDir = dirname($targetPath);
    
        if ((!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) || file_exists($targetPath)) {
            return self::FAIL;
        }
    
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'url'], true)) {
            return self::FAIL;
        }
    
        return rename($resolved, $targetPath) ? self::SUCCESS : self::FAIL;
    }

    /**
     * @return string|null Pilnas kelias Ä¯ baseDir arba null jei neegzistuoja
     */
    public function getBaseFullPath(string $baseDir): ?string
    {
        $baseDir = trim(str_replace('\\', '/', $baseDir), '/');
        $mapped  = match ($baseDir) {
            'templates' => 'templates',
            'generated' => 'generated',
            'archive' => 'archive',
            'deleted'   => 'deleted',
            default     => null,
        };
        if ($mapped === null) {
            return null;
        }
        $fullPath = $this->projectDir . '/' . $mapped;
        if (! is_dir($fullPath) && ! @mkdir($fullPath, 0775, true) && ! is_dir($fullPath)) {
            return null;
        }
        $resolved = realpath($fullPath);
        return ($resolved !== false && is_dir($resolved)) ? $resolved : null;
    }

    private function isAllowedExtension(string $pathOrName, array $allowedExtensions): bool
    {
        $ext = strtolower(pathinfo($pathOrName, PATHINFO_EXTENSION));
        return in_array($ext, $allowedExtensions, true);
    }

    private function sanitizeShortcutFileName(string $displayName): string
    {
        $name = trim(str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|', "\0"], '_', $displayName));
        if ($name === '') {
            $name = 'nuoroda';
        }
        if (! str_ends_with(strtolower($name), '.url')) {
            $name .= '.url';
        }

        return $name;
    }

    /**
     * @return array{core: array, custom: array<string, string>}
     */
    private function readUrlShortcutMetadata(string $path): array
    {
        $url = $this->parseInternetShortcutUrl($path);

        return [
            'core'   => [],
            'custom' => [
                'linkUrl'  => $url,
                'mimeType' => 'application/internet-shortcut',
            ],
        ];
    }

    private function parseInternetShortcutUrl(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return '';
        }
        $parsed = @parse_ini_string($content, true, INI_SCANNER_RAW);
        if (! is_array($parsed)) {
            return '';
        }
        if (isset($parsed['InternetShortcut']['URL'])) {
            return (string) $parsed['InternetShortcut']['URL'];
        }
        if (isset($parsed['URL'])) {
            return (string) $parsed['URL'];
        }

        return '';
    }

    private function readDocxMetadata(string $docxPath): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($docxPath) !== true) {
            return [];
        }

        $metadata = [
            'core'   => [],
            'custom' => [],
        ];

        $coreXml = $zip->getFromName('docProps/core.xml');
        if ($coreXml !== false) {
            $metadata['core'] = $this->parseCoreMetadata($coreXml);
        }

        $customXml = $zip->getFromName('docProps/custom.xml');
        if ($customXml !== false) {
            $metadata['custom'] = $this->parseCustomMetadata($customXml);
        }

        $zip->close();

        return $metadata;
    }

    private function parseCoreMetadata(string $xml): array
    {
        $doc = new \DOMDocument();

        if (! @$doc->loadXML($xml)) {
            return [];
        }

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('cp', 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $xpath->registerNamespace('dcterms', 'http://purl.org/dc/terms/');

        return [
            'title'          => $this->firstNodeValue($xpath, '/cp:coreProperties/dc:title'),
            'subject'        => $this->firstNodeValue($xpath, '/cp:coreProperties/dc:subject'),
            'creator'        => $this->firstNodeValue($xpath, '/cp:coreProperties/dc:creator'),
            'description'    => $this->firstNodeValue($xpath, '/cp:coreProperties/dc:description'),
            'lastModifiedBy' => $this->firstNodeValue($xpath, '/cp:coreProperties/cp:lastModifiedBy'),
            'revision'       => $this->firstNodeValue($xpath, '/cp:coreProperties/cp:revision'),
            'created'        => $this->firstNodeValue($xpath, '/cp:coreProperties/dcterms:created'),
            'modified'       => $this->firstNodeValue($xpath, '/cp:coreProperties/dcterms:modified'),
        ];
    }

    private function parseCustomMetadata(string $xml): array
    {
        $doc = new \DOMDocument();

        if (! @$doc->loadXML($xml)) {
            return [];
        }

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('cp', 'http://schemas.openxmlformats.org/officeDocument/2006/custom-properties');

        $result = [];

        foreach ($xpath->query('/cp:Properties/cp:property') as $property) {
            if (! $property instanceof \DOMElement) {
                continue;
            }

            $name = $property->getAttribute('name');
            if ($name === '') {
                continue;
            }

            $value = null;
            foreach ($property->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $value = $child->textContent;
                    break;
                }
            }

            $result[$name] = $value ?? '';
        }

        return $result;
    }

    private function firstNodeValue(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $value = trim($nodes->item(0)?->textContent ?? '');

        return $value !== '' ? $value : null;
    }

    private function readInternetShortcutUrl(string $path): string
    {
        $raw = @file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            return '';
        }

        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if (! str_starts_with(strtoupper($line), 'URL=')) {
                continue;
            }

            return trim(substr($line, 4));
        }

        return '';
    }
}

