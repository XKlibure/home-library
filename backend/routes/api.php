<?php

/**
 * API Routes Definition
 * 
 * All routes are prefixed with /api
 */

use App\Controllers\AuthController;
use App\Controllers\PasswordResetController;
use App\Controllers\AccessRequestController;
use App\Controllers\EbooksController;
use App\Controllers\EbookPluginController;
use App\Controllers\BooksController;
use App\Controllers\LendingController;
use App\Controllers\ReportsController;
use App\Controllers\UsersController;
use App\Controllers\GenresController;
use App\Controllers\WritersController;
use App\Controllers\PublishersController;
use App\Controllers\LocationsController;
use App\Controllers\ScanController;
use App\Controllers\BackupController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\UserMiddleware;

// ===== Public Routes =====

// Authentication
$router->post('/api/auth/login',    [AuthController::class, 'login']);
$router->post('/api/auth/register', [AuthController::class, 'register']); // returns 403 — kept for clarity

// Password reset (public, no auth required)
$router->post('/api/auth/forgot-password',          [PasswordResetController::class, 'forgotPassword']);
$router->get('/api/auth/reset-password/validate',   [PasswordResetController::class, 'validateToken']);
$router->post('/api/auth/reset-password',           [PasswordResetController::class, 'resetPassword']);

// Access request (public — non-members ask to join)
$router->post('/api/auth/request-access', [AccessRequestController::class, 'store']);

// ===== Protected Routes (require authentication) =====

// Auth - user profile
$router->get('/api/auth/me',                       [AuthController::class, 'me'],                    [AuthMiddleware::class]);
$router->put('/api/auth/profile',                  [AuthController::class, 'updateProfile'],          [AuthMiddleware::class]);
$router->put('/api/auth/password',                 [AuthController::class, 'changePassword'],         [AuthMiddleware::class]);
$router->post('/api/auth/change-initial-password', [AuthController::class, 'changeInitialPassword'], [AuthMiddleware::class]);

