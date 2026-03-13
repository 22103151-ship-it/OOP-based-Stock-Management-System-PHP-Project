# Stock Management System - Architecture Documentation

## Overview

This document describes the **Object-Oriented PHP architecture** of the Stock Management System. The entire codebase follows SOLID principles with a layered architecture pattern.

## Architecture Layers

The system is organized into four primary layers:

```
┌─────────────────────────────────────────────┐
│            Presentation Layer               │
│    *.php files (HTML + JavaScript)          │
│  Root, Admin, Customer, Staff, Supplier     │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│         Application/Service Layer           │
│    App\Services\* (Business Logic)          │
│  NotificationService, CartService, etc.     │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│              Model/Data Layer               │
│      App\Models\* (Data Access)             │
│    BaseModel, Customer, Product, etc.       │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│         Infrastructure/Core Layer           │
│     App\Core\* (Framework Utilities)        │
│   Auth, Request, Database, etc.             │
└─────────────────────────────────────────────┘
```

## Core Components

### 1. App\Core (Infrastructure Layer)

**Location:** `app/Core/`

Provides foundational utilities for all application layers.

#### Auth.php
- **Purpose:** Authentication and authorization
- **Key Methods:**
  - `login($conn, $email, $password, $roleFilter)` - Authenticate user and set session
  - `requireRole($requiredRole)` - Guard pages by role
  - `logout()` - Clear session and destroy login state
  - `customerId()`, `staffId()`, `supplierId()`, `adminId()` - Get current user ID
  - `redirectByRole()` - Route to appropriate dashboard based on role

**Usage Example:**
```php
use App\Core\Auth;

// Protect a page
Auth::requireRole('admin');

// Get current user
$userId = Auth::customerId();
```

#### Request.php
- **Purpose:** Safe input handling with type coercion
- **Key Methods:**
  - `getString($param, $default)` - Get string from GET/POST
  - `postInt($param)` - Get integer from POST
  - `hasPost($param)` - Check if POST parameter exists
  - `all()` - Get all POST/GET parameters as array

**Usage Example:**
```php
use App\Core\Request;

$email = Request::postString('email');
$quantity = Request::postInt('quantity');
if (Request::hasPost('submit')) { ... }
```

#### Database.php
- **Purpose:** Database connection singleton
- **Key Methods:**
  - `connection()` - Get MySQLi connection instance
  - Always use with prepared statements for security

**Usage Pattern:**
```php
$conn = Database::connection();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
```

### 2. App\Models (Data Access Layer)

**Location:** `app/Models/`

**Base Class:** `BaseModel`
- Provides common CRUD operations and query building
- All models inherit from BaseModel
- Enforces prepared statements (no raw SQL injection risk)

**Available Models:**
- `User` - User accounts with roles
- `Customer` - Registered customer profiles
- `Product` - Inventory items with stock
- `Supplier` - Supplier information
- `CustomerOrder` - Customer purchase orders
- `GuestOrder` - Guest bulk orders
- `GuestCustomer` - Guest customer profiles
- `PurchaseOrder` - Supplier purchase orders
- `StaffOrder` - Staff internal orders
- `SellProduct` - Sell transaction history

**Usage Example:**
```php
use App\Models\Product;

$product = new Product($conn);
$allProducts = $product->all(); // Get all
$one = $product->findById(5); // Get by ID
```

### 3. App\Services (Business Logic Layer)

**Location:** `app/Services/`

Services contain business logic, workflow orchestration, and validation rules. They coordinate between Models and external systems.

**Core Services:**

| Service | Purpose | Key Methods |
|---------|---------|------------|
| `NotificationService` | User notifications and message handling | `sendOrderNotification()`, `getUnreadCount()`, `getDotCounts()` |
| `CartService` | Shopping cart operations | `addItem()`, `updateQuantity()`, `calculateTotal()` |
| `GuestOrderService` | Guest bulk order workflow | `sendOTP()`, `verifyOTP()`, `checkout()` |
| `SSLCommerzService` | Payment gateway integration | `initPayment()`, `validateTransaction()` |
| `CustomerCheckoutService` | Customer purchase flow | `validateCart()`, `processCheckout()` |
| `CustomerMembershipService` | Membership tier management | `initiatePayment()`, `handleSuccess()` |
| `CustomerPortalService` | Customer dashboard data | `getDashboardData()`, `getOrderHistory()` |
| `AdminMetricsService` | Admin dashboard analytics | `getSalesMetrics()`, `getOrderStats()` |
| `UserManagementService` | User CRUD and role management | `createUser()`, `updateRole()` |

**Dependency Injection Pattern:**
```php
use App\Services\NotificationService;

// Services receive MySQLi connection in constructor
$service = new NotificationService($conn);
$service->sendOrderNotification($userId, $orderData);
```

### 4. Helper Wrapper Classes

**Location:** `includes/`

Legacy procedural code wrapped in OOP classes for backward compatibility.

