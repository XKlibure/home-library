<?php

namespace App\Controllers;

use App\Config\Database;

/**
 * Locations Controller
 * Manages the hierarchy: Addresses → Rooms → Shelves
 */
class LocationsController extends BaseController
{
    // ===== ADDRESSES =====

    /**
     * GET /api/locations
     * List all addresses with room/shelf counts
     */
    public function index(array $params): void
    {
        $db = Database::getConnection();
        $stmt = $db->query('
            SELECT a.*,
                (SELECT COUNT(*) FROM rooms WHERE address_id = a.id) as rooms_count,
                (SELECT COUNT(*) FROM shelves s JOIN rooms r ON s.room_id = r.id WHERE r.address_id = a.id) as shelves_count,
                (SELECT COUNT(*) FROM books b JOIN shelves s ON b.shelf_id = s.id JOIN rooms r ON s.room_id = r.id WHERE r.address_id = a.id) as books_count
            FROM addresses a
            ORDER BY a.is_primary DESC, a.name ASC
        ');
        $this->json(['data' => $stmt->fetchAll()]);
    }

    /**
     * GET /api/locations/{id}
     * Get address with its rooms and shelves
     */
    public function show(array $params): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM addresses WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);
        $address = $stmt->fetch();

        if (!$address) {
            $this->json(['error' => 'Address not found.'], 404);
            return;
        }

        // Get rooms with their shelves
        $stmt = $db->prepare('
            SELECT r.*, 
                (SELECT COUNT(*) FROM shelves WHERE room_id = r.id) as shelves_count,
                (SELECT COUNT(*) FROM books b JOIN shelves s ON b.shelf_id = s.id WHERE s.room_id = r.id) as books_count
            FROM rooms r 
            WHERE r.address_id = :address_id 
            ORDER BY r.name
        ');
        $stmt->execute(['address_id' => $params['id']]);
        $address['rooms'] = $stmt->fetchAll();

        // Get shelves for each room
        foreach ($address['rooms'] as &$room) {
            $stmt = $db->prepare('
                SELECT s.*, 
                    (SELECT COUNT(*) FROM books WHERE shelf_id = s.id) as books_count
                FROM shelves s 
                WHERE s.room_id = :room_id 
                ORDER BY s.name
            ');
            $stmt->execute(['room_id' => $room['id']]);
            $room['shelves'] = $stmt->fetchAll();
        }

        $this->json(['data' => $address]);
    }

    /**
     * POST /api/locations
     */
    public function store(array $params): void
    {
        $data = $this->getRequestBody();

        if (empty($data['name'])) {
            $this->json(['error' => 'Address name is required.'], 422);
            return;
        }

        $db = Database::getConnection();

        // If setting as primary, unset others
        if (!empty($data['is_primary'])) {
            $db->exec("UPDATE addresses SET is_primary = FALSE");
        }

        $stmt = $db->prepare('
            INSERT INTO addresses (name, street, city, state_province, postal_code, country, is_primary, notes)
            VALUES (:name, :street, :city, :state_province, :postal_code, :country, :is_primary, :notes)
            RETURNING *
        ');
        $stmt->execute([
            'name' => $this->sanitize($data['name']),
            'street' => $this->sanitize($data['street'] ?? ''),
            'city' => $this->sanitize($data['city'] ?? ''),
            'state_province' => $this->sanitize($data['state_province'] ?? ''),
            'postal_code' => $this->sanitize($data['postal_code'] ?? ''),
            'country' => $this->sanitize($data['country'] ?? ''),
            'is_primary' => filter_var($data['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 't' : 'f',
            'notes' => $data['notes'] ?? '',
        ]);

        $this->json(['message' => 'Address created.', 'data' => $stmt->fetch()], 201);
    }

    /**
     * PUT /api/locations/{id}
     */
    public function update(array $params): void
    {
        $data = $this->getRequestBody();
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT id FROM addresses WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);
        if (!$stmt->fetch()) {
            $this->json(['error' => 'Address not found.'], 404);
            return;
        }

        // If setting as primary, unset others
        if (!empty($data['is_primary'])) {
            $db->exec("UPDATE addresses SET is_primary = FALSE");
        }

        $fields = ['name', 'street', 'city', 'state_province', 'postal_code', 'country', 'notes'];
        $updates = [];
        $bindings = ['id' => $params['id']];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = :{$field}";
                $bindings[$field] = $this->sanitize($data[$field] ?? '');
            }
        }
        if (array_key_exists('is_primary', $data)) {
            $updates[] = "is_primary = :is_primary";
            $bindings['is_primary'] = filter_var($data['is_primary'], FILTER_VALIDATE_BOOLEAN) ? 't' : 'f';
        }

        if (empty($updates)) {
            $this->json(['error' => 'No fields to update.'], 422);
            return;
        }

        $updates[] = "updated_at = NOW()";
        $sql = "UPDATE addresses SET " . implode(', ', $updates) . " WHERE id = :id RETURNING *";
        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);

        $this->json(['message' => 'Address updated.', 'data' => $stmt->fetch()]);
    }

