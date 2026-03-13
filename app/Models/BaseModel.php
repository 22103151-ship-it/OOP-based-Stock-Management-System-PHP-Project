<?php

namespace App\Models;

/**
 * BaseModel — abstract base for all domain models.
 *
 * Provides generic findById, findAll, count, and deleteById so that
 * subclasses only define $table and domain-specific methods.
 *
 * Subclass example:
 *   class Product extends BaseModel {
 *       protected string $table = 'products';
 *   }
 */
abstract class BaseModel
{
    protected \mysqli $db;
    protected string  $table;
    protected string  $primaryKey = 'id';

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    // ------------------------------------------------------------------ reads

    /** Find a single row by primary key. Returns null when not found. */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Fetch every row, optionally sorted and limited.
     *
     * @param  string $orderBy  Raw SQL ORDER BY expression, e.g. "name ASC"
     * @param  int    $limit    0 means no LIMIT
     * @return array<int, array<string, mixed>>
     */
    public function findAll(string $orderBy = '', int $limit = 0): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        $result = $this->db->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Count rows, with an optional WHERE clause.
     *
     * @param  string  $where   Raw SQL condition without the WHERE keyword, e.g. "status = ?"
     * @param  array   $params  Values for the placeholders in $where
     * @param  string  $types   bind_param type string, e.g. "si" — auto-detected as all strings when empty
     */
    public function count(string $where = '', array $params = [], string $types = ''): int
    {
        $sql = "SELECT COUNT(*) AS total FROM `{$this->table}`";
        if ($where) {
            $sql .= " WHERE {$where}";
        }

        if ($params) {
            $stmt = $this->db->prepare($sql);
            if (!$types) {
                $types = str_repeat('s', count($params));
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return (int)$stmt->get_result()->fetch_assoc()['total'];
        }

        return (int)$this->db->query($sql)->fetch_assoc()['total'];
    }

    // ------------------------------------------------------------------ writes

    /** Delete a row by primary key. Returns true on success. */
    public function deleteById(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?"
        );
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // ------------------------------------------------------------------ helper

    /** Expose the connection for sub-classes that need raw queries. */
    protected function db(): \mysqli
    {
        return $this->db;
    }
}