#### NotificationFunctionsBridge
- **Pattern:** Static class with singleton service caching
- **Purpose:** Bridges old `notification_functions.php` calls to `NotificationService`
- **Usage:** Maintains backward compatibility while internal calls use service

#### SSLCommerzHelper
- **Pattern:** Static class with convenience methods
- **Methods:** `baseUrl()`, `apiEndpoint()`, `initPayment()`, `validateTransaction()`
- **Purpose:** Wraps payment gateway logic

#### SupplierContextResolver
- **Pattern:** Instance class resolving supplier context
- **Purpose:** Determines supplier ID from current session/request

## Request Flow Example: Customer Order Checkout

To understand how layers interact, here's the flow when a customer places an order:

```
1. User submits checkout form in customer/checkout.php
   ↓
2. Request::postString() and Request::postInt() validate inputs (Core)
   ↓
3. Auth::requireRole('customer') guards access (Core)
   ↓
4. CustomerCheckoutService->processCheckout($cartItems) (Service)
   ├─ Calls Product model to verify stock availability (Model)
   ├─ Calls Customer model to get customer discount tier (Model)
   ├─ Calls SSLCommerzService->initPayment() (Service)
   ├─ Calls NotificationService->sendOrderNotification() (Service)
   └─ Returns payment redirect URL and order data
   ↓
5. JavaScript redirects to SSLCommerz payment gateway
   ↓
6. Payment callback to customer/sslcommerz_success.php
   ├─ SSLCommerzService->validateTransaction() (Service)
   ├─ CustomerOrder model saves successful order
   └─ NotificationService sends confirmation (Service)
   ↓
7. User sees order success page with invoice option
```

## Key OOP Patterns Used

### Dependency Injection
All services and models receive dependencies (primarily `$conn`) through constructors:
```php
public function __construct(mysqli $conn) {
    $this->conn = $conn;
}
```

### Service Locator (Partial)
Core classes (`Auth`, `Request`) act as static facades for convenience:
```php
Auth::requireRole('admin');  // Static method access
```

### Singleton Pattern
NotificationFunctionsBridge uses instance caching:
```php
private static array $instances = [];
public static function service($conn): NotificationService {
    $key = spl_object_id($conn);
    if (!isset(self::$instances[$key])) {
        self::$instances[$key] = new NotificationService($conn);
    }
    return self::$instances[$key];
}
```

### Factory Pattern
Models and Services are instantiated as factories:
```php
$userService = new UserManagementService($conn);
$users = $userService->getAllUsers();
```

### Strategy Pattern
Payment handling can use different strategies (SSLCommerz, future: Stripe, bKash):
```php
$paymentService = new SSLCommerzService(...);  // or new StripeService(...) in future
$paymentService->processPayment($amount);
```

## Database Schema

### Core Tables

**users**
- id (PK), name, email, password, role, created_at

**products**
- id (PK), name, price, stock_quantity, supplier_id (FK), image_url

**suppliers**
- id (PK), name, contact_email, phone

**customers**
- id (PK), user_id (FK), customer_type (guest/pro/vip), discount_tier

**customer_orders**
- id (PK), customer_id (FK), total_amount, status, created_at

**customer_cart**
- id (PK), customer_id (FK), product_id (FK), quantity

**guest_customers**
- id (PK), phone, name, verified_at

**guest_orders**
- id (PK), guest_customer_id (FK), total_amount, status, transaction_id

### Automation Tables

**automated_notifications**
- id (PK), user_id (FK), type, message_data

**notification_dots**
- id (PK), user_id, role, unread_count, last_activity

**ai_chat_messages**
- id (PK), customer_id (FK), message_text, sender (user/ai), created_at

## Naming Conventions

