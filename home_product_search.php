<?php
header('Content-Type: application/json; charset=UTF-8');
require 'config.php';
use App\Models\Product;

// Accept POST (preferred) and GET (fallback)
$query = '';
if (isset($_POST['query'])) {
    $query = trim((string)$_POST['query']);
} elseif (isset($_GET['query'])) {
    $query = trim((string)$_GET['query']);
}

if ($query === '' || !isset($conn)) {
    echo json_encode([]);
    exit;
}

$items = [];
$like = '%' . $query . '%';
$productModel = new Product($conn);

// Primary direct search
$rows = array_slice($productModel->findInStock($query), 0, 20);
foreach ($rows as $row) {
    $items[] = [
        'id' => (int)$row['id'],
        'name' => (string)$row['name'],
        'price' => (float)$row['price'],
        'stock' => isset($row['stock']) ? (int)$row['stock'] : 0
    ];
}

// If nothing matched directly, attempt phonetic match (only if mbstring exists)
if (count($items) === 0 && function_exists('mb_strtolower') && function_exists('mb_strlen') && function_exists('mb_substr')) {
    $fallbackItems = [];
    $queryLower = mb_strtolower($query, 'UTF-8');

    // Transliterate Bangla characters to rough Latin phonetics
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

    $transliterate = function ($text) use ($map) {
        $out = '';
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $out .= $map[$char] ?? $char;
        }
        return strtolower($out);
    };

    // Pull a limited set to avoid heavy scans
    $fallbackRows = $productModel->findAll('name ASC', 200);
    if (!empty($fallbackRows)) {
        foreach ($fallbackRows as $row) {
            $latin = $transliterate($row['name']);
            if (strpos($latin, $queryLower) !== false) {
                $fallbackItems[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'price' => (float)$row['price'],
                    'stock' => isset($row['stock']) ? (int)$row['stock'] : 0
                ];
            }
            if (count($fallbackItems) >= 20) {
                break;
            }
        }
    }
    if (count($fallbackItems) > 0) {
        $items = $fallbackItems;
    }
}

echo json_encode($items);
