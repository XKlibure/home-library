<?php

namespace App\Controllers;

use App\Config\Database;

/**
 * Book Scanner Controller
 * Uses Google Gemini Flash Vision API to extract book info from photos.
 * Falls back to Tesseract OCR if no API key is configured.
 */
class ScanController extends BaseController
{
    /**
     * POST /api/scan/cover
     * Scan front cover to extract title and author
     */
    public function scanCover(array $params): void
    {
        $data = $this->getRequestBody();

        // Rate limiting: 20 scans per hour per user to protect the Gemini API key
        $authUser = $this->getAuthUser();
        $rateLimiter = new \App\Middleware\RateLimiter();
        $rateLimitKey = 'scan:' . ($authUser['id'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (!$rateLimiter->attempt($rateLimitKey, 20, 3600)) {
            $this->json(['error' => 'Scan limit reached. Please try again later.'], 429);
            return;
        }

        if (empty($data['image'])) {
            $this->json(['error' => 'No image provided.'], 422);
            return;
        }

        // Decode base64 image
        $imageData = $this->decodeBase64Image($data['image']);
        if (!$imageData) {
            $this->json(['error' => 'Invalid image format.'], 422);
            return;
        }

        // Extract book info using AI vision
        $parsed = $this->analyzeWithGemini($imageData, 'cover');

        if (!$parsed) {
            $this->json(['error' => 'Could not analyze image. Check GEMINI_API_KEY configuration.'], 500);
            return;
        }

        // Search database for matches
        $matches = $this->searchBooks($parsed['title'] ?? '', $parsed['author'] ?? '');

        $this->json([
            'data' => [
                'parsed' => $parsed,
                'matches' => $matches,
                'found' => count($matches) > 0,
            ]
        ]);
    }

    /**
     * POST /api/scan/back
     * Scan back cover/last page to extract ISBN, publisher, year
     */
    public function scanBack(array $params): void
    {
        $data = $this->getRequestBody();

        // Rate limiting: shared 20-per-hour limit per user across all scan endpoints
        $authUser = $this->getAuthUser();
        $rateLimiter = new \App\Middleware\RateLimiter();
        $rateLimitKey = 'scan:' . ($authUser['id'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (!$rateLimiter->attempt($rateLimitKey, 20, 3600)) {
            $this->json(['error' => 'Scan limit reached. Please try again later.'], 429);
            return;
        }

        if (empty($data['image'])) {
            $this->json(['error' => 'No image provided.'], 422);
            return;
        }

        // Decode base64 image
        $imageData = $this->decodeBase64Image($data['image']);
        if (!$imageData) {
            $this->json(['error' => 'Invalid image format.'], 422);
            return;
        }

        // Extract book info using AI vision
        $parsed = $this->analyzeWithGemini($imageData, 'back');

        if (!$parsed) {
            $this->json(['error' => 'Could not analyze image. Check GEMINI_API_KEY configuration.'], 500);
            return;
        }

        // If ISBN found, search database
        $matches = [];
        if (!empty($parsed['isbn'])) {
            $matches = $this->searchByIsbn($parsed['isbn']);
        }
        if (empty($matches) && !empty($parsed['title'])) {
            $matches = $this->searchBooks($parsed['title'] ?? '', $parsed['author'] ?? '');
        }

        $this->json([
            'data' => [
                'parsed' => $parsed,
                'matches' => $matches,
                'found' => count($matches) > 0,
            ]
        ]);
    }

    /**
     * Analyze image using Google Gemini Flash Vision API
     */
    private function analyzeWithGemini(string $imageData, string $mode): ?array
    {
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        if (empty($apiKey)) {
            // Fall back to Tesseract if no API key
            return $this->analyzeWithTesseract($imageData, $mode);
        }

        // Determine MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);

        // Build the prompt based on scan mode
        if ($mode === 'cover') {
            $prompt = "This is a photo of a book's front cover. Extract the following information:\n" .
                "- title: The book title (in original language, keep Arabic/French as-is)\n" .
                "- author: The author name (in original language)\n\n" .
                "The book may be in Arabic (العربية), French, or English.\n" .
                "Return ONLY valid JSON with keys: title, author\n" .
                "If you cannot determine a field, use empty string.\n" .
                "Example: {\"title\": \"السنة النبوية\", \"author\": \"محمد الغزالي\"}";
        } else {
            $prompt = "This is a photo of a book's back cover or last page. Extract the following information:\n" .
                "- isbn: The ISBN number (10 or 13 digits, numbers only)\n" .
                "- edition_house: The publisher/editor name (in original language)\n" .
                "- publication_year: The year of publication (4 digits)\n" .
                "- title: The book title if visible\n" .
                "- author: The author name if visible\n\n" .
                "The book may be in Arabic (العربية), French, or English.\n" .
                "Return ONLY valid JSON with keys: isbn, edition_house, publication_year, title, author\n" .
                "If you cannot determine a field, use empty string or null for year.\n" .
                "Example: {\"isbn\": \"9782070360024\", \"edition_house\": \"دار الشروق\", \"publication_year\": 2005, \"title\": \"\", \"author\": \"\"}";
        }

        // Call Gemini API
        $model = $_ENV['GEMINI_MODEL'] ?? 'gemini-2.0-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => base64_encode($imageData),
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 500,
            ]
        ];

        $jsonPayload = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 429) {
            // Rate limited — inform user instead of falling back to poor OCR
            return ['title' => '', 'author' => '', '_error' => 'AI rate limit reached. Please wait a moment and try again.'];
        }

        if ($httpCode !== 200 || !$response) {
            error_log("Gemini API error: HTTP $httpCode - $curlError - $response");
            // Fall back to Tesseract
            return $this->analyzeWithTesseract($imageData, $mode);
        }

        // Parse Gemini response
        $result = json_decode($response, true);
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Extract JSON from response (Gemini sometimes wraps in markdown code blocks)
        if (preg_match('/\{[^}]+\}/s', $text, $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return $parsed;
            }
        }

        // Try the full text as JSON
        $parsed = json_decode($text, true);
        if ($parsed) {
            return $parsed;
        }

        error_log("Gemini response parse failed: $text");
        return $this->analyzeWithTesseract($imageData, $mode);
    }

    /**
     * Fallback: Analyze with Tesseract OCR
     */
    private function analyzeWithTesseract(string $imageData, string $mode): ?array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'scan_');
        file_put_contents($tempFile, $imageData);

