<?php

namespace App\Controllers;

use App\Config\Database;

/**
 * Publishers Controller
 * CRUD operations for publishers/edition houses
 */
class PublishersController extends BaseController
{
    /**
     * GET /api/publishers
     */
    public function index(array $params): void
    {
        $query = $this->getQueryParams();
        $db = Database::getConnection();

        $conditions = [];
        $bindings = [];

        if (!empty($query['search'])) {
            $search = '%' . $query['search'] . '%';
            $conditions[] = "(name ILIKE :search OR name_ar ILIKE :search OR city ILIKE :search)";
            $bindings['search'] = $search;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT p.*, 
                    (SELECT COUNT(*) FROM books WHERE publisher_id = p.id) as books_count
                FROM publishers p 
                {$whereClause} 
                ORDER BY p.name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);

        $this->json(['data' => $stmt->fetchAll()]);
    }

    /**
     * GET /api/publishers/{id}
     */
    public function show(array $params): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM publishers WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);
        $publisher = $stmt->fetch();

        if (!$publisher) {
            $this->json(['error' => 'Publisher not found.'], 404);
            return;
        }

        $this->json(['data' => $publisher]);
    }

    /**
     * POST /api/publishers
     */
    public function store(array $params): void
    {
        $data = $this->getRequestBody();

        if (empty($data['name'])) {
            $this->json(['error' => 'Publisher name is required.'], 422);
            return;
        }

        $db = Database::getConnection();

        $stmt = $db->prepare('
            INSERT INTO publishers (name, name_ar, name_fr, address, city, country, phone, email, website, notes)
            VALUES (:name, :name_ar, :name_fr, :address, :city, :country, :phone, :email, :website, :notes)
            RETURNING *
        ');
        $stmt->execute([
            'name' => $this->sanitize($data['name']),
            'name_ar' => $this->sanitize($data['name_ar'] ?? ''),
            'name_fr' => $this->sanitize($data['name_fr'] ?? ''),
            'address' => $this->sanitize($data['address'] ?? ''),
            'city' => $this->sanitize($data['city'] ?? ''),
            'country' => $this->sanitize($data['country'] ?? ''),
            'phone' => $this->sanitize($data['phone'] ?? ''),
            'email' => $this->sanitize($data['email'] ?? ''),
            'website' => $this->sanitize($data['website'] ?? ''),
            'notes' => $data['notes'] ?? '',
        ]);

        $publisher = $stmt->fetch();
        $this->json(['message' => 'Publisher created successfully.', 'data' => $publisher], 201);
    }

    /**
     * PUT /api/publishers/{id}
     */
    public function update(array $params): void
    {
        $data = $this->getRequestBody();
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT * FROM publishers WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);
        if (!$stmt->fetch()) {
            $this->json(['error' => 'Publisher not found.'], 404);
            return;
        }

        $fields = ['name', 'name_ar', 'name_fr', 'address', 'city', 'country', 'phone', 'email', 'website', 'notes'];
        $updates = [];
        $bindings = ['id' => $params['id']];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = :{$field}";
                $bindings[$field] = is_string($data[$field]) ? $this->sanitize($data[$field]) : $data[$field];
            }
        }

        if (empty($updates)) {
            $this->json(['error' => 'No fields to update.'], 422);
            return;
        }

        $updates[] = "updated_at = NOW()";
        $sql = "UPDATE publishers SET " . implode(', ', $updates) . " WHERE id = :id RETURNING *";

        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);

        $this->json(['message' => 'Publisher updated successfully.', 'data' => $stmt->fetch()]);
    }

    /**
     * DELETE /api/publishers/{id}
     */
    public function destroy(array $params): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM publishers WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);

        if ($stmt->rowCount() === 0) {
            $this->json(['error' => 'Publisher not found.'], 404);
            return;
        }

        $this->json(['message' => 'Publisher deleted successfully.']);
    }
}
