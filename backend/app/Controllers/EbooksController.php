<?php

namespace App\Controllers;

use App\Config\Database;

/**
 * EbooksController
 * CRUD, upload, cover extraction/fetch, and reading-progress tracking for e-books
 */
class EbooksController extends BaseController
{
    // Allowed MIME types mapped to format
    private const ALLOWED_MIME = [
        'application/pdf'                                                    => 'pdf',
        'application/epub+zip'                                               => 'epub',
        'application/x-mobipocket-ebook'                                     => 'mobi',
        'application/vnd.amazon.ebook'                                       => 'mobi',
        'application/octet-stream'                                           => null, // checked by extension below
    ];

    private const ALLOWED_EXTENSIONS = ['pdf', 'epub', 'mobi'];

    // Max upload size: 100 MB
    private const MAX_FILE_SIZE = 104857600;

    // Storage base path (mounted volume)
    private const STORAGE_BASE = '/var/www/html/storage/ebooks';

    // =========================================================
    // GET /api/ebooks
    // =========================================================
    public function index(array $params): void
    {
        $this->requirePluginEnabled();

        $q       = $this->getQueryParams();
        $db      = Database::getConnection();
        $authUser = $this->getAuthUser();
        $userId  = $authUser['id'] ?? null;

        $page    = max(1, (int)($q['page'] ?? 1));
        $perPage = min(100, max(1, (int)($q['per_page'] ?? 24)));
        $offset  = ($page - 1) * $perPage;

        $conditions = [];
        $bindings   = [];

        if (!empty($q['search'])) {
            $conditions[] = "(e.title ILIKE :search OR e.author ILIKE :search)";
            $bindings['search'] = '%' . $q['search'] . '%';
        }
        if (!empty($q['format'])) {
            $conditions[] = "e.file_format = :format";
            $bindings['format'] = $q['format'];
        }
        if (isset($q['metadata_complete'])) {
            $conditions[] = "e.metadata_complete = :mc";
            $bindings['mc'] = ($q['metadata_complete'] === 'true' || $q['metadata_complete'] === '1');
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Count
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM ebooks e {$where}");
        $stmt->execute($bindings);
        $total = (int)$stmt->fetch()['total'];

        // Fetch — JOIN per-user progress
        $bindings['uid'] = $userId;
        $stmt = $db->prepare("
            SELECT e.*,
                   b.title  AS linked_book_title,
                   p.name   AS publisher_name,
                   COALESCE(erp.current_page,    0)    AS current_page,
                   COALESCE(erp.read_percentage, 0.00) AS read_percentage
            FROM ebooks e
            LEFT JOIN books                  b   ON b.id   = e.book_id
            LEFT JOIN publishers             p   ON p.id   = e.publisher_id
            LEFT JOIN ebook_reading_progress erp ON erp.ebook_id = e.id
                                                AND erp.user_id  = :uid
            {$where}
            ORDER BY e.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($bindings as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit',  $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $ebooks = $stmt->fetchAll();

        $this->json([
            'data' => $ebooks,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => max(1, (int)ceil($total / $perPage)),
            ]
        ]);
    }

    // =========================================================
    // GET /api/ebooks/{id}
    // =========================================================
    public function show(array $params): void
    {
        $this->requirePluginEnabled();

        $authUser = $this->getAuthUser();
        $userId   = $authUser['id'] ?? null;
        $db       = Database::getConnection();

        $stmt = $db->prepare("
            SELECT e.*,
                   b.title  AS linked_book_title,
                   p.name   AS publisher_name,
                   COALESCE(erp.current_page,    0)    AS current_page,
                   COALESCE(erp.read_percentage, 0.00) AS read_percentage
            FROM ebooks e
            LEFT JOIN books                  b   ON b.id   = e.book_id
            LEFT JOIN publishers             p   ON p.id   = e.publisher_id
            LEFT JOIN ebook_reading_progress erp ON erp.ebook_id = e.id
                                                AND erp.user_id  = :uid
            WHERE e.id = :id
        ");
        $stmt->execute(['id' => $params['id'], 'uid' => $userId]);
        $ebook = $stmt->fetch();

        if (!$ebook) {
            $this->json(['error' => 'E-book not found.'], 404);
            return;
        }

        $this->json(['data' => $ebook]);
    }

    // =========================================================
    // POST /api/ebooks/upload
    // Accepts multipart/form-data with fields:
    //   file        (required)  — the ebook file
    //   title       (optional)  — overrides auto-detected
    //   author      (optional)  — overrides auto-detected
    //   book_id     (optional)  — link to existing book record
    //   total_pages (optional)  — total pages / chapter count
    // =========================================================
    public function upload(array $params): void
    {
        $this->requirePluginEnabled();

        if (empty($_FILES['file'])) {
            $this->json(['error' => 'No file uploaded.'], 422);
            return;
        }

        $file = $_FILES['file'];

        // Check upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Upload error code: ' . $file['error']], 422);
            return;
        }

        // Size check
        if ($file['size'] > self::MAX_FILE_SIZE) {
            $this->json(['error' => 'File too large. Maximum size is 100 MB.'], 422);
            return;
        }

        // Extension check
        $originalName = basename($file['name']);
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            $this->json(['error' => 'Unsupported format. Allowed: pdf, epub, mobi.'], 422);
            return;
        }

        // MIME check
        $mime   = mime_content_type($file['tmp_name']);
        $format = self::ALLOWED_MIME[$mime] ?? null;

        // Fallback for octet-stream or missing MIME
        if (!$format) {
            if (in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                $format = $ext;
            } else {
                $this->json(['error' => 'Could not verify file type.'], 422);
                return;
            }
        }

        // Ensure storage directories exist (volume mount may have wiped build-time mkdir)
        $storageDir = self::STORAGE_BASE . '/files';
        $coversDir  = self::STORAGE_BASE . '/covers';
        foreach ([$storageDir, $coversDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                $this->json(['error' => 'Storage directory could not be created.'], 500);
                return;
            }
        }

        // Generate unique filename
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $uniqueName = bin2hex(random_bytes(16)) . '_' . $safeName;
        $destPath   = $storageDir . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->json(['error' => 'Failed to store uploaded file.'], 500);
            return;
        }

        // Post data
        $post       = $_POST;
        $bookId      = !empty($post['book_id'])      ? $this->sanitize($post['book_id'])      : null;
        $publisherId = !empty($post['publisher_id']) ? $this->sanitize($post['publisher_id']) : null;
        $totalPages  = !empty($post['total_pages'])  ? (int)$post['total_pages'] : 0;

        // Auto-extract metadata
        $metadata = $this->extractMetadata($destPath, $format);

        // User-supplied overrides have priority
        $title  = !empty($post['title'])  ? $this->sanitize($post['title'])  : ($metadata['title']  ?? '');
        $author = !empty($post['author']) ? $this->sanitize($post['author']) : ($metadata['author'] ?? '');

        // If still no title, use filename without extension
        if ($title === '') {
            $title = pathinfo($originalName, PATHINFO_FILENAME);
        }

        $metadataComplete = ($title !== '' && $author !== '');

        // Cover extraction
        [$coverPath, $coverSource] = $this->resolveCover($destPath, $format, $title, $author);

        $authUser = $this->getAuthUser();

        $db   = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO ebooks
                (book_id, publisher_id, title, author, file_name, file_path, file_size, file_format,
                 cover_source, cover_path, total_pages, location_type, metadata_complete, owner_id)
            VALUES
                (:book_id, :publisher_id, :title, :author, :file_name, :file_path, :file_size, :file_format,
                 :cover_source, :cover_path, :total_pages, 'local', :metadata_complete, :owner_id)
            RETURNING *
        ");
        $stmt->execute([
            'book_id'           => $bookId,
            'publisher_id'      => $publisherId,
            'title'             => $title,
            'author'            => $author,
            'file_name'         => $originalName,
            'file_path'         => $destPath,
            'file_size'         => $file['size'],
            'file_format'       => $format,
            'cover_source'      => $coverSource,
            'cover_path'        => $coverPath,
            'total_pages'       => $totalPages,
            'metadata_complete' => $metadataComplete ? 't' : 'f',
            'owner_id'          => $authUser['id'] ?? null,
        ]);

        $ebook = $stmt->fetch();
        $this->logAction('upload', 'ebook', $ebook['id'], $authUser['id'] ?? null);

        $this->json([
            'message'           => 'E-book uploaded successfully.',
            'data'              => $ebook,
            'metadata_complete' => $metadataComplete,
        ], 201);
    }

