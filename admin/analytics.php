<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\AdminMetricsService;
Auth::requireRole('admin');
include '../includes/header.php';

$metricsService = new AdminMetricsService($conn);
$analytics = $metricsService->getAnalyticsData();

$total_revenue = $analytics['total_revenue'] ?? 0;
$total_costs = $analytics['total_costs'] ?? 0;
$profit_loss = $analytics['profit_loss'] ?? 0;
$monthly_sales = $analytics['monthly_sales'] ?? [];
$top_products = $analytics['top_products'] ?? [];
$order_status = $analytics['order_status'] ?? [];
$low_stock_products = $analytics['low_stock_products'] ?? [];
$high_demand_products = $analytics['high_demand_products'] ?? [];
$low_performance_products = $analytics['low_performance_products'] ?? [];
$avg_stock = $analytics['avg_stock'] ?? 0;
$business_score = $analytics['business_score'] ?? 0;
$stock_levels = $analytics['stock_levels'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Analytics Dashboard</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --bg-color: #f4f7fc;
            --main-color: #1a2a47;
            --accent-color: #ff9800;
            --card-bg: #ffffff;
            --text-color: #333;
            --border-color: #e0e0e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.35)), url('../assets/images/home-bg.jpg') center/cover fixed;
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.35)), url('../assets/images/home-bg.jpg') center/cover;
            color: white;
            padding: 18px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .header-top {
            text-align: left;
            margin-bottom: 15px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            transition: background-color 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            text-decoration: none;
            color: white;
        }

        .back-btn i {
            font-size: 0.9rem;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 5px solid var(--accent-color);
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--main-color);
        }

        .stat-card p {
            color: #666;
            font-size: 0.9rem;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .chart-container h3 {
            text-align: center;
            margin-bottom: 20px;
            color: var(--main-color);
        }

        .full-width-chart {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .full-width-chart h3 {
            text-align: center;
            margin-bottom: 20px;
            color: var(--main-color);
        }

        .table-container {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .table-container h3 {
            margin-bottom: 20px;
            color: var(--main-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: var(--main-color);
            color: white;
        }

        .profit {
            color: #28a745;
        }

        .loss {
            color: #dc3545;
        }

        .profit {
            background-color: rgba(40, 167, 69, 0.1);
        }

        .loss {
            background-color: rgba(220, 53, 69, 0.1);
        }

        /* AI Insights Styles */
        .ai-insights {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .ai-insights h3 {
            color: var(--main-color);
            margin-bottom: 25px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .business-score {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
        }

        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .score-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: var(--main-color);
            font-weight: bold;
        }

        .score-number {
            font-size: 2rem;
            display: block;
        }

        .score-label {
            font-size: 0.8rem;
            display: block;
            margin-top: 5px;
        }

        .score-info h4 {
            margin: 0 0 10px 0;
            color: var(--main-color);
        }

        .score-info .excellent { color: #28a745; font-weight: bold; }
        .score-info .good { color: #17a2b8; font-weight: bold; }
        .score-info .fair { color: #ffc107; font-weight: bold; }
        .score-info .poor { color: #dc3545; font-weight: bold; }

        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .insight-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--accent-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .insight-card.alert { border-left-color: #dc3545; }
        .insight-card.success { border-left-color: #28a745; }
        .insight-card.info { border-left-color: #17a2b8; }
        .insight-card.optimization { border-left-color: #ffc107; }

        .insight-card h4 {
            margin: 0 0 15px 0;
            color: var(--main-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .insight-card ul {
            margin: 0;
            padding-left: 20px;
        }

        .insight-card li {
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .alert-item {
            padding: 10px;
            background: rgba(220, 53, 69, 0.1);
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .alert-item.success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .optimization-metrics {
            display: flex;
            justify-content: space-around;
            margin-bottom: 15px;
        }

        .metric {
            text-align: center;
        }

        .metric-value {
            display: block;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--main-color);
        }

        .metric-label {
            display: block;
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .business-score {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .insights-grid {
                grid-template-columns: 1fr;
            }

            .optimization-metrics {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <h1><i class="fas fa-chart-line"></i> Analytics Dashboard</h1>
            <p>Comprehensive business insights and financial analytics</p>
        </div>

        <!-- Key Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>৳<?php echo number_format($total_revenue, 2); ?></h3>
                <p>Total Revenue</p>
            </div>
            <div class="stat-card">
                <h3>৳<?php echo number_format($total_costs, 2); ?></h3>
                <p>Total Costs</p>
            </div>
            <div class="stat-card">
                <h3 class="<?php echo $profit_loss >= 0 ? 'profit' : 'loss'; ?>">
                    ৳<?php echo number_format(abs($profit_loss), 2); ?>
                </h3>
                <p><?php echo $profit_loss >= 0 ? 'Profit' : 'Loss'; ?></p>
            </div>
            <div class="stat-card">
                <h3><?php echo count($top_products); ?></h3>
                <p>Active Products</p>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <!-- Monthly Revenue Chart -->
            <div class="chart-container">
                <h3>Monthly Revenue Trend</h3>
                <canvas id="revenueChart"></canvas>
            </div>

            <!-- Order Status Distribution -->
            <div class="chart-container">
                <h3>Order Status Distribution</h3>
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- Top Products Chart -->
        <div class="full-width-chart">
            <h3>Top Selling Products</h3>
            <canvas id="productsChart"></canvas>
        </div>

        <!-- Stock Levels -->
        <div class="table-container">
            <h3>Current Stock Levels</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Stock Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stock_levels)): ?>
                        <tr>
                            <td colspan="2" style="text-align:center; color:#666;">No stock data found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stock_levels as $stock): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stock['name']); ?></td>
                            <td><?php echo $stock['stock']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Financial Summary -->
        <div class="table-container">
            <h3>Financial Summary</h3>
            <table>
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Amount</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Total Revenue</td>
                        <td>৳<?php echo number_format($total_revenue, 2); ?></td>
                        <td>From product sales</td>
                    </tr>
                    <tr>
                        <td>Total Costs</td>
                        <td>৳<?php echo number_format($total_costs, 2); ?></td>
                        <td>From purchase orders</td>
                    </tr>
                    <tr class="<?php echo $profit_loss >= 0 ? 'profit' : 'loss'; ?>">
                        <td><?php echo $profit_loss >= 0 ? 'Profit' : 'Loss'; ?></td>
                        <td>৳<?php echo number_format(abs($profit_loss), 2); ?></td>
                        <td>Revenue - Costs</td>
                    </tr>
                    <tr>
                        <td>Profit Margin</td>
                        <td><?php echo $total_revenue > 0 ? number_format(($profit_loss / $total_revenue) * 100, 2) . '%' : 'N/A'; ?></td>
                        <td>Profit as % of revenue</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- AI-Powered Business Insights -->
        <div class="ai-insights">
            <h3><i class="fas fa-robot"></i> AI Business Insights & Recommendations</h3>
            
            <div class="business-score">
                <div class="score-circle" style="background: conic-gradient(#28a745 0% <?php echo $business_score; ?>%, #e9ecef <?php echo $business_score; ?>% 100%)">
                    <div class="score-text">
                        <span class="score-number"><?php echo $business_score; ?></span>
                        <span class="score-label">Business Health</span>
                    </div>
                </div>
                <div class="score-info">
                    <h4>Overall Business Health: 
                        <span class="<?php echo $business_score >= 70 ? 'excellent' : ($business_score >= 50 ? 'good' : ($business_score >= 30 ? 'fair' : 'poor')); ?>">
                            <?php echo $business_score >= 70 ? 'Excellent' : ($business_score >= 50 ? 'Good' : ($business_score >= 30 ? 'Fair' : 'Poor')); ?>
                        </span>
                    </h4>
                    <p>Profit margin, product performance এবং inventory health এর উপর ভিত্তি করে</p>
                </div>
            </div>

            <div class="insights-grid">
                <!-- Critical Alerts -->
                <div class="insight-card alert">
                    <h4><i class="fas fa-exclamation-triangle"></i> Critical Alerts</h4>
                    <?php if (count($low_stock_products) > 0): ?>
                        <div class="alert-item">
                            <strong>Low Stock Alert:</strong> <?php echo count($low_stock_products); ?> টি পণ্যে এখনই restock প্রয়োজন
                            <ul>
                                <?php foreach (array_slice($low_stock_products, 0, 3) as $product): ?>
                                <li><?php echo htmlspecialchars($product['name']); ?> (<?php echo $product['stock']; ?> units)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="alert-item success">
                            <strong>✓</strong> সব পণ্য যথেষ্ট Stock এ আছে
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Top Performers -->
                <div class="insight-card success">
                    <h4><i class="fas fa-trophy"></i> High-Demand Products</h4>
                    <p>এই best-seller গুলোর Stock বজায় রাখুন:</p>
                    <?php if (count($high_demand_products) > 0): ?>
                        <ul>
                            <?php foreach ($high_demand_products as $product): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($product['product_name']); ?></strong><br>
                                <small>Sold: <?php echo $product['total_sold']; ?> units | Revenue: ৳<?php echo number_format($product['revenue'], 2); ?> | Stock: <?php echo $product['stock']; ?></small>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>সাম্প্রতিক sales data পাওয়া যায়নি</p>
                    <?php endif; ?>
                </div>

                <!-- Improvement Suggestions -->
                <div class="insight-card info">
                    <h4><i class="fas fa-lightbulb"></i> AI Recommendations</h4>
                    <ul>
                        <?php if ($profit_loss < 0): ?>
                        <li><strong>⚠️ Loss Prevention:</strong> এখনকার অপারেশনে Loss হচ্ছে। low‑margin পণ্যের cost কমান বা price বাড়ান।</li>
                            <li><strong>🔥 Highly Recommended:</strong> প্রতিটি পণ্যের <em>unit cost</em> (avg purchase cost) ট্র্যাক করে margin &lt; 10% হলে price adjust করুন।</li>
                            <li><strong>✅ Highly Recommended:</strong> slow‑moving স্টক (৩০ দিনের কম বিক্রি) এ discount/ bundle দিন, নতুন stock ক্রয় বন্ধ রাখুন।</li>
                        <?php elseif ($profit_loss / max($total_revenue, 1) < 0.1): ?>
                        <li><strong>💡 Profit Optimization:</strong> Profit margin কম। high‑margin পণ্যে ফোকাস করুন এবং supplier deal improve করুন।</li>
                            <li><strong>🔥 Highly Recommended:</strong> top 5 বিক্রিত পণ্যে vendor negotiation করে cost 3–5% কমান, তাতেই margin বাড়বে।</li>
                            <li><strong>✅ Highly Recommended:</strong> একই ক্যাটাগরিতে premium version যোগ করে upsell করুন (AOV বাড়বে)।</li>
                        <?php else: ?>
                        <li><strong>✅ Profit Health:</strong> Profit margin ভালো। top performer পণ্যের inventory বাড়াতে পারেন।</li>
                            <li><strong>🔥 Highly Recommended:</strong> best‑seller গুলোতে stockout এড়াতে <em>reorder point</em> সেট করুন (avg daily sales × lead time + safety stock)।</li>
                            <li><strong>✅ Highly Recommended:</strong> অতিরিক্ত profit দিয়ে marketing/loyalty offer চালু করুন যাতে repeat order বাড়ে।</li>
                        <?php endif; ?>

                        <?php if (count($low_stock_products) > 3): ?>
                        <li><strong>📦 Inventory Management:</strong> অনেক পণ্যে Stock কম। automatic reorder alert সেট করুন এবং bulk purchase বিবেচনা করুন।</li>
                        <?php endif; ?>

                        <?php if (count($low_performance_products) > 0): ?>
                        <li><strong>🎯 Product Strategy:</strong> <?php echo count($low_performance_products); ?> টি পণ্যের sales কম। promotion, bundling অথবা slow‑moving item phase‑out বিবেচনা করুন।</li>
                        <?php endif; ?>

                            <li><strong>✅ Highly Recommended:</strong> supplier lead time বেশি হলে safety stock বাড়ান, দ্রুত supplier থাকলে inventory কমিয়ে cash‑flow উন্নত করুন।</li>

                        <?php if ($business_score < 50): ?>
                        <li><strong>🚀 Business Growth:</strong> Overall Business Health উন্নতি প্রয়োজন। sales volume বাড়ানো এবং inventory turnover optimize করুন।</li>
                        <?php elseif ($business_score > 80): ?>
                        <li><strong>📈 Expansion Opportunity:</strong> Excellent performance! product line expand বা নতুন sales channel বিবেচনা করুন।</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Inventory Optimization -->
                <div class="insight-card optimization">
                    <h4><i class="fas fa-cogs"></i> Inventory Optimization</h4>
                    <div class="optimization-metrics">
                        <div class="metric">
                            <span class="metric-value"><?php echo round($avg_stock); ?></span>
                            <span class="metric-label">Avg Stock Level</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value"><?php echo count($low_stock_products); ?></span>
                            <span class="metric-label">Low Stock Items</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value"><?php echo count($high_demand_products); ?></span>
                            <span class="metric-label">High Demand Items</span>
                        </div>
                    </div>
                    <p><strong>AI Suggestion:</strong> top 5 পণ্যের জন্য 20–30% বেশি Stock রাখুন যাতে stockout কমে এবং sales বাড়ে।</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Monthly Revenue Chart
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            const revenueData = <?php echo json_encode(array_reverse($monthly_sales)); ?>;
            
            new Chart(revenueCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: revenueData.map(item => item.month || 'N/A'),
                    datasets: [{
                        label: 'Revenue ($)',
                        data: revenueData.map(item => item.revenue || 0),
                        borderColor: 'rgb(255, 152, 0)',
                        backgroundColor: 'rgba(255, 152, 0, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Order Status Chart
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            const statusData = <?php echo json_encode($order_status); ?>;
            
            new Chart(statusCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(statusData).length > 0 ? Object.keys(statusData) : ['No Data'],
                    datasets: [{
                        data: Object.values(statusData).length > 0 ? Object.values(statusData) : [1],
                        backgroundColor: [
                            'rgb(255, 152, 0)',
                            'rgb(76, 175, 80)',
                            'rgb(244, 67, 54)'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });
        }

        // Top Products Chart
        const productsCtx = document.getElementById('productsChart');
        if (productsCtx) {
            const productsData = <?php echo json_encode($top_products); ?>;
            
            new Chart(productsCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: productsData.length > 0 ? productsData.map(item => item.product_name || 'Unknown') : ['No Data'],
                    datasets: [{
                        label: 'Quantity Sold',
                        data: productsData.length > 0 ? productsData.map(item => item.total_quantity || 0) : [0],
                        backgroundColor: 'rgba(26, 42, 71, 0.8)',
                        borderColor: 'rgb(26, 42, 71)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>