    /**
     * DELETE /api/locations/{id}
     */
    public function destroy(array $params): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM addresses WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);

        if ($stmt->rowCount() === 0) {
            $this->json(['error' => 'Address not found.'], 404);
            return;
        }

        $this->json(['message' => 'Address deleted.']);
    }

    // ===== ROOMS =====

    /**
     * GET /api/locations/{id}/rooms
     */
    public function listRooms(array $params): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT r.*, 
                (SELECT COUNT(*) FROM shelves WHERE room_id = r.id) as shelves_count,
                (SELECT COUNT(*) FROM books b JOIN shelves s ON b.shelf_id = s.id WHERE s.room_id = r.id) as books_count
            FROM rooms r 
            WHERE r.address_id = :address_id 
            ORDER BY r.name
        ');
        $stmt->execute(['address_id' => $params['id']]);
        $this->json(['data' => $stmt->fetchAll()]);
    }

    /**
     * POST /api/rooms
     */
    public function storeRoom(array $params): void
    {
        $data = $this->getRequestBody();

        $errors = $this->validateRequired($data, ['address_id', 'name']);
        if ($errors) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('
            INSERT INTO rooms (address_id, name, description, floor)
            VALUES (:address_id, :name, :description, :floor)
            RETURNING *
        ');
        $stmt->execute([
            'address_id' => $data['address_id'],
            'name' => $this->sanitize($data['name']),
            'description' => $this->sanitize($data['description'] ?? ''),
            'floor' => $this->sanitize($data['floor'] ?? ''),
        ]);

        $this->json(['message' => 'Room created.', 'data' => $stmt->fetch()], 201);
    }

    /**
     * PUT /api/rooms/{id}
     */
    public function updateRoom(array $params): void
    {
        $data = $this->getRequestBody();
        $db = Database::getConnection();

        $fields = ['name', 'description', 'floor'];
        $updates = [];
        $bindings = ['id' => $params['id']];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = :{$field}";
                $bindings[$field] = $this->sanitize($data[$field] ?? '');
            }
        }

        if (empty($updates)) {
            $this->json(['error' => 'No fields to update.'], 422);
            return;
        }

        $updates[] = "updated_at = NOW()";
        $sql = "UPDATE rooms SET " . implode(', ', $updates) . " WHERE id = :id RETURNING *";
        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);
        $room = $stmt->fetch();

        if (!$room) {
            $this->json(['error' => 'Room not found.'], 404);
            return;
        }

        $this->json(['message' => 'Room updated.', 'data' => $room]);
    }

    /**
     * DELETE /api/rooms/{id}
     */
    public function destroyRoom(array $params): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM rooms WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);

        if ($stmt->rowCount() === 0) {
            $this->json(['error' => 'Room not found.'], 404);
            return;
        }

        $this->json(['message' => 'Room deleted.']);
    }

    // ===== SHELVES =====

    /**
     * GET /api/rooms/{id}/shelves
     */
    public function listShelves(array $params): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT s.*, 
                (SELECT COUNT(*) FROM books WHERE shelf_id = s.id) as books_count
            FROM shelves s 
            WHERE s.room_id = :room_id 
            ORDER BY s.name
        ');
        $stmt->execute(['room_id' => $params['id']]);
        $this->json(['data' => $stmt->fetchAll()]);
    }

    /**
     * POST /api/shelves
     */
    public function storeShelf(array $params): void
    {
        $data = $this->getRequestBody();

        $errors = $this->validateRequired($data, ['room_id', 'name']);
        if ($errors) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('
            INSERT INTO shelves (room_id, name, description, capacity)
            VALUES (:room_id, :name, :description, :capacity)
            RETURNING *
        ');
        $stmt->execute([
            'room_id' => $data['room_id'],
            'name' => $this->sanitize($data['name']),
            'description' => $this->sanitize($data['description'] ?? ''),
            'capacity' => !empty($data['capacity']) ? (int)$data['capacity'] : null,
        ]);

        $this->json(['message' => 'Shelf created.', 'data' => $stmt->fetch()], 201);
    }

    /**
     * PUT /api/shelves/{id}
     */
    public function updateShelf(array $params): void
    {
        $data = $this->getRequestBody();
        $db = Database::getConnection();

        $fields = ['name', 'description'];
        $updates = [];
        $bindings = ['id' => $params['id']];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = :{$field}";
                $bindings[$field] = $this->sanitize($data[$field] ?? '');
            }
        }
        if (array_key_exists('capacity', $data)) {
            $updates[] = "capacity = :capacity";
            $bindings['capacity'] = !empty($data['capacity']) ? (int)$data['capacity'] : null;
        }

        if (empty($updates)) {
            $this->json(['error' => 'No fields to update.'], 422);
            return;
        }

        $updates[] = "updated_at = NOW()";
        $sql = "UPDATE shelves SET " . implode(', ', $updates) . " WHERE id = :id RETURNING *";
        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);
        $shelf = $stmt->fetch();

        if (!$shelf) {
            $this->json(['error' => 'Shelf not found.'], 404);
            return;
        }

        $this->json(['message' => 'Shelf updated.', 'data' => $shelf]);
    }

    /**
     * DELETE /api/shelves/{id}
     */
    public function destroyShelf(array $params): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM shelves WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);

        if ($stmt->rowCount() === 0) {
            $this->json(['error' => 'Shelf not found.'], 404);
            return;
        }

        $this->json(['message' => 'Shelf deleted.']);
    }

    // ===== HELPER: Get full location tree (for dropdowns) =====

    /**
     * GET /api/locations/tree
     * Returns complete hierarchy for select dropdowns
     */
    public function tree(array $params): void
    {
        $db = Database::getConnection();
        $stmt = $db->query('
            SELECT a.id as address_id, a.name as address_name,
                   r.id as room_id, r.name as room_name,
                   s.id as shelf_id, s.name as shelf_name
            FROM addresses a
            LEFT JOIN rooms r ON r.address_id = a.id
            LEFT JOIN shelves s ON s.room_id = r.id
            ORDER BY a.name, r.name, s.name
        ');
        $rows = $stmt->fetchAll();

        // Build tree structure
        $tree = [];
        foreach ($rows as $row) {
            $addrId = $row['address_id'];
            if (!isset($tree[$addrId])) {
                $tree[$addrId] = [
                    'id' => $addrId,
                    'name' => $row['address_name'],
                    'rooms' => [],
                ];
            }
            if ($row['room_id']) {
                $roomId = $row['room_id'];
                if (!isset($tree[$addrId]['rooms'][$roomId])) {
                    $tree[$addrId]['rooms'][$roomId] = [
                        'id' => $roomId,
                        'name' => $row['room_name'],
                        'shelves' => [],
                    ];
                }
                if ($row['shelf_id']) {
                    $tree[$addrId]['rooms'][$roomId]['shelves'][] = [
                        'id' => $row['shelf_id'],
                        'name' => $row['shelf_name'],
                    ];
                }
            }
        }

        // Convert associative to indexed arrays
        $result = [];
        foreach ($tree as $addr) {
            $addr['rooms'] = array_values($addr['rooms']);
            $result[] = $addr;
        }

        $this->json(['data' => $result]);
    }
}
