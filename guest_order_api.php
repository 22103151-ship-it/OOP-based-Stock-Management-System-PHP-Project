<?php
session_start();
header('Content-Type: application/json');
include 'config.php';

use App\Core\Request;
use App\Services\GuestOrderService;

$action  = Request::postString('action');
$service = new GuestOrderService($conn);

switch ($action) {
    // ---- OTP flow ----
    case 'send_otp':
    case 'sendOTP':
        $name      = Request::postString('name');
        $phone     = preg_replace('/\D/', '', Request::postString('phone'));
        $sessionId = bin2hex(random_bytes(16));

        $result = $service->sendOTP($name, $phone, $sessionId);

        if ($result['success']) {
            $_SESSION['guest_phone']      = $phone;
            $_SESSION['guest_session_id'] = $sessionId;
            // Return OTP for demo; remove 'otp' key in production
            echo json_encode([
                'success'    => true,
                'message'    => 'OTP sent to your phone',
                'session_id' => $sessionId,
                'otp'        => $result['otp'], // DEMO ONLY â€” remove in production
            ]);
        } else {
            echo json_encode($result);
        }
        break;

    case 'verify_otp':
    case 'verifyOTP':
        $sessionId = Request::postString('session_id');
        $otp       = Request::postString('otp');
        $phone     = preg_replace('/\D/', '', Request::postString('phone'));

        $result = $service->verifyOTP($sessionId, $otp, $phone ?: null);

        if ($result['success']) {
            $_SESSION['guest_id']       = $result['guest_id'];
            $_SESSION['guest_verified'] = true;
            $_SESSION['guest_name']     = $result['guest_name'];
        }

        echo json_encode($result);
        break;

    // ---- Products ----
    case 'get_products':
    case 'getProducts':
        echo json_encode(['success' => true, 'products' => $service->getProducts()]);
        break;

    // ---- Session-based cart (stored in $_SESSION) ----
    case 'add_to_cart':
        if (empty($_SESSION['guest_verified'])) {
            echo json_encode(['success' => false, 'message' => 'Please verify your phone first']);
            break;
        }

        $productId = Request::postInt('product_id');
        $quantity  = Request::postInt('quantity');

        if ($quantity < 50) {
            echo json_encode(['success' => false, 'message' => 'Guest orders require minimum 50 stocks per product']);
            break;
        }

        if (!isset($_SESSION['guest_cart'])) {
            $_SESSION['guest_cart'] = [];
        }

        $_SESSION['guest_cart'][$productId] = [
            'product_id' => $productId,
            'quantity'   => $quantity,
        ];

        echo json_encode(['success' => true, 'message' => 'Added to cart']);
        break;

    case 'get_cart':
        $cart        = $_SESSION['guest_cart'] ?? [];
        $totalStocks = 0;
        $subtotal    = 0.0;
        $items       = [];

        foreach ($cart as $item) {
            $totalStocks += (int)$item['quantity'];
            $subtotal    += 0; // price fetched below via service.getProducts() if needed
            $items[]      = $item;
        }

        $discount = floor($totalStocks / 100) * 1000;
        $total    = max(0, $subtotal - $discount);

        echo json_encode([
            'success'      => true,
            'cart'         => array_values($items),
            'total_stocks' => $totalStocks,
            'subtotal'     => $subtotal,
            'discount'     => $discount,
            'total'        => $total,
            'can_checkout' => $totalStocks >= 100,
        ]);
        break;

    case 'remove_from_cart':
        $productId = Request::postInt('product_id');
        unset($_SESSION['guest_cart'][$productId]);
        echo json_encode(['success' => true, 'message' => 'Removed from cart']);
        break;

    case 'calculate_total':
        $cart        = $_SESSION['guest_cart'] ?? [];
        $totalStocks = array_sum(array_column($cart, 'quantity'));
        $discount    = floor($totalStocks / 100) * 1000;

        $errors = [];
        if ($totalStocks < 100) {
            $errors[] = 'Minimum 100 stocks required for guest orders';
        }
        foreach ($cart as $item) {
            if ((int)$item['quantity'] < 50) {
                $errors[] = "Product #{$item['product_id']} requires minimum 50 stocks";
            }
        }

        echo json_encode([
            'success'       => true,
            'valid'         => empty($errors),
            'errors'        => $errors,
            'total_stocks'  => $totalStocks,
            'discount'      => $discount,
            'discount_info' => 'à§³1000 off per 100 stocks',
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
