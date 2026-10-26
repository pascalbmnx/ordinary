<?php
/**
 * Secure file upload service.
 * Validates extension, MIME type, size, filename. Stores outside public web root.
 */
final class Upload
{
    private array $config;

    public function __construct()
    {
        $this->config = Database::config('uploads');
    }

    /**
     * Process a single uploaded file.
     *
     * @return array  ['stored_name','original_name','path','mime','size']
     * @throws Exception on validation failure
     */
    public function process(array $file, string $subDir, array $extraExts = []): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new Exception('No file was uploaded.');
        }
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->uploadErrorMessage((int) $file['error']));
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new Exception('Invalid upload.');
        }

        $originalName = $file['name'] ?? '';
        $size = (int) ($file['size'] ?? 0);

        // Extension (never trust browser — derive from a safe list after scanning)
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExts = array_merge($this->config['allowed_extensions'] ?? [], $extraExts);
        if (!in_array($ext, $allowedExts, true)) {
            throw new Exception('File type not allowed.');
        }

        // Size
        $maxSize = $this->maxBytes();
        if ($size > $maxSize) {
            throw new Exception('File exceeds the maximum allowed size.');
        }
        if ($size <= 0) {
            throw new Exception('File is empty.');
        }

        // MIME from actual content
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = $this->config['allowed_mimes'] ?? [];
        if (!in_array($mime, $allowedMimes, true)) {
            throw new Exception('File content (MIME) is not allowed.');
        }

        // Block executable / PHP content regardless of extension
        $this->assertSafe($file['tmp_name'], $mime, $ext);

        // Sanitize filename
        $safeName = $this->sanitizeName($originalName);

        // Sanitize subDir to prevent path traversal
        $subDir = preg_replace('/[^a-zA-Z0-9_\-]/', '', $subDir);

        $storageBase = Database::config('storage');
        $targetDir = $storageBase['path'] . $storageBase[$subDir] ?? $storageBase['documents'];
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $targetPath = $targetDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to store the uploaded file.');
        }

        return [
            'stored_name' => $storedName,
            'original_name' => $safeName,
            'path' => $targetPath,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    private function maxBytes(): int
    {
        // Allow override from system_settings
        try {
            $settings = new SystemSetting();
            $mb = (int) $settings->get('max_upload_size_mb', 10);
            if ($mb <= 0) $mb = 10;
            return $mb * 1024 * 1024;
        } catch (Throwable $e) {
            return $this->config['max_size'] ?? 10485760;
        }
    }

    private function assertSafe(string $tmpPath, string $mime, string $ext): void
    {
        $unsafe = ['php', 'php3', 'php4', 'php5', 'phtml', 'cgi', 'sh', 'exe', 'bat', 'cmd', 'pl', 'py'];
        if (in_array($ext, $unsafe, true)) {
            throw new Exception('File type is not allowed.');
        }
        $head = @file_get_contents($tmpPath, false, null, 0, 256);
        if ($head !== false && preg_match('/<\?php|\x00(?:ELF|MZ)/i', $head)) {
            throw new Exception('File content is not allowed.');
        }
        if (strpos($mime, 'php') !== false) {
            throw new Exception('File content is not allowed.');
        }
    }

    private function sanitizeName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\w.\- ]+/u', '_', $name);
        return substr($name, 0, 200);
    }

    private function uploadErrorMessage(int $error): string
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File is too large.';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Upload directory is missing.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write the file to disk.';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the upload.';
            default:
                return 'Upload failed.';
        }
    }
}