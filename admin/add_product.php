<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\ProductManagementService;
Auth::requireRole('admin');

$productService = new ProductManagementService($conn);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $productService->addProduct($_POST, $_FILES);
    $message = $result['message'] ?? '<div class="alert-error">Failed to add product.</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #f4f7fc;
            --card: #ffffff;
            --text: #2c3e50;
            --muted: #7f8c8d;
            --accent: #3498db;
            --danger: #e74c3c;
            --shadow: rgba(0, 0, 0, 0.08);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            margin: 0;
            padding: 0;
            color: var(--text);
        }
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }
        .page-header h1 { margin: 0; font-size: 1.8rem; }
        .page-header p { margin: 4px 0 0; color: var(--muted); }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: #2c3e50;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            box-shadow: 0 4px 10px var(--shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }
        .back-btn:hover { background: #34495e; transform: translateY(-2px); box-shadow: 0 6px 14px var(--shadow); }
        .card {
            background: var(--card);
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 6px 18px var(--shadow);
        }
        form { display: grid; gap: 18px; }
        label { font-weight: 600; color: var(--text); }
        input[type="text"], input[type="number"], input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #e1e8ed;
            border-radius: 6px;
            font-size: 1rem;
        }
        input[type="file"] { padding: 10px; }
        .actions { display: flex; gap: 12px; }
        button[type="submit"] {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 12px 18px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }
        button[type="submit"]:hover {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(52, 152, 219, 0.4);
        }
        .alert-success, .alert-error {
            padding: 12px 14px;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .alert-success { background: #eafaf1; color: #27ae60; border: 1px solid #b8e6cc; }
        .alert-error { background: #fdecea; color: #c0392b; border: 1px solid #f5c6c1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h1><i class="fa-solid fa-box"></i> Add Product</h1>
                <p>Create a new product with image and price</p>
            </div>
            <a class="back-btn" href="dashboard.php"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if ($message) { echo $message; } ?>

        <div class="card">
            <form method="POST" enctype="multipart/form-data">
                <div>
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter product name" required>
                </div>
                <div>
                    <label for="price">Price</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" placeholder="Enter price" required>
                </div>
                <div>
                    <label for="stock">Stock (optional)</label>
                    <input type="number" id="stock" name="stock" min="0" value="0">
                </div>
                <div>
                    <label for="image">Product Image</label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>
                <div class="actions">
                    <button type="submit" name="add_product"><i class="fa-solid fa-plus"></i> Add Product</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
