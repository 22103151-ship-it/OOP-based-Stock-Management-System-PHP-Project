<?php
session_start();
header('Content-Type: application/json');
include '../config.php';

use App\Core\Auth;
use App\Core\Request;
use App\Services\CartService;
use App\Models\Customer;

if (!Auth::check() || Auth::role() !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$customerId = Auth::customerId();
$action     = Request::postString('action');

// Resolve customer type and build CartService
$customerModel = new Customer($conn);
$customerType  = $customerModel->getType($customerId);
$cart          = new CartService($conn, $customerId, $customerType);

switch ($action) {
    case 'add':
        echo json_encode($cart->add(Request::postInt('product_id'), Request::postInt('quantity')));
        break;
    case 'update':
        echo json_encode($cart->update(Request::postInt('product_id'), Request::postInt('quantity')));
        break;
    case 'remove':
        echo json_encode($cart->remove(Request::postInt('product_id')));
        break;
    case 'get':
        echo json_encode($cart->get());
        break;
    case 'clear':
        echo json_encode($cart->clear());
        break;
    case 'validate':
        echo json_encode($cart->validate());
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
