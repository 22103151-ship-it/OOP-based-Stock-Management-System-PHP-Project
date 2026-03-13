<?php
session_start();
include '../config.php';
include '../includes/notification_functions.php';
use App\Core\Auth;
use App\Services\AiAssistantService;
Auth::requireRole('customer');

$assistantService = new AiAssistantService($conn);

// Get customer info
$customer_id = Auth::customerId();
$customer = $assistantService->getCustomer($customer_id);

// Handle AI chat messages
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);

    if (!empty($message)) {
        // Save user message
        $assistantService->saveCustomerMessage($customer_id, $message);

        // Generate AI response based on message content
        $response = $assistantService->generateResponse($message, $customer_id);

        // Trigger admin notification for customer inquiry
        if (strpos(strtolower($message), 'stock') !== false ||
            strpos(strtolower($message), 'price') !== false ||
            strpos(strtolower($message), 'available') !== false) {
            handleCustomerProductRequest($customer_id, 0, 'inquiry', $conn); // 0 for general inquiry
        }

        // Save AI response
        $assistantService->saveAssistantResponse($customer_id, $response);
    }
}

// Get chat history
$chat_history = $assistantService->getChatHistory($customer_id, 50);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - Customer Portal</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --bg-color: #f4f7fc;
            --main-color: #2c3e50;
            --accent-color: #3498db;
            --card-bg: #ffffff;
            --border-color: #e1e8ed;
            --success-color: #27ae60;
            --error-color: #e74c3c;
            --text-color: #2c3e50;
            --bot-color: #667eea;
            --user-color: #f093fb;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: var(--main-color);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .chat-container {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: 600px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            background: linear-gradient(135deg, var(--bot-color), var(--accent-color));
            color: white;
            padding: 20px;
            text-align: center;
        }

        .chat-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .chat-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
        }

        .message.bot {
            justify-content: flex-start;
        }

        .message.user {
            justify-content: flex-end;
        }

        .message-content {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }

        .message.bot .message-content {
            background: linear-gradient(135deg, var(--bot-color), var(--accent-color));
            color: white;
            border-bottom-left-radius: 4px;
        }

        .message.user .message-content {
            background: linear-gradient(135deg, var(--user-color), #f5576c);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin: 0 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .message.bot .message-avatar {
            background: var(--bot-color);
            color: white;
        }

        .message.user .message-avatar {
            background: var(--user-color);
            color: white;
        }

        .chat-input {
            padding: 20px;
            background: var(--card-bg);
            border-top: 1px solid var(--border-color);
        }

        .input-group {
            display: flex;
            gap: 10px;
        }

        .message-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .message-input:focus {
            border-color: var(--accent-color);
        }

        .send-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--accent-color), #2980b9);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .quick-questions {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid var(--border-color);
        }

        .quick-questions h3 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }

        .question-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .question-tag {
            background: var(--accent-color);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .question-tag:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .typing-indicator {
            display: none;
            padding: 10px 20px;
            color: #666;
            font-style: italic;
        }

        .typing-indicator.show {
            display: block;
        }

        /* Back button */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .header-main h1 {
            margin: 0;
        }

        .header-main p {
            margin: 6px 0 0;
        }

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
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        .back-btn:hover {
            background: #34495e;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.2);
        }

        .back-btn i {
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .chat-container {
                height: 500px;
            }

            .message-content {
                max-width: 85%;
            }

            .input-group {
                flex-direction: column;
            }

            .question-tags {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-main">
                <h1><i class="fas fa-robot"></i> AI Assistant</h1>
                <p>Get instant help with product information and support</p>
            </div>
            <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <div class="chat-container">
            <div class="chat-header">
                <h2><i class="fas fa-comments"></i> Chat with AI Assistant</h2>
                <p>Ask me anything about products, stock, or orders!</p>
            </div>

            <div class="quick-questions">
                <h3>Quick Questions:</h3>
                <div class="question-tags">
                    <span class="question-tag" onclick="sendQuickMessage('What products are available?')">Available Products</span>
                    <span class="question-tag" onclick="sendQuickMessage('Check stock of Laptop')">Check Stock</span>
                    <span class="question-tag" onclick="sendQuickMessage('What is the price of Keyboard?')">Product Price</span>
                    <span class="question-tag" onclick="sendQuickMessage('What is my order status?')">Order Status</span>
                    <span class="question-tag" onclick="sendQuickMessage('Help')">Help</span>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <?php if (empty($chat_history)): ?>
                    <div class="message bot">
                        <div class="message-avatar">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="message-content">
                            👋 Hello <?php echo htmlspecialchars($customer['name']); ?>! Welcome to our AI assistant. I'm here to help you with product information, stock availability, pricing, and order support. What would you like to know?
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($chat_history as $msg): ?>
                        <div class="message <?php echo $msg['message_type'] === 'customer_to_ai' ? 'user' : 'bot'; ?>">
                            <div class="message-avatar">
                                <i class="fas fa-<?php echo $msg['message_type'] === 'customer_to_ai' ? 'user' : 'robot'; ?>"></i>
                            </div>
                            <div class="message-content">
                                <?php if ($msg['message_type'] === 'customer_to_ai'): ?>
                                    <?php echo htmlspecialchars($msg['message']); ?>
                                <?php else: ?>
                                    <?php echo nl2br(htmlspecialchars($msg['response'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="typing-indicator" id="typingIndicator">
                <i class="fas fa-circle"></i>
                <i class="fas fa-circle"></i>
                <i class="fas fa-circle"></i>
                AI is typing...
            </div>

            <div class="chat-input">
                <form method="POST" action="" id="chatForm">
                    <div class="input-group">
                        <input type="text" name="message" class="message-input" placeholder="Ask me about products, stock, prices, or orders..." required>
                        <button type="submit" class="send-btn">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto scroll to bottom of chat
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Scroll to bottom on page load
        scrollToBottom();

        // Handle quick message sending
        function sendQuickMessage(message) {
            document.querySelector('.message-input').value = message;
            document.getElementById('chatForm').submit();
        }

        // Show typing indicator when form is submitted
        document.getElementById('chatForm').addEventListener('submit', function() {
            document.getElementById('typingIndicator').classList.add('show');
            scrollToBottom();
        });

        // Auto-focus on input field
        document.querySelector('.message-input').focus();

        // Handle Enter key to send message
        document.querySelector('.message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('chatForm').submit();
            }
        });
    </script>
</body>
</html>