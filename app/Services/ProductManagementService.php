<?php

namespace App\Services;

class ProductManagementService
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function addProduct(array $post, array $files): array
    {
        $name = trim((string)($post['name'] ?? ''));
        $price = (float)($post['price'] ?? 0);
        $stock = (int)($post['stock'] ?? 0);
        $supplierId = (int)($post['supplier_id'] ?? 0);
        $createdAt = date('Y-m-d H:i:s');

        $imageResult = $this->handleImageUpload($files, null, '../assets/images/');
        if (!empty($imageResult['error'])) {
            return ['ok' => false, 'message' => "<div class='alert-error'>" . $imageResult['error'] . '</div>'];
        }
        $imageName = (string)($imageResult['file'] ?? '');

        $check = $this->db->prepare('SELECT id FROM products WHERE LOWER(name) = LOWER(?)');
        $check->bind_param('s', $name);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->close();
            return ['ok' => false, 'message' => "<div class='alert-error'>Product already exists!</div>"];
        }
        $check->close();

        $stmt = $this->db->prepare('INSERT INTO products (name, price, stock, supplier_id, image, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sdiiss', $name, $price, $stock, $supplierId, $imageName, $createdAt);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            return ['ok' => true, 'message' => "<div class='alert-success'>Product added successfully!</div>"];
        }
        return ['ok' => false, 'message' => "<div class='alert-error'>Product add not possible.</div>"];
    }

    public function editProduct(array $post, array $files): array
    {
        $id = (int)($post['id'] ?? 0);
        $name = trim((string)($post['name'] ?? ''));
        $price = (float)($post['price'] ?? 0);
        $stock = (int)($post['stock'] ?? 0);
        $supplierId = (int)($post['supplier_id'] ?? 0);

        $current = $this->findById($id);
        $currentImage = $current['image'] ?? null;

        $imageResult = $this->handleImageUpload($files, $currentImage, '../assets/images/');
        if (!empty($imageResult['error'])) {
            return ['ok' => false, 'message' => "<div class='alert-error'>" . $imageResult['error'] . '</div>'];
        }
        $imageName = $imageResult['file'] ?? $currentImage ?? '';

        $check = $this->db->prepare('SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND id <> ?');
        $check->bind_param('si', $name, $id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->close();
            return ['ok' => false, 'message' => "<div class='alert-error'>Another product with the same name already exists!</div>"];
        }
        $check->close();

        $stmt = $this->db->prepare('UPDATE products SET name=?, price=?, stock=?, supplier_id=?, image=? WHERE id=?');
        $stmt->bind_param('sdiisi', $name, $price, $stock, $supplierId, $imageName, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            return ['ok' => true, 'message' => "<div class='alert-success'>Product updated successfully!</div>"];
        }
        return ['ok' => false, 'message' => "<div class='alert-error'>Product update failed.</div>"];
    }

    public function deleteProduct(int $id): array
    {
        $stmt = $this->db->prepare('DELETE FROM products WHERE id=?');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            return ['ok' => true, 'message' => "<div class='alert-success'>Product deleted successfully!</div>"];
        }
        return ['ok' => false, 'message' => "<div class='alert-error'>Delete failed.</div>"];
    }

    /** @return array<int,array<string,mixed>> */
    public function getProductsWithSupplier(): array
    {
        $result = $this->db->query('SELECT p.*, s.name AS supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.id DESC');
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function getSuppliers(): array
    {
        $result = $this->db->query('SELECT * FROM suppliers ORDER BY name ASC');
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private function handleImageUpload(array $files, ?string $oldImage, string $targetDir): array
    {
        if (!isset($files['image']) || (int)$files['image']['error'] !== 0) {
            return ['file' => $oldImage ?? ''];
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;

        if (!in_array((string)$files['image']['type'], $allowedTypes, true) || (int)$files['image']['size'] > $maxSize) {
            return ['error' => 'Invalid image file. Only JPG, PNG, GIF, WebP allowed. Max size: 5MB.'];
        }

        $imageName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename((string)$files['image']['name']));
        $targetPath = $targetDir . $imageName;

        if (!move_uploaded_file((string)$files['image']['tmp_name'], $targetPath)) {
            return ['error' => 'Failed to upload image.'];
        }

        if (!empty($oldImage) && file_exists($targetDir . $oldImage)) {
            @unlink($targetDir . $oldImage);
        }

        return ['file' => $imageName];
    }
}
