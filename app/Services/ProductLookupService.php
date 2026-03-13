<?php

namespace App\Services;

use App\Models\Product;

class ProductLookupService
{
    private \mysqli $db;
    private Product $productModel;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->productModel = new Product($db);
    }

    /** @return array<int,array<string,mixed>> */
    public function searchByName(string $searchQuery): array
    {
        if ($searchQuery === '') {
            return $this->productModel->findAll('name ASC');
        }

        $rows = $this->productModel->findInStock($searchQuery);
        if (!empty($rows)) {
            return $rows;
        }

        $all = $this->productModel->findAll('name ASC', 300);
        if (!function_exists('mb_strtolower') || !function_exists('mb_strlen') || !function_exists('mb_substr')) {
            return [];
        }

        $queryLower = mb_strtolower($searchQuery, 'UTF-8');
        $result = [];
        foreach ($all as $row) {
            $latin = $this->transliterateBangla((string)($row['name'] ?? ''));
            if (strpos($latin, $queryLower) !== false) {
                $result[] = $row;
            }
        }

        return $result;
    }

    private function transliterateBangla(string $text): string
    {
        $map = [
            'অ' => 'o', 'আ' => 'a', 'ই' => 'i', 'ঈ' => 'ee', 'উ' => 'u', 'ঊ' => 'oo', 'ঋ' => 'ri', 'এ' => 'e', 'ঐ' => 'oi', 'ও' => 'o', 'ঔ' => 'ou',
            'া' => 'a', 'ি' => 'i', 'ী' => 'ee', 'ু' => 'u', 'ূ' => 'oo', 'ৃ' => 'ri', 'ে' => 'e', 'ৈ' => 'oi', 'ো' => 'o', 'ৌ' => 'ou',
            'ক' => 'k', 'খ' => 'kh', 'গ' => 'g', 'ঘ' => 'gh', 'ঙ' => 'ng',
            'চ' => 'ch', 'ছ' => 'chh', 'জ' => 'j', 'ঝ' => 'jh', 'ঞ' => 'n',
            'ট' => 't', 'ঠ' => 'th', 'ড' => 'd', 'ঢ' => 'dh', 'ণ' => 'n',
            'ত' => 't', 'থ' => 'th', 'দ' => 'd', 'ধ' => 'dh', 'ন' => 'n',
            'প' => 'p', 'ফ' => 'ph', 'ব' => 'b', 'ভ' => 'bh', 'ম' => 'm',
            'য' => 'y', 'র' => 'r', 'ল' => 'l', 'শ' => 'sh', 'ষ' => 'sh', 'স' => 's', 'হ' => 'h', 'য়' => 'y', 'ড়' => 'r', 'ঢ়' => 'rh', 'ং' => 'ng', 'ঃ' => 'h', 'ঁ' => 'n'
        ];

        $out = '';
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $out .= $map[$char] ?? $char;
        }
        return strtolower($out);
    }

    /** @return array<int,array<string,mixed>> */
    public function searchSuggestions(string $query): array
    {
        $like = '%' . $query . '%';
        $stmt = $this->db->prepare('SELECT id, name FROM products WHERE name LIKE ? ORDER BY name ASC');
        $stmt->bind_param('s', $like);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** @return array{stock:int,price:float}|array<string,mixed> */
    public function getStockPriceByProductId(int $id): array
    {
        $product = $this->productModel->findById($id);
        if (!$product) {
            return [];
        }
        return [
            'stock' => (int)($product['stock'] ?? 0),
            'price' => (float)($product['price'] ?? 0),
        ];
    }
}
