<?php

namespace App\Services;

/**
 * Centralized metrics provider for admin dashboard and analytics pages.
 */
class AdminMetricsService
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /** @return array<string,int> */
    public function getDashboardCounts(): array
    {
        return [
            'user_count' => $this->countQuery("SELECT COUNT(*) as total FROM users"),
            'supplier_count' => $this->countQuery("SELECT COUNT(*) as total FROM suppliers"),
            'product_count' => $this->countQuery("SELECT COUNT(*) as total FROM products"),
            'order_count' => $this->countQuery("SELECT COUNT(*) as total FROM purchase_orders"),
            'delivered_count' => $this->countQuery("SELECT COUNT(*) as total FROM purchase_orders WHERE status='delivered'"),
            'pending_count' => $this->countQuery("SELECT COUNT(*) as total FROM purchase_orders WHERE status='pending'"),
            'returned_count' => $this->countQuery("SELECT COUNT(*) as total FROM purchase_orders WHERE status='returned'"),
        ];
    }

    /** @return array<string,mixed> */
    public function getAnalyticsData(): array
    {
        $totalRevenue = $this->singleValue("SELECT SUM(price * quantity) as total FROM sell_product");

        $costsQuery = "
            SELECT SUM(sp.quantity * COALESCE(pc.avg_cost, p.price, 0)) as total
            FROM sell_product sp
            LEFT JOIN products p ON sp.product_id = p.id
            LEFT JOIN (
                SELECT po.product_id,
                       AVG(CASE WHEN po.price > 0 THEN po.price ELSE COALESCE(p2.price, 0) END) AS avg_cost
                FROM purchase_orders po
                LEFT JOIN products p2 ON po.product_id = p2.id
                WHERE po.status = 'delivered' AND (po.payment_status = 'Paid' OR po.payment_status IS NULL)
                GROUP BY po.product_id
            ) pc ON sp.product_id = pc.product_id
        ";
        $totalCosts = $this->singleValue($costsQuery);

        $monthlySales = $this->allRows(" 
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(price * quantity) as revenue,
                COUNT(*) as transactions
            FROM sell_product
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");

        $topProducts = $this->allRows(" 
            SELECT product_name,
                   SUM(total_quantity) AS total_quantity,
                   SUM(total_revenue) AS total_revenue
            FROM (
                SELECT sp.product_name AS product_name,
                       sp.quantity AS total_quantity,
                       (sp.price * sp.quantity) AS total_revenue
                FROM sell_product sp
                UNION ALL
                SELECT p.name AS product_name,
                       co.quantity AS total_quantity,
                       (co.price * co.quantity) AS total_revenue
                FROM customer_orders co
                JOIN products p ON co.product_id = p.id
                WHERE co.status IN ('confirmed','shipped','delivered')
            ) sales
            GROUP BY product_name
            ORDER BY total_quantity DESC
            LIMIT 10
        ");

        $orderStatusRows = $this->allRows("SELECT status, COUNT(*) as count FROM purchase_orders GROUP BY status");
        $orderStatus = [];
        foreach ($orderStatusRows as $row) {
            $orderStatus[$row['status']] = (int)$row['count'];
        }

        $lowStockProducts = $this->allRows("SELECT name, stock FROM products WHERE stock < 10 ORDER BY stock ASC");

        $highDemandProducts = $this->allRows(" 
            SELECT
                sp.product_name,
                SUM(sp.quantity) as total_sold,
                SUM(sp.price * sp.quantity) as revenue,
                p.stock
            FROM sell_product sp
            LEFT JOIN products p ON sp.product_name = p.name
            WHERE sp.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY sp.product_name
            ORDER BY total_sold DESC
            LIMIT 5
        ");

        $lowPerformanceProducts = $this->allRows(" 
            SELECT
                p.name as product_name,
                COALESCE(SUM(sp.quantity), 0) as total_sold,
                p.stock
            FROM products p
            LEFT JOIN sell_product sp ON p.name = sp.product_name AND sp.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.name, p.stock
            HAVING total_sold < 5
            ORDER BY total_sold ASC, p.stock DESC
            LIMIT 5
        ");

        $avgStock = $this->singleValue("SELECT AVG(stock) as avg_stock FROM products", 'avg_stock');
        $stockLevels = $this->allRows("SELECT name, stock FROM products ORDER BY stock DESC");

        $profitLoss = $totalRevenue - $totalCosts;
        $profitMargin = $totalRevenue > 0 ? ($profitLoss / $totalRevenue) : 0;
        $profitScore = max(0, min(100, round(($profitMargin * 100) + 50)));
        $stockScore = count($lowStockProducts) === 0 ? 90 : (count($lowStockProducts) <= 3 ? 65 : 35);
        $salesScore = $totalRevenue > 0 ? 70 : 30;
        $businessScore = (int)round(($profitScore * 0.5) + ($stockScore * 0.3) + ($salesScore * 0.2));

        return [
            'total_revenue' => $totalRevenue,
            'total_costs' => $totalCosts,
            'profit_loss' => $profitLoss,
            'monthly_sales' => $monthlySales,
            'top_products' => $topProducts,
            'order_status' => $orderStatus,
            'low_stock_products' => $lowStockProducts,
            'high_demand_products' => $highDemandProducts,
            'low_performance_products' => $lowPerformanceProducts,
            'avg_stock' => $avgStock,
            'business_score' => $businessScore,
            'stock_levels' => $stockLevels,
        ];
    }

    /** @return array{shipped:int,pending:int} */
    public function getCustomerOrderCounts(): array
    {
        return [
            'shipped' => $this->countQuery("SELECT COUNT(*) as total FROM customer_orders WHERE status='shipped'"),
            'pending' => $this->countQuery("SELECT COUNT(*) as total FROM customer_orders WHERE status='pending'"),
        ];
    }

    private function countQuery(string $sql): int
    {
        $result = $this->db->query($sql);
        if (!$result) {
            return 0;
        }
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    private function singleValue(string $sql, string $field = 'total'): float
    {
        $result = $this->db->query($sql);
        if (!$result) {
            return 0.0;
        }
        $row = $result->fetch_assoc();
        return (float)($row[$field] ?? 0);
    }

    /** @return array<int,array<string,mixed>> */
    private function allRows(string $sql): array
    {
        $result = $this->db->query($sql);
        if (!$result) {
            return [];
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
