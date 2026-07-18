<?php

/**
 * API Routes Definition
 * 
 * All routes are prefixed with /api
 */

use App\Controllers\AuthController;
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
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/register', [AuthController::class, 'register']);

// ===== Protected Routes (require authentication) =====

// Auth - user profile
$router->get('/api/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);
$router->put('/api/auth/profile', [AuthController::class, 'updateProfile'], [AuthMiddleware::class]);
$router->put('/api/auth/password', [AuthController::class, 'changePassword'], [AuthMiddleware::class]);

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

// ===== Admin Routes =====

// User management
$router->get('/api/users', [UsersController::class, 'index'], [AdminMiddleware::class]);
$router->get('/api/users/{id}', [UsersController::class, 'show'], [AdminMiddleware::class]);
$router->put('/api/users/{id}', [UsersController::class, 'update'], [AdminMiddleware::class]);
$router->delete('/api/users/{id}', [UsersController::class, 'destroy'], [AdminMiddleware::class]);

// Backup management
$router->post('/api/backup/create', [BackupController::class, 'create'], [AdminMiddleware::class]);
$router->get('/api/backup/list', [BackupController::class, 'list'], [AdminMiddleware::class]);
$router->get('/api/backup/download/{filename}', [BackupController::class, 'download'], [AdminMiddleware::class]);
$router->delete('/api/backup/{filename}', [BackupController::class, 'destroy'], [AdminMiddleware::class]);
