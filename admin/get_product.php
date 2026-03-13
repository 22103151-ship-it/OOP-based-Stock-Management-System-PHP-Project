<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\ProductLookupService;
if (!Auth::check()) { http_response_code(401); exit; }

$productLookupService = new ProductLookupService($conn);
if(isset($_POST['id'])){
    $id = intval($_POST['id']);
    $product = $productLookupService->getStockPriceByProductId($id);
    echo json_encode($product);
}
?>