// Books CRUD
$router->get('/api/books', [BooksController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/books/{id}', [BooksController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/books', [BooksController::class, 'store'], [AuthMiddleware::class]);
$router->put('/api/books/{id}', [BooksController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/books/{id}', [BooksController::class, 'destroy'], [AuthMiddleware::class]);
$router->post('/api/books/{id}/toggle-read', [BooksController::class, 'toggleRead'], [AuthMiddleware::class]);
$router->post('/api/books/isbn-lookup', [BooksController::class, 'isbnLookup'], [AuthMiddleware::class]);

// Book Scanner (camera OCR) - Admin and User only
$router->post('/api/scan/cover', [ScanController::class, 'scanCover'], [UserMiddleware::class]);
$router->post('/api/scan/back', [ScanController::class, 'scanBack'], [UserMiddleware::class]);

// Writers CRUD (read: any auth user, write: admin only)
$router->get('/api/writers', [WritersController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/writers/{id}', [WritersController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/writers', [WritersController::class, 'store'], [AdminMiddleware::class]);
$router->put('/api/writers/{id}', [WritersController::class, 'update'], [AdminMiddleware::class]);
$router->delete('/api/writers/{id}', [WritersController::class, 'destroy'], [AdminMiddleware::class]);

// Genres CRUD (read: any auth user, write: admin only)
$router->get('/api/genres', [GenresController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/genres', [GenresController::class, 'store'], [AdminMiddleware::class]);
$router->put('/api/genres/{id}', [GenresController::class, 'update'], [AdminMiddleware::class]);
$router->delete('/api/genres/{id}', [GenresController::class, 'destroy'], [AdminMiddleware::class]);

// Publishers CRUD (read: any auth user, write: admin only)
$router->get('/api/publishers', [PublishersController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/publishers/{id}', [PublishersController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/publishers', [PublishersController::class, 'store'], [AdminMiddleware::class]);
$router->put('/api/publishers/{id}', [PublishersController::class, 'update'], [AdminMiddleware::class]);
$router->delete('/api/publishers/{id}', [PublishersController::class, 'destroy'], [AdminMiddleware::class]);

// Locations (Addresses > Rooms > Shelves)
$router->get('/api/locations/tree', [LocationsController::class, 'tree'], [AuthMiddleware::class]);
$router->get('/api/locations', [LocationsController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/locations/{id}', [LocationsController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/locations', [LocationsController::class, 'store'], [AdminMiddleware::class]);
$router->put('/api/locations/{id}', [LocationsController::class, 'update'], [AdminMiddleware::class]);
$router->delete('/api/locations/{id}', [LocationsController::class, 'destroy'], [AdminMiddleware::class]);
$router->get('/api/locations/{id}/rooms', [LocationsController::class, 'listRooms'], [AuthMiddleware::class]);
$router->post('/api/rooms', [LocationsController::class, 'storeRoom'], [AdminMiddleware::class]);
$router->put('/api/rooms/{id}', [LocationsController::class, 'updateRoom'], [AdminMiddleware::class]);
$router->delete('/api/rooms/{id}', [LocationsController::class, 'destroyRoom'], [AdminMiddleware::class]);
$router->get('/api/rooms/{id}/shelves', [LocationsController::class, 'listShelves'], [AuthMiddleware::class]);
$router->post('/api/shelves', [LocationsController::class, 'storeShelf'], [AdminMiddleware::class]);
$router->put('/api/shelves/{id}', [LocationsController::class, 'updateShelf'], [AdminMiddleware::class]);
$router->delete('/api/shelves/{id}', [LocationsController::class, 'destroyShelf'], [AdminMiddleware::class]);

// Lending
$router->get('/api/lending', [LendingController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/lending', [LendingController::class, 'store'], [AuthMiddleware::class]);
$router->put('/api/lending/{id}', [LendingController::class, 'update'], [AuthMiddleware::class]);
$router->post('/api/lending/{id}/return', [LendingController::class, 'returnBook'], [AuthMiddleware::class]);
$router->delete('/api/lending/{id}', [LendingController::class, 'destroy'], [AuthMiddleware::class]);
$router->get('/api/lending/overdue', [LendingController::class, 'overdue'], [AuthMiddleware::class]);

// Reports
$router->get('/api/reports/summary', [ReportsController::class, 'summary'], [AuthMiddleware::class]);
$router->get('/api/reports/by-genre', [ReportsController::class, 'byGenre'], [AuthMiddleware::class]);
$router->get('/api/reports/by-author', [ReportsController::class, 'byAuthor'], [AuthMiddleware::class]);
$router->get('/api/reports/by-year', [ReportsController::class, 'byYear'], [AuthMiddleware::class]);
$router->get('/api/reports/by-location', [ReportsController::class, 'byLocation'], [AuthMiddleware::class]);
$router->get('/api/reports/lending-history', [ReportsController::class, 'lendingHistory'], [AuthMiddleware::class]);
$router->get('/api/reports/export/csv', [ReportsController::class, 'exportCsv'], [AuthMiddleware::class]);
$router->get('/api/reports/export/pdf', [ReportsController::class, 'exportPdf'], [AuthMiddleware::class]);

// ===== E-Book Plugin Routes =====

$router->get('/api/ebook-plugin/status',                 [EbookPluginController::class, 'status'],         [AuthMiddleware::class]);
$router->get('/api/ebook-plugin/global-status',          [EbookPluginController::class, 'globalStatus'],   [AdminMiddleware::class]);
$router->get('/api/ebook-plugin/users',                  [EbookPluginController::class, 'userOverrides'],  [AdminMiddleware::class]);
$router->post('/api/ebook-plugin/enable',                [EbookPluginController::class, 'enable'],         [AdminMiddleware::class]);
$router->post('/api/ebook-plugin/disable',               [EbookPluginController::class, 'disable'],        [AdminMiddleware::class]);
$router->post('/api/ebook-plugin/user/{userId}/enable',  [EbookPluginController::class, 'enableForUser'],  [AdminMiddleware::class]);
$router->post('/api/ebook-plugin/user/{userId}/disable', [EbookPluginController::class, 'disableForUser'], [AdminMiddleware::class]);

// E-Books CRUD
$router->get('/api/ebooks',                      [EbooksController::class, 'index'],         [AuthMiddleware::class]);
$router->get('/api/ebooks/{id}',                 [EbooksController::class, 'show'],          [AuthMiddleware::class]);
$router->post('/api/ebooks/upload',              [EbooksController::class, 'upload'],        [UserMiddleware::class]);
$router->put('/api/ebooks/{id}',                 [EbooksController::class, 'update'],        [UserMiddleware::class]);
$router->delete('/api/ebooks/{id}',              [EbooksController::class, 'destroy'],       [UserMiddleware::class]);
$router->post('/api/ebooks/{id}/progress',       [EbooksController::class, 'updateProgress'],[AuthMiddleware::class]);
$router->get('/api/ebooks/{id}/open',            [EbooksController::class, 'open'],          [AuthMiddleware::class]);
$router->get('/api/ebooks/{id}/cover',           [EbooksController::class, 'cover'],          [AuthMiddleware::class]);
$router->post('/api/ebooks/{id}/cover',           [EbooksController::class, 'uploadCover'],    [UserMiddleware::class]);
$router->post('/api/ebooks/{id}/reextract-cover', [EbooksController::class, 'reextractCover'], [UserMiddleware::class]);
$router->post('/api/ebooks/{id}/refresh-cover',  [EbooksController::class, 'refreshCover'],  [UserMiddleware::class]);

// ===== Admin Routes =====

// User management
// Note: specific routes MUST come before /{id} wildcard routes
$router->get('/api/users',                               [UsersController::class, 'index'],   [AdminMiddleware::class]);
$router->post('/api/users',                              [UsersController::class, 'store'],   [AdminMiddleware::class]);

// Access requests (registered BEFORE /users/{id} to avoid wildcard match)
$router->get('/api/users/access-requests',               [AccessRequestController::class, 'index'],   [AdminMiddleware::class]);
$router->post('/api/users/access-requests/{id}/approve', [AccessRequestController::class, 'approve'], [AdminMiddleware::class]);
$router->delete('/api/users/access-requests/{id}',       [AccessRequestController::class, 'reject'],  [AdminMiddleware::class]);

// Generic user CRUD (wildcard — must come after all specific /users/* routes)
$router->get('/api/users/{id}',                          [UsersController::class, 'show'],    [AdminMiddleware::class]);
$router->put('/api/users/{id}',                          [UsersController::class, 'update'],  [AdminMiddleware::class]);
$router->delete('/api/users/{id}',                       [UsersController::class, 'destroy'], [AdminMiddleware::class]);

// Backup management
$router->post('/api/backup/create', [BackupController::class, 'create'], [AdminMiddleware::class]);
$router->get('/api/backup/list', [BackupController::class, 'list'], [AdminMiddleware::class]);
$router->get('/api/backup/download/{filename}', [BackupController::class, 'download'], [AdminMiddleware::class]);
$router->delete('/api/backup/{filename}', [BackupController::class, 'destroy'], [AdminMiddleware::class]);