    // =========================================================
    // PUT /api/ebooks/{id}
    // Update title, author, total_pages, book_id
    // =========================================================
    public function update(array $params): void
    {
        $this->requirePluginEnabled();

        $data = $this->getRequestBody();
        $db   = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM ebooks WHERE id = :id");
        $stmt->execute(['id' => $params['id']]);
        $ebook = $stmt->fetch();

        if (!$ebook) {
            $this->json(['error' => 'E-book not found.'], 404);
            return;
        }

        $fields   = ['title', 'author', 'total_pages', 'book_id', 'publisher_id', 'notes'];
        $updates  = [];
        $bindings = ['id' => $params['id']];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = :{$field}";
                $bindings[$field] = match ($field) {
                    'total_pages' => !empty($data[$field]) ? (int)$data[$field] : 0,
                    'publisher_id' => !empty($data[$field]) ? $data[$field] : null,
                    default       => $this->sanitize((string)($data[$field] ?? '')),
                };
            }
        }

        // Recompute metadata_complete if title/author changed
        $newTitle  = $data['title']  ?? $ebook['title'];
        $newAuthor = $data['author'] ?? $ebook['author'];
        $updates[] = "metadata_complete = :metadata_complete";
        $bindings['metadata_complete'] = ($newTitle !== '' && $newAuthor !== '') ? 't' : 'f';

        $updates[] = "updated_at = NOW()";
        $sql = "UPDATE ebooks SET " . implode(', ', $updates) . " WHERE id = :id RETURNING *";

        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);
        $updated = $stmt->fetch();

        $authUser = $this->getAuthUser();
        $this->logAction('update', 'ebook', $params['id'], $authUser['id'] ?? null);

        $this->json(['message' => 'E-book updated successfully.', 'data' => $updated]);
    }

    // =========================================================
    // DELETE /api/ebooks/{id}
    // =========================================================
    public function destroy(array $params): void
    {
        $this->requirePluginEnabled();

        $db   = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM ebooks WHERE id = :id");
        $stmt->execute(['id' => $params['id']]);
        $ebook = $stmt->fetch();

        if (!$ebook) {
            $this->json(['error' => 'E-book not found.'], 404);
            return;
        }

        // Delete physical files
        if (!empty($ebook['file_path']) && file_exists($ebook['file_path'])) {
            @unlink($ebook['file_path']);
        }
        if (!empty($ebook['cover_path']) && $ebook['cover_source'] !== 'default' && file_exists($ebook['cover_path'])) {
            @unlink($ebook['cover_path']);
        }

        $stmt = $db->prepare("DELETE FROM ebooks WHERE id = :id");
        $stmt->execute(['id' => $params['id']]);

        $authUser = $this->getAuthUser();
        $this->logAction('delete', 'ebook', $params['id'], $authUser['id'] ?? null);

        $this->json(['message' => 'E-book deleted successfully.']);
    }

    // =========================================================
    // POST /api/ebooks/{id}/progress
    // Body: { "current_page": 42 }
    // =========================================================
    public function updateProgress(array $params): void
    {
        $this->requirePluginEnabled();

        $data     = $this->getRequestBody();
        $authUser = $this->getAuthUser();
        $userId   = $authUser['id'] ?? null;

        if (!isset($data['current_page'])) {
            $this->json(['error' => 'current_page is required.'], 422);
            return;
        }

        if (!$userId) {
            $this->json(['error' => 'Authentication required.'], 401);
            return;
        }

        $db   = Database::getConnection();
        $stmt = $db->prepare('SELECT id, total_pages FROM ebooks WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);
        $ebook = $stmt->fetch();

        if (!$ebook) {
            $this->json(['error' => 'E-book not found.'], 404);
            return;
        }

        $currentPage = max(0, (int)$data['current_page']);
        $totalPages  = max(1, (int)($ebook['total_pages'] ?: 1));
        $currentPage = min($currentPage, $totalPages);
        $percentage  = round(($currentPage / $totalPages) * 100, 2);

        // UPSERT per-user progress
        $stmt = $db->prepare("
            INSERT INTO ebook_reading_progress
                (user_id, ebook_id, current_page, read_percentage, last_read_at)
            VALUES
                (:user_id, :ebook_id, :current_page, :percentage, NOW())
            ON CONFLICT (user_id, ebook_id)
            DO UPDATE SET
                current_page    = EXCLUDED.current_page,
                read_percentage = EXCLUDED.read_percentage,
                last_read_at    = NOW()
            RETURNING ebook_id AS id, current_page, read_percentage
        ");
        $stmt->execute([
            'user_id'      => $userId,
            'ebook_id'     => $params['id'],
            'current_page' => $currentPage,
            'percentage'   => $percentage,
        ]);
        $updated = $stmt->fetch();
        $updated['total_pages'] = (int)$ebook['total_pages'];

        $this->json(['message' => 'Progress updated.', 'data' => $updated]);
    }

    // =========================================================
    // GET /api/ebooks/{id}/open
    // Returns the file download/open URL info
    // =========================================================
    public function open(array $params): void
    {
        $this->requirePluginEnabled();

        $db   = Database::getConnection();
        $stmt = $db->prepare("SELECT id, title, file_name, file_path, file_format FROM ebooks WHERE id = :id");
        $stmt->execute(['id' => $params['id']]);
        $ebook = $stmt->fetch();

        if (!$ebook) {
            $this->json(['error' => 'E-book not found.'], 404);
            return;
        }

        if (!file_exists($ebook['file_path'])) {
            $this->json(['error' => 'File not found on server.'], 404);
            return;
        }

        // Stream the file with appropriate Content-Type
        $mimeMap = [
            'pdf'  => 'application/pdf',
            'epub' => 'application/epub+zip',
            'mobi' => 'application/x-mobipocket-ebook',
        ];
        $mime = $mimeMap[$ebook['file_format']] ?? 'application/octet-stream';

        // PDFs open inline (browser PDF viewer); others force download so OS opens with default app
        $disposition = ($ebook['file_format'] === 'pdf') ? 'inline' : 'attachment';

        // Guard against path traversal: ensure file is within the ebooks storage directory
        $realPath    = realpath($ebook['file_path']);
        $storageReal = realpath(self::STORAGE_BASE);
        if ($realPath === false || $storageReal === false || strncmp($realPath, $storageReal, strlen($storageReal)) !== 0) {
            $this->json(['error' => 'File access denied.'], 403);
            return;
        }

        // Clear any buffered output before streaming
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($ebook['file_name']) . '"');
        header('Content-Length: ' . filesize($ebook['file_path']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-cache');
        // Note: CORS is handled globally in index.php — no wildcard override here
        readfile($ebook['file_path']);
        exit;
    }

    // =========================================================
    // GET /api/ebooks/{id}/cover
    // Serves the cover image
    // =========================================================
    public function cover(array $params): void
    {
        $this->requirePluginEnabled();

        $db   = Database::getConnection();
        $stmt = $db->prepare("SELECT cover_path, cover_source FROM ebooks WHERE id = :id");
        $stmt->execute(['id' => $params['id']]);
        $ebook = $stmt->fetch();

        if (!$ebook) {
            $this->json(['error' => 'E-book not found.'], 404);
            return;
        }

        if (empty($ebook['cover_path']) || !file_exists($ebook['cover_path'])) {
            // Return a redirect/placeholder indicator
            $this->json(['cover_url' => null, 'source' => 'default']);
            return;
        }

        // Guard against path traversal
        $realPath    = realpath($ebook['cover_path']);
        $storageReal = realpath(self::STORAGE_BASE);
        if ($realPath === false || $storageReal === false || strncmp($realPath, $storageReal, strlen($storageReal)) !== 0) {
            $this->json(['error' => 'File access denied.'], 403);
            return;
        }

        $ext  = strtolower(pathinfo($ebook['cover_path'], PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($ebook['cover_path']);
        exit;
    }

    // =========================================================
    // POST /api/ebooks/{id}/cover
    // Upload a custom cover image (multipart: field "cover")
    // =========================================================
    public function uploadCover(array $params): void
    {
        $this->requirePluginEnabled();

        $db   = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM ebooks WHERE id = :id");
        $stmt->execute(['id' => $params['id']]);
        $ebook = $stmt->fetch();

        if (!$ebook) {
            $this->json(['error' => 'E-book not found.'], 404);
            return;
        }

        if (empty($_FILES['cover'])) {
            $this->json(['error' => 'No cover image uploaded.'], 422);
            return;
        }

        $file = $_FILES['cover'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Upload error: ' . $file['error']], 422);
            return;
        }

        // Max 5 MB for a cover image
        if ($file['size'] > 5242880) {
            $this->json(['error' => 'Cover image too large. Maximum 5 MB.'], 422);
            return;
        }

        // Validate it is actually an image
        $mime = mime_content_type($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowedMimes, true)) {
            $this->json(['error' => 'Invalid image type. Allowed: JPEG, PNG, WEBP.'], 422);
            return;
        }

        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'jpg'];
        $ext    = $extMap[$mime] ?? 'jpg';

        $coversDir = self::STORAGE_BASE . '/covers';
        if (!is_dir($coversDir)) {
            mkdir($coversDir, 0750, true);
        }

        $coverPath = $coversDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $coverPath)) {
            $this->json(['error' => 'Failed to save cover image.'], 500);
            return;
        }

        // Resize to a reasonable cover size using GD (always available)
        // resizeCoverImage returns the final path (may rename to .jpg)
        $coverPath = $this->resizeCoverImage($coverPath, $mime);

        // Delete old cover file if it was not default
        if (!empty($ebook['cover_path']) && $ebook['cover_source'] !== 'default' && file_exists($ebook['cover_path'])) {
            @unlink($ebook['cover_path']);
        }

        $stmt = $db->prepare("
            UPDATE ebooks
            SET cover_path = :cover_path, cover_source = 'custom', updated_at = NOW()
            WHERE id = :id
            RETURNING *
        ");
        $stmt->execute(['cover_path' => $coverPath, 'id' => $params['id']]);
        $updated = $stmt->fetch();

        $authUser = $this->getAuthUser();
        $this->logAction('update_cover', 'ebook', $params['id'], $authUser['id'] ?? null);

        $this->json(['message' => 'Cover updated successfully.', 'data' => $updated]);
    }

    // =========================================================
    // POST /api/ebooks/{id}/reextract-cover
    // Re-extract cover from the first page of the file
    // =========================================================
    public function reextractCover(array $params): void
    {
        $this->requirePluginEnabled();

        $db   = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM ebooks WHERE id = :id");
        $stmt->execute(['id' => $params['id']]);
        $ebook = $stmt->fetch();

        if (!$ebook) {
            $this->json(['error' => 'E-book not found.'], 404);
            return;
        }

        if (!file_exists($ebook['file_path'])) {
            $this->json(['error' => 'E-book file not found on server.'], 404);
            return;
        }

        // Delete old cover
        if (!empty($ebook['cover_path']) && $ebook['cover_source'] !== 'default' && file_exists($ebook['cover_path'])) {
            @unlink($ebook['cover_path']);
        }

        $coverPath = $this->extractCoverFromFile($ebook['file_path'], $ebook['file_format']);
        $source    = $coverPath ? 'extracted' : 'default';

        $stmt = $db->prepare("
            UPDATE ebooks
            SET cover_path = :cover_path, cover_source = :cover_source, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['cover_path' => $coverPath, 'cover_source' => $source, 'id' => $params['id']]);

        $this->json([
            'message'      => $coverPath ? 'Cover re-extracted from file.' : 'Could not extract cover from file.',
            'cover_source' => $source,
            'found'        => $coverPath !== null,
        ]);
    }

    // =========================================================
    // POST /api/ebooks/{id}/refresh-cover
    // Attempt to re-fetch the cover from the internet
    // =========================================================
    public function refreshCover(array $params): void
    {
        $this->requirePluginEnabled();

        $db   = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM ebooks WHERE id = :id");
        $stmt->execute(['id' => $params['id']]);
        $ebook = $stmt->fetch();

        if (!$ebook) {
            $this->json(['error' => 'E-book not found.'], 404);
            return;
        }

        // Allow frontend to pass custom search terms (e.g. from the Change Cover modal)
        $body   = $this->getRequestBody();
        $title  = !empty($body['title'])  ? $this->sanitize($body['title'])  : $ebook['title'];
        $author = !empty($body['author']) ? $this->sanitize($body['author']) : ($ebook['author'] ?? '');

        // Delete old fetched/extracted cover before replacing
        if (!empty($ebook['cover_path']) && in_array($ebook['cover_source'], ['fetched'], true) && file_exists($ebook['cover_path'])) {
            @unlink($ebook['cover_path']);
        }

        [$coverPath, $coverSource] = $this->fetchCoverOnline($title, $author);

        $stmt = $db->prepare("
            UPDATE ebooks SET cover_path = :cover_path, cover_source = :cover_source, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'cover_path'   => $coverPath,
            'cover_source' => $coverSource,
            'id'           => $params['id'],
        ]);

        $this->json([
            'message'      => 'Cover refresh attempted.',
            'cover_source' => $coverSource,
            'found'        => $coverSource === 'fetched',
        ]);
    }

    // =========================================================
    // Private helpers
    // =========================================================

    /**
     * Guard — abort with 403 if plugin is disabled
     */
    private function requirePluginEnabled(): void
    {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT setting_value FROM plugin_settings
            WHERE plugin_name = 'ebooks' AND setting_key = 'enabled'
        ");
        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row || $row['setting_value'] !== 'true') {
            $this->json(['error' => 'The E-book plugin is not enabled.'], 403);
            exit;
        }
    }

    /**
     * Extract title/author from file metadata
     */
    private function extractMetadata(string $filePath, string $format): array
    {
        $metadata = ['title' => '', 'author' => ''];

        try {
            if ($format === 'pdf') {
                // Try to read PDF info using pdfinfo if available
                $escaped = escapeshellarg($filePath);
                $output  = shell_exec("/usr/bin/pdfinfo {$escaped} 2>/dev/null");
                if ($output) {
                    if (preg_match('/^Title:\s+(.+)$/mi', $output, $m)) {
                        $metadata['title'] = trim($m[1]);
                    }
                    if (preg_match('/^Author:\s+(.+)$/mi', $output, $m)) {
                        $metadata['author'] = trim($m[1]);
                    }
                }
            } elseif ($format === 'epub') {
                // EPUB is a ZIP — read container.xml → content.opf
                $zip = new \ZipArchive();
                if ($zip->open($filePath) === true) {
                    // Find OPF path from container.xml
                    $container = $zip->getFromName('META-INF/container.xml');
                    if ($container && preg_match('/full-path="([^"]+\.opf)"/i', $container, $m)) {
                        $opf = $zip->getFromName($m[1]);
                        if ($opf) {
                            if (preg_match('/<dc:title[^>]*>([^<]+)<\/dc:title>/i', $opf, $tm)) {
                                $metadata['title'] = trim(html_entity_decode($tm[1]));
                            }
                            if (preg_match('/<dc:creator[^>]*>([^<]+)<\/dc:creator>/i', $opf, $am)) {
                                $metadata['author'] = trim(html_entity_decode($am[1]));
                            }
                        }
                    }
                    $zip->close();
                }
            }
            // MOBI: basic metadata extraction (header magic bytes)
            elseif ($format === 'mobi') {
                $fh = fopen($filePath, 'rb');
                if ($fh) {
                    // PalmDOC header: title is bytes 0–31
                    $header = fread($fh, 32);
                    $rawTitle = rtrim($header, "\x00");
                    if ($rawTitle && mb_detect_encoding($rawTitle, 'UTF-8', true)) {
                        $metadata['title'] = $rawTitle;
                    }
                    fclose($fh);
                }
            }
        } catch (\Exception $e) {
            // Metadata extraction is best-effort; don't fail the upload
        }

        return $metadata;
    }

    /**
     * Resolve cover: ONLY extract from the file's first page.
     * Online fetch is intentionally removed from the upload flow —
     * the user can trigger it manually from the detail page.
     */
    private function resolveCover(string $filePath, string $format, string $title, string $author): array
    {
        $extracted = $this->extractCoverFromFile($filePath, $format);
        if ($extracted) {
            return [$extracted, 'extracted'];
        }
        // Could not extract — default placeholder, user can change later
        return [null, 'default'];
    }

    /**
     * Extract cover image from the first page of a PDF or the cover of an EPUB.
     *
     * PDF strategy (in order):
     *   1. pdftoppm  (poppler-utils) — most reliable, no security-policy issues
     *   2. Ghostscript (gs)          — fallback if pdftoppm unavailable
     *
     * EPUB strategy:
     *   1. Parse OPF manifest for the cover item
     *   2. Fallback: scan common cover filenames inside the ZIP
     */
    private function extractCoverFromFile(string $filePath, string $format): ?string
    {
        $coversDir = self::STORAGE_BASE . '/covers';
        if (!is_dir($coversDir) && !mkdir($coversDir, 0775, true) && !is_dir($coversDir)) {
            return null;
        }

        try {
            // ── PDF ──────────────────────────────────────────────
            if ($format === 'pdf') {
                $prefix  = $coversDir . '/' . bin2hex(random_bytes(16));
                $escaped = escapeshellarg($filePath);
                $prefixE = escapeshellarg($prefix);

                // 1. Try pdftoppm (poppler-utils) — full path avoids PATH issues
                exec("/usr/bin/pdftoppm -jpeg -r 150 -f 1 -l 1 {$escaped} {$prefixE} 2>/dev/null", $out, $code);

                // pdftoppm appends a zero-padded page number: prefix-000001.jpg
                // Some older versions use prefix-1.jpg
                foreach (['{prefix}-000001.jpg', '{prefix}-1.jpg'] as $pattern) {
                    $candidate = str_replace('{prefix}', $prefix, $pattern);
                    if (file_exists($candidate) && filesize($candidate) > 500) {
                        return $candidate;
                    }
                }

                // 2. Fallback: Ghostscript
                $gsOut = $prefix . '_gs.jpg';
                $gsOutE = escapeshellarg($gsOut);
                exec(
                    "/usr/bin/gs -dNOPAUSE -dBATCH -dSAFER "
                    . "-dFirstPage=1 -dLastPage=1 "
                    . "-sDEVICE=jpeg -r150 -dJPEGQ=85 "
                    . "-sOutputFile={$gsOutE} {$escaped} 2>/dev/null",
                    $out2, $code2
                );
                if ($code2 === 0 && file_exists($gsOut) && filesize($gsOut) > 500) {
                    return $gsOut;
                }

                return null;
            }

            // ── EPUB ─────────────────────────────────────────────
            if ($format === 'epub') {
                $zip = new \ZipArchive();
                if ($zip->open($filePath) !== true) {
                    return null;
                }

                $coverPath = null;

                // Strategy 1: parse OPF manifest
                $container = $zip->getFromName('META-INF/container.xml');
                if ($container) {
                    preg_match('/full-path=["\']([^"\']+\.opf)["\']/i', $container, $opfMatch);
                    if (!empty($opfMatch[1])) {
                        $opfZipPath = $opfMatch[1];
                        $opfDir     = dirname($opfZipPath);
                        $opf        = $zip->getFromName($opfZipPath);

                        if ($opf) {
                            $imgRelPath = null;

                            // Try: <meta name="cover" content="cover-image"/>
                            // then find <item id="cover-image" href="..."/>
                            if (preg_match('/<meta[^>]+name=["\']cover["\'\s][^>]+content=["\'](\S+?)["\']/i', $opf, $metaM)) {
                                $covItemId = $metaM[1];
                                // find item by id (attribute order varies)
                                if (preg_match('/<item\b[^>]+\bid=["\'](?' . '=.*?)' . preg_quote($covItemId, '/') . '["\'][^>]+>/i', $opf, $itemM)
                                    || preg_match('/<item\b[^>]*\sid=["\']' . preg_quote($covItemId, '/') . '["\'][^>]*>/i', $opf, $itemM)) {
                                    preg_match('/\bhref=["\']([^"\']+)["\']/i', $itemM[0], $hrefM);
                                    $imgRelPath = $hrefM[1] ?? null;
                                }
                            }

                            // Try: <item id="cover" .../> or <item id="cover-image" .../>
                            if (!$imgRelPath) {
                                if (preg_match('/<item\b[^>]+\bid=["\']cover(?:-image)?["\'][^>]*>/i', $opf, $itemM)) {
                                    preg_match('/\bhref=["\']([^"\']+)["\']/i', $itemM[0], $hrefM);
                                    $imgRelPath = $hrefM[1] ?? null;
                                }
                            }

                            // Try: <item ... properties="cover-image" .../>
                            if (!$imgRelPath) {
                                if (preg_match('/<item\b[^>]+\bproperties=["\']cover-image["\'][^>]*>/i', $opf, $itemM)) {
                                    preg_match('/\bhref=["\']([^"\']+)["\']/i', $itemM[0], $hrefM);
                                    $imgRelPath = $hrefM[1] ?? null;
                                }
                            }

                            if ($imgRelPath) {
                                // Resolve path relative to OPF file
                                $zipImgPath = ($opfDir && $opfDir !== '.')
                                    ? rtrim($opfDir, '/') . '/' . ltrim($imgRelPath, '/')
                                    : $imgRelPath;
                                $imgData = $zip->getFromName($zipImgPath);
                                if ($imgData !== false && strlen($imgData) > 500) {
                                    $ext = strtolower(pathinfo($imgRelPath, PATHINFO_EXTENSION));
                                    $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? $ext : 'jpg';
                                    $coverPath = $coversDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
                                    file_put_contents($coverPath, $imgData);
                                }
                            }
                        }
                    }
                }

                // Strategy 2: fallback — common filenames
                if (!$coverPath) {
                    $candidates = [
                        'cover.jpg', 'cover.jpeg', 'cover.png',
                        'OEBPS/cover.jpg', 'OEBPS/cover.jpeg', 'OEBPS/cover.png',
                        'OEBPS/images/cover.jpg', 'OEBPS/images/cover.png',
                        'images/cover.jpg', 'images/cover.png',
                        'OPS/cover.jpg', 'OPS/images/cover.jpg',
                    ];
                    foreach ($candidates as $cn) {
                        $imgData = $zip->getFromName($cn);
                        if ($imgData !== false && strlen($imgData) > 500) {
                            $ext = strtolower(pathinfo($cn, PATHINFO_EXTENSION));
                            $coverPath = $coversDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
                            file_put_contents($coverPath, $imgData);
                            break;
                        }
                    }
                }

                $zip->close();
                return $coverPath;
            }

        } catch (\Exception $e) {
            // Best-effort — don't fail the upload
        }

        return null;
    }

    /**
     * Resize an uploaded cover image to max 400x600 and save as JPEG.
     * Uses GD which is always compiled in.
     */
    private function resizeCoverImage(string $path, string $mime): string
    {
        try {
            $src = match ($mime) {
                'image/png'  => @imagecreatefrompng($path),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
                default      => @imagecreatefromjpeg($path),
            };

            if (!$src) {
                // If GD can't read it, just leave the original file as-is
                return $path;
            }

            $w = imagesx($src);
            $h = imagesy($src);

            $maxW = 400;
            $maxH = 600;

            $ratio = min($maxW / max($w, 1), $maxH / max($h, 1));
            // Only downscale, never upscale
            if ($ratio >= 1.0) {
                imagedestroy($src);
                // Still normalise to JPEG for consistency
                $jpgPath = preg_replace('/\.[^.]+$/', '.jpg', $path);
                if ($jpgPath !== $path) {
                    imagejpeg($src, $jpgPath, 88);
                    @unlink($path);
                    return $jpgPath;
                }
                return $path;
            }

            $newW = (int)round($w * $ratio);
            $newH = (int)round($h * $ratio);

            $dst = imagecreatetruecolor($newW, $newH);
            // Fill white background (important for PNGs with transparency)
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

            // Always save as JPEG
            $jpgPath = preg_replace('/\.[^.]+$/', '.jpg', $path);
            imagejpeg($dst, $jpgPath, 88);

            imagedestroy($src);
            imagedestroy($dst);

            // Remove original if format changed
            if ($jpgPath !== $path) {
                @unlink($path);
            }

            return $jpgPath;
        } catch (\Throwable) {
            return $path; // Non-fatal — return original path
        }
    }

    /**
     * Fetch cover from Open Library API by title (+ optional author)
     */
    private function fetchCoverOnline(string $title, string $author = ''): array
    {
        try {
            $coversDir = self::STORAGE_BASE . '/covers';
            if (!is_dir($coversDir)) {
                mkdir($coversDir, 0750, true);
            }

            // Build search query
            $query = urlencode($title);
            if ($author !== '') {
                $query .= '+' . urlencode($author);
            }
            $searchUrl = "https://openlibrary.org/search.json?q={$query}&limit=1&fields=cover_i";

            $ctx = stream_context_create([
                'http' => [
                    'timeout'          => 6,
                    'method'           => 'GET',
                    'follow_location'  => 0,
                    'max_redirects'    => 0,
                    'user_agent'       => 'Bookoholik/1.0',
                ]
            ]);

            $response = @file_get_contents($searchUrl, false, $ctx);
            if (!$response) {
                return [null, 'default'];
            }

            $data = json_decode($response, true);
            if (empty($data['docs'][0]['cover_i'])) {
                return [null, 'default'];
            }

            $coverId  = (int)$data['docs'][0]['cover_i'];
            $coverUrl = "https://covers.openlibrary.org/b/id/{$coverId}-M.jpg";

            $imgData = @file_get_contents($coverUrl, false, $ctx);
            if (!$imgData || strlen($imgData) < 1000) {
                // Too small = likely placeholder
                return [null, 'default'];
            }

            $coverPath = $coversDir . '/' . bin2hex(random_bytes(16)) . '.jpg';
            file_put_contents($coverPath, $imgData);

            return [$coverPath, 'fetched'];
        } catch (\Exception $e) {
            return [null, 'default'];
        }
    }

    /**
     * Log audit action
     */
    private function logAction(string $action, string $entityType, string $entityId, ?string $userId): void
    {
        $db = Database::getConnection();

        // Guard against stale JWT UUIDs after a full DB rebuild
        if ($userId !== null) {
            $check = $db->prepare("SELECT 1 FROM users WHERE id = :id");
            $check->execute(['id' => $userId]);
            if (!$check->fetch()) {
                $userId = null;
            }
        }

        $stmt = $db->prepare("
            INSERT INTO audit_log (user_id, action, entity_type, entity_id)
            VALUES (:user_id, :action, :entity_type, :entity_id)
        ");
        $stmt->execute([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
        ]);
    }
}