        $extractedText = $this->runOcr($tempFile);
        unlink($tempFile);

        if (empty($extractedText)) {
            return ['title' => '', 'author' => '', 'isbn' => '', 'edition_house' => '', 'publication_year' => null];
        }

        if ($mode === 'cover') {
            return $this->parseCoverText($extractedText);
        } else {
            return $this->parseBackText($extractedText);
        }
    }

    /**
     * Run Tesseract OCR (fallback)
     */
    private function runOcr(string $filePath): string
    {
        $outputBase = tempnam(sys_get_temp_dir(), 'ocr_');
        $command = sprintf(
            'tesseract %s %s -l ara+eng+fra --psm 6 --oem 1 2>/dev/null',
            escapeshellarg($filePath),
            escapeshellarg($outputBase)
        );
        exec($command);

        $ocrText = '';
        $outputFile = $outputBase . '.txt';
        if (file_exists($outputFile)) {
            $ocrText = trim(file_get_contents($outputFile));
            unlink($outputFile);
        }
        if (file_exists($outputBase)) {
            unlink($outputBase);
        }
        return $ocrText;
    }

    /**
     * Parse cover text (Tesseract fallback)
     */
    private function parseCoverText(string $text): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $text)), fn($l) => mb_strlen(trim($l)) >= 2);
        $lines = array_values($lines);

        $title = '';
        $author = '';

        if (count($lines) >= 2) {
            // Longest line is likely the title
            $longestIdx = 0;
            $longestLen = 0;
            foreach ($lines as $idx => $line) {
                if (mb_strlen($line) > $longestLen) {
                    $longestLen = mb_strlen($line);
                    $longestIdx = $idx;
                }
            }
            $title = $lines[$longestIdx];
            foreach ($lines as $idx => $line) {
                if ($idx !== $longestIdx) {
                    $author = $line;
                    break;
                }
            }
        } elseif (count($lines) === 1) {
            $title = $lines[0];
        }

        return ['title' => trim($title), 'author' => trim($author)];
    }

    /**
     * Parse back page text (Tesseract fallback)
     */
    private function parseBackText(string $text): array
    {
        $isbn = '';
        $publisher = '';
        $year = '';

        if (preg_match('/ISBN[\s:\-]*([0-9\-X]{10,17})/i', $text, $matches)) {
            $isbn = preg_replace('/[^0-9X]/', '', strtoupper($matches[1]));
        }
        if (preg_match('/(?:19|20)\d{2}/', $text, $matches)) {
            $year = $matches[0];
        }

        return [
            'title' => '',
            'author' => '',
            'isbn' => $isbn,
            'edition_house' => $publisher,
            'publication_year' => $year ? (int)$year : null,
        ];
    }

    /**
     * Decode base64 image data
     */
    private function decodeBase64Image(string $base64): ?string
    {
        if (preg_match('/^data:image\/\w+;base64,/', $base64)) {
            $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($decoded);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (!in_array($mimeType, $allowedTypes, true)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Search books by title and/or author
     */
    private function searchBooks(string $title, string $author): array
    {
        if (empty($title) && empty($author)) {
            return [];
        }

        $db = Database::getConnection();
        $conditions = [];
        $bindings = [];

        if (!empty($title)) {
            $conditions[] = "title ILIKE :title";
            $bindings['title'] = '%' . $title . '%';
        }
        if (!empty($author)) {
            $conditions[] = "author ILIKE :author";
            $bindings['author'] = '%' . $author . '%';
        }

        $whereClause = implode(' OR ', $conditions);
        $stmt = $db->prepare("SELECT id, title, author, isbn, genre, language FROM books WHERE {$whereClause} LIMIT 10");
        $stmt->execute($bindings);

        return $stmt->fetchAll();
    }

    /**
     * Search books by ISBN
     */
    private function searchByIsbn(string $isbn): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, title, author, isbn, genre, language FROM books WHERE isbn = :isbn LIMIT 5");
        $stmt->execute(['isbn' => $isbn]);
        return $stmt->fetchAll();
    }
}
