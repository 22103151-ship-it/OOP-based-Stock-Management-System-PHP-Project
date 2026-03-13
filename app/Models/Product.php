<?php

namespace App\Models;

/**
 * Product — model for the `products` table.
 *
 * Encapsulates every read/write operation on product data
 * including stock management helpers.
 */
class Product extends BaseModel
{
    protected string $table = 'products';

    // ------------------------------------------------------------------ reads

    /**
     * Return all products that have stock > 0.
     * Optional search term filters by name (LIKE).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findInStock(?string $search = null): array
    {
        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $stmt = $this->db->prepare(
                "SELECT * FROM products WHERE stock > 0 AND name LIKE ? ORDER BY name"
            );
            $stmt->bind_param('s', $like);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $result = $this->db->query("SELECT * FROM products WHERE stock > 0 ORDER BY name");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Products whose stock is at or below $threshold.
     * Default threshold matches common alert level.
     */
    public function findLowStock(int $threshold = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM products WHERE stock <= ? AND stock > 0 ORDER BY stock ASC"
        );
        $stmt->bind_param('i', $threshold);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** Find a product by exact name. Returns null when not found. */
    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE name = ? LIMIT 1");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ------------------------------------------------------------------ writes

    /**
     * Insert a new product and return its new ID.
     *
     * @param  string|null  $image  Filename relative to assets/images/
     */
    public function create(string $name, float $price, int $stock, ?string $image = null): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO products (name, price, stock, image) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('sdis', $name, $price, $stock, $image);
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    /**
     * Adjust stock by a signed integer delta.
     *  +10 adds ten units; -10 removes ten units.
     */
    public function updateStock(int $id, int $delta): bool
    {
        $stmt = $this->db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $stmt->bind_param('ii', $delta, $id);
        return $stmt->execute();
    }

    /**
     * Safely decrement stock (atomic guard: only runs when stock >= quantity).
     * Returns false if there was insufficient stock and the update was skipped.
     */
    public function decrementStock(int $id, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?"
        );
        $stmt->bind_param('iii', $quantity, $id, $quantity);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    /** Update price of a product. */
    public function updatePrice(int $id, float $price): bool
    {
        $stmt = $this->db->prepare("UPDATE products SET price = ? WHERE id = ?");
        $stmt->bind_param('di', $price, $id);
        return $stmt->execute();
    }

    /** Update the image filename stored against a product. */
    public function updateImage(int $id, string $image): bool
    {
        $stmt = $this->db->prepare("UPDATE products SET image = ? WHERE id = ?");
        $stmt->bind_param('si', $image, $id);
        return $stmt->execute();
    }
}
