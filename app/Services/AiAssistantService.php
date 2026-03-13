<?php

namespace App\Services;

class AiAssistantService
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function getCustomer(int $customerId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function saveCustomerMessage(int $customerId, string $message): void
    {
        $stmt = $this->db->prepare("INSERT INTO ai_chat_messages (customer_id, message, message_type) VALUES (?, ?, 'customer_to_ai')");
        $stmt->bind_param('is', $customerId, $message);
        $stmt->execute();
        $stmt->close();
    }

    public function saveAssistantResponse(int $customerId, string $response): void
    {
        $stmt = $this->db->prepare("INSERT INTO ai_chat_messages (customer_id, response, message_type) VALUES (?, ?, 'ai_to_customer')");
        $stmt->bind_param('is', $customerId, $response);
        $stmt->execute();
        $stmt->close();
    }

    /** @return array<int,array<string,mixed>> */
    public function getChatHistory(int $customerId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_chat_messages WHERE customer_id = ? ORDER BY id DESC LIMIT ?');
        $stmt->bind_param('ii', $customerId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return array_reverse($rows);
    }

    public function generateResponse(string $message, int $customerId): string
    {
        $normalized = strtolower($message);

        if (strpos($normalized, 'stock') !== false || strpos($normalized, 'available') !== false || strpos($normalized, 'quantity') !== false) {
            $productName = $this->extractProductName($normalized);
            if ($productName !== null) {
                $query = 'SELECT name, stock FROM products WHERE name LIKE ? AND stock > 0 LIMIT 1';
                $stmt = $this->db->prepare($query);
                $searchTerm = '%' . $productName . '%';
                $stmt->bind_param('s', $searchTerm);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();

                if ($product) {
                    return '📦 ' . $product['name'] . ' has ' . $product['stock'] . ' units available in stock.';
                }

                return "❌ I couldn't find that product or it might be out of stock. Please check our products page for current availability.";
            }

            $result = $this->db->query('SELECT name, stock FROM products WHERE stock > 0 ORDER BY stock DESC LIMIT 5');
            $response = "📋 Here are our top 5 products by stock level:\n";
            if ($result instanceof \mysqli_result) {
                while ($product = $result->fetch_assoc()) {
                    $response .= '• ' . $product['name'] . ': ' . $product['stock'] . " units\n";
                }
            }
            return $response;
        }

        if (strpos($normalized, 'price') !== false || strpos($normalized, 'cost') !== false || strpos($normalized, 'how much') !== false) {
            $productName = $this->extractProductName($normalized);
            if ($productName !== null) {
                $query = 'SELECT name, price FROM products WHERE name LIKE ? LIMIT 1';
                $stmt = $this->db->prepare($query);
                $searchTerm = '%' . $productName . '%';
                $stmt->bind_param('s', $searchTerm);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();

                if ($product) {
                    return '💰 The price of ' . $product['name'] . ' is $' . number_format((float)$product['price'], 2) . '.';
                }

                return "❌ I couldn't find pricing information for that product. Please check our products page.";
            }

            $result = $this->db->query('SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE stock > 0');
            $priceRange = $result ? $result->fetch_assoc() : ['min_price' => 0, 'max_price' => 0];
            return '💰 Our product prices range from $' . number_format((float)$priceRange['min_price'], 2) . ' to $' . number_format((float)$priceRange['max_price'], 2) . '. Check our products page for specific pricing!';
        }

        if (strpos($normalized, 'order') !== false || strpos($normalized, 'status') !== false) {
            $stmt = $this->db->prepare('SELECT status, COUNT(*) as count FROM customer_orders WHERE customer_id = ? GROUP BY status');
            $stmt->bind_param('i', $customerId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $response = "📋 Your order summary:\n";
                while ($row = $result->fetch_assoc()) {
                    $response .= '• ' . ucfirst($row['status']) . ': ' . $row['count'] . " orders\n";
                }
                return $response;
            }

            return "📭 You don't have any orders yet. Browse our products to place your first order!";
        }

        if (strpos($normalized, 'help') !== false || strpos($normalized, 'what can you do') !== false) {
            return "🤖 Hi! I'm your AI assistant. I can help you with:\n\n"
               . "📦 Check product stock availability\n"
               . "💰 Get product pricing information\n"
               . "📋 View your order status\n"
               . "🛒 Browse available products\n"
               . "📞 Contact support\n\n"
               . "Just ask me anything about our products or your orders!";
        }

        if (strpos($normalized, 'support') !== false || strpos($normalized, 'contact') !== false || strpos($normalized, 'admin') !== false) {
            return "📞 For additional support, you can:\n\n"
               . "• Use the Support section in your dashboard\n"
               . "• Contact our admin team\n"
               . "• Check our FAQ section\n\n"
               . "Our support team will respond to your queries as soon as possible!";
        }

        foreach (['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'] as $greeting) {
            if (strpos($normalized, $greeting) !== false) {
                return '👋 Hello! Welcome to our Stock Management System. How can I help you today?';
            }
        }

        if (strpos($normalized, 'thank') !== false) {
            return "🙏 You're welcome! Is there anything else I can help you with?";
        }

        return "🤔 I'm here to help with product information, stock availability, pricing, and order status. Try asking me about specific products or use commands like 'stock of [product name]' or 'price of [product name]'!";
    }

    private function extractProductName(string $message): ?string
    {
        $patterns = [
            '/stock of (.+)/i',
            '/price of (.+)/i',
            '/how much is (.+)/i',
            '/(.+) stock/i',
            '/(.+) price/i',
            '/about (.+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }
}