### PHP Classes
- **PascalCase** for class names: `CustomerCheckoutService`, `GuestOrderService`
- **namespace** follows directory structure: `App\Services\`, `App\Models\`, `App\Core\`
- **Method names** in camelCase: `processCheckout()`, `validateTransaction()`

### Database
- **lowercase with underscores** for table names: `customer_orders`, `guest_customers`
- **lowercase with underscores** for columns: `total_amount`, `created_at`

### JavaScript (Frontend Controllers)
- **PascalCase** for class names: `HomePageController`, `CartController`
- **camelCase** for method names: `openModal()`, `submitForm()`
- **Global function delegates** in camelCase: `window.selectRole()`, `window.checkout()`

## Entry Points

### Public Users
- **`index.php`** - Role selection and login
- **`login.php`** - Login form
- **`home.php`** - Landing page with guest ordering

### Admin Authenticated Users
- **`admin/dashboard.php`** - Admin home
- **`admin/products.php`** - Product management
- **`admin/customers.php`** - Customer overview

### Staff Authenticated Users
- **`staff/dashboard.php`** - Staff home
- **`staff/processing_orders.php`** - Order handling

### Supplier Authenticated Users
- **`supplier/dashboard.php`** - Supplier home
- **`supplier/pending_orders.php`** - Order fulfillment

### Customer Authenticated Users
- **`customer/dashboard.php`** - Customer home
- **`customer/products.php`** - Browse and order
- **`customer/membership.php`** - Membership purchase

## Security Model

### Authentication
- Users authenticate via `Auth::login()` with email/password
- Passwords are hashed with `password_hash($password, PASSWORD_BCRYPT)`
- Session tokens stored in `$_SESSION` with role verification on each request

### Authorization
- All protected pages start with: `Auth::requireRole('admin')` or similar
- Role-based access control (RBAC) on six role types: admin, staff, supplier, customer, guest
- Guest users bypass role checks and use phone verification instead

### Data Access
- **All database queries use prepared statements** with bound parameters
- Models always use `$stmt->bind_param()` - no string interpolation in SQL
- Request validation through `Request::*` methods with type coercion
- No direct SQL visible in service or page-level code

## Configuration

### config.php
Main configuration file with database connection and global settings:
```php
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
```

### includes/sslcommerz_config.php
Payment gateway credentials:
```php
$SSLCOMMERZ_STORE_ID = 'YOUR_STORE_ID';
$SSLCOMMERZ_STORE_PASS = 'YOUR_PASS';
$SSLCOMMERZ_SANDBOX = true; // false for production
```

### app/autoload.php
PSR-4 autoloader for `App\*` namespace classes:
```php
spl_autoload_register(function ($class) {
    // Converts App\Core\Auth to app/Core/Auth.php
    // Converts App\Models\Product to app/Models/Product.php
});
```

## Development Workflow

### Adding a New Feature

1. **Identify the layer:**
   - Presentation: Create `pages/*.php`
   - Business Logic: Create `App\Services\NewService.php`
   - Data: Create `App\Models\NewModel.php`

2. **Implement Models first (bottom-up):**
   ```php
   class Product extends BaseModel {
       public function getByCategory($category) { ... }
   }
   ```

3. **Implement Service:**
   ```php
   class ProductService {
       public __construct(mysqli $conn) { ... }
       public function getProductsByCategory($cat) {
           $model = new Product($conn);
           return $model->getByCategory($cat);
       }
   }
   ```

4. **Wire in Page:**
   ```php
   use App\Services\ProductService;
   $service = new ProductService($conn);
   $products = $service->getProductsByCategory('electronics');
   ```

### Testing

Currently no automated tests. To manuallytest a service:

```php
require_once 'app/autoload.php';
require_once 'config.php';

$service = new CustomerCheckoutService($conn);
$result = $service->validateCart($items);
var_dump($result);
```

## Future Improvements

1. **Unit Tests** - Add PHPUnit for service layer testing
2. **API Layer** - Create JSON API endpoints for decoupling frontend
3. **Migration System** - Formalize database versioning (Phinx/Flyway)
4. **Error Handling** - Custom exception classes for better error reporting
5. **Logging** - Centralized logging service (Monolog)
6. **Caching** - Redis/Memcached for session and query caching
7. **Rate Limiting** - Prevent abuse on public endpoints
8. **API Documentation** - Swagger/OpenAPI specs for all endpoints

## File Organization Summary

```
stock/
├── app/                          # Core application code
│   ├── autoload.php              # PSR-4 autoloader
│   ├── bootstrap.php             # Initialization
│   ├── Core/
│   │   ├── Auth.php              # Authentication & authorization
│   │   ├── Request.php           # Input validation & sanitization
│   │   └── Database.php          # DB connection
│   ├── Models/
│   │   ├── BaseModel.php         # Common DB operations
│   │   ├── User.php
│   │   ├── Product.php
│   │   ├── Customer.php
│   │   └── ... (9+ more models)
│   ├── Services/                 # Business logic
│   │   ├── NotificationService.php
│   │   ├── CartService.php
│   │   ├── GuestOrderService.php
│   │   └── ... (20+ more services)
│   └── Pages/                    # Page controllers (new)
├── admin/                        # Admin panel pages
├── customer/                     # Customer portal pages
├── staff/                        # Staff panel pages
├── supplier/                     # Supplier portal pages
├── includes/                     # Shared utilities & wrappers
├── assets/                       # CSS, images, fonts
├── *.php                         # Root-level public pages
├── *.sql                         # Database schemas & migrations
├── config.php                    # Main configuration
├── README.md                     # User guide
├── ARCHITECTURE.md               # This file
└── learning_journal.md           # Development notes
```

## Glossary

- **Service** - Encapsulates business logic and coordinates between models
- **Model** - Represents data structure and handles database interactions
- **Controller** - Coordinates request, service, and response (implicit in pages)
- **Repository** - Provides data access abstraction (implicit in Models)
- **DTO** - Data Transfer Object for passing data between layers (implicit in arrays)
- **Dependency Injection** - Passing dependencies through constructor
- **SOLID** - Set of OOP principles: Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion

