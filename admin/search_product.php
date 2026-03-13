<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\ProductLookupService;
if (!Auth::check()) { http_response_code(401); exit; }

$productLookupService = new ProductLookupService($conn);
if(isset($_POST['query'])){
    $rows = $productLookupService->searchSuggestions((string)$_POST['query']);
    foreach ($rows as $row) {
        echo "<div class='suggestion-item' data-id='".$row['id']."' style='padding:5px; cursor:pointer;'>".$row['name']."</div>";
    }
}
?>
