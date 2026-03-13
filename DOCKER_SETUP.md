# 🐳 Docker Setup & Deployment Guide

## What is Docker?
Docker packages your entire application (PHP, MySQL, dependencies) into containers that run the same everywhere - your laptop, a VPS, AWS, etc.

## Prerequisites
- Docker Desktop installed ([download here](https://www.docker.com/products/docker-desktop))
- Basic command line knowledge
- 4GB RAM available

## ⚡ Quick Start (5 minutes)

### Step 1: Navigate to Project
```powershell
cd c:\Users\ASUS\OneDrive\Desktop\New\ folder\ \(3\)\stock
```

### Step 2: Start Containers
```bash
docker-compose up -d
```

### Step 3: Import Database
```bash
docker-compose exec db mysql -u stock_user -pStockPassword123! stock_management_system < stock_management_system.sql
docker-compose exec db mysql -u stock_user -pStockPassword123! stock_management_system < customer_schema_updates.sql
docker-compose exec db mysql -u stock_user -pStockPassword123! stock_management_system < guest_membership_schema.sql
docker-compose exec db mysql -u stock_user -pStockPassword123! stock_management_system < notification_dots_schema.sql
docker-compose exec db mysql -u stock_user -pStockPassword123! stock_management_system < add_product_images.sql
```

### Step 4: Visit Your Site
```
http://localhost:8000
```

**Login:** admin@stock.com / 123

---

## Files Needed

### File 1: docker-compose.yml
Location: `stock/docker-compose.yml`

This file is already created - see DOCKER_COMPOSE_TEMPLATE below

### File 2: Dockerfile
Location: `stock/Dockerfile`

This file is already created - see DOCKERFILE_TEMPLATE below

### File 3: .env
Location: `stock/.env`

This file is already created - see ENV_TEMPLATE below

---

## 📄 DOCKER_COMPOSE_TEMPLATE

```yaml
version: '3.8'

services:
  # PHP Application Container
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: stock_app
    ports:
      - "8000:80"
    volumes:
      - .:/var/www/html
    environment:
      - DB_HOST=db
      - DB_USER=stock_user
      - DB_PASS=StockPassword123!
      - DB_NAME=stock_management_system
      - PHP_MEMORY_LIMIT=256M
      - MAX_EXECUTION_TIME=300
    depends_on:
      - db
    networks:
      - stock_network
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 3

  # MySQL Database Container
  db:
    image: mysql:8.0
    container_name: stock_db
    environment:
      MYSQL_ROOT_PASSWORD: RootPassword123!
      MYSQL_DATABASE: stock_management_system
      MYSQL_USER: stock_user
      MYSQL_PASSWORD: StockPassword123!
    ports:
      - "3306:3306"
    volumes:
      - db_data:/var/lib/mysql
      - ./stock_management_system.sql:/docker-entrypoint-initdb.d/1_stock_management_system.sql
      - ./customer_schema_updates.sql:/docker-entrypoint-initdb.d/2_customer_schema_updates.sql
      - ./guest_membership_schema.sql:/docker-entrypoint-initdb.d/3_guest_membership_schema.sql
      - ./notification_dots_schema.sql:/docker-entrypoint-initdb.d/4_notification_dots_schema.sql
      - ./add_product_images.sql:/docker-entrypoint-initdb.d/5_add_product_images.sql
    networks:
      - stock_network
    restart: unless-stopped

  # phpMyAdmin for Database Management (Optional)
  phpmyadmin:
    image: phpmyadmin:latest
    container_name: stock_phpmyadmin
    environment:
      PMA_HOST: db
      PMA_USER: stock_user
      PMA_PASSWORD: StockPassword123!
      PMA_ROOT_PASSWORD: RootPassword123!
    ports:
      - "8080:80"
    depends_on:
      - db
    networks:
      - stock_network
    restart: unless-stopped

volumes:
  db_data:
    driver: local

networks:
  stock_network:
    driver: bridge
```

---

## 📄 DOCKERFILE_TEMPLATE

```dockerfile
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    mysql-client \
    git \
    curl \
    wget \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    mysqli \
    pdo_mysql \
    json \
    curl \
    && docker-php-ext-enable \
    mysqli \
    pdo_mysql \
    curl

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
```

---

## 📄 ENV_TEMPLATE

Create file: `stock/.env`

```env
# Database Configuration
DB_HOST=db
DB_USER=stock_user
DB_PASS=StockPassword123!
DB_NAME=stock_management_system
DB_ROOT_PASS=RootPassword123!

# Application
APP_NAME=Stock Management System
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8000

# PHP Configuration
PHP_MEMORY_LIMIT=256M
MAX_EXECUTION_TIME=300

# SSLCOMMERZ Payment Gateway
SSLCOMMERZ_STORE_ID=your_store_id
SSLCOMMERZ_STORE_PASS=your_store_password
SSLCOMMERZ_SANDBOX=true

# Email Configuration (Future)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@stock.local
MAIL_FROM_NAME="Stock Management System"
```

---

## 📄 CONFIG.PHP (Docker Version)

Update your `config.php` to support both Docker and local development:

```php
<?php
// Load environment variables
$dotenv_path = __DIR__ . '/.env';
if (file_exists($dotenv_path)) {
    $lines = file($dotenv_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'stock_management_system');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Set charset
$conn->set_charset('utf8mb4');

// Load autoloader
require_once __DIR__ . '/app/autoload.php';

// Set error reporting based on environment
if ($_ENV['APP_DEBUG'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>
```

---

## 🔧 Common Docker Commands

### Container Management
```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# Restart containers
docker-compose restart

# View container status
docker-compose ps

# View logs
docker-compose logs -f app    # App logs
docker-compose logs -f db     # Database logs

# Clean everything up
docker-compose down -v        # Remove volumes too
```

### Database Management
```bash
# Access MySQL CLI
docker-compose exec db mysql -u stock_user -pStockPassword123! stock_management_system

# Backup database
docker-compose exec db mysqldump -u stock_user -pStockPassword123! stock_management_system > backup.sql

# Restore database
docker-compose exec db mysql -u stock_user -pStockPassword123! stock_management_system < backup.sql
```

### Application Management
```bash
# Access application container bash
docker-compose exec app bash

# Run PHP command
docker-compose exec app php -v

# Execute script in container
docker-compose exec app php create_users.php
```

### Debugging
```bash
# View full logs
docker-compose logs

# Check container health
docker-compose exec app curl http://localhost/

# Test database connection
docker-compose exec app php -r "
  \$conn = new mysqli('db', 'stock_user', 'StockPassword123!', 'stock_management_system');
  echo \$conn->connect_error ? 'ERROR: ' . \$conn->connect_error : 'Connected!';
"
```

---

## 📊 Accessing Your Services

| Service | URL | Credentials |
|---------|-----|-------------|
| **Application** | http://localhost:8000 | admin@stock.com / 123 |
| **phpMyAdmin** | http://localhost:8080 | stock_user / StockPassword123! |
| **MySQL** | localhost:3306 | stock_user / StockPassword123! |

---

## 🚀 Production Deployment (Docker)

### Deploy to AWS EC2
```bash
# 1. Launch EC2 instance (Ubuntu 22.04)
# 2. SSH into instance
# 3. Install Docker
sudo apt update
sudo apt install -y docker.io docker-compose

# 4. Clone your GitHub repo
git clone https://github.com/22103151-ship-it/OOP-based-Stock-Management-System-PHP-Project.git
cd OOP-based-Stock-Management-System-PHP-Project

# 5. Create .env file
cp .env.example .env
nano .env  # Update with your settings

# 6. Start containers
docker-compose -f docker-compose.prod.yml up -d
```

### Deploy to DigitalOcean App Platform
```bash
# DigitalOcean now supports docker-compose natively
# 1. Connect your GitHub repo
# 2. Select docker-compose.yml
# 3. Deploy
```

### Deploy to Heroku (with Docker)
```bash
# 1. Install Heroku CLI
# 2. Login to Heroku
heroku login

# 3. Create app
heroku create your-app-name

# 4. Push to Heroku
git push heroku main
```

---

## 🔒 Production Security Checklist

- [ ] Change all default passwords in `.env`
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Use HTTPS (SSL certificate)
- [ ] Set strong database root password
- [ ] Regular database backups
- [ ] Monitor container logs
- [ ] Keep Docker images updated

### Production docker-compose.yml
See [CI_CD_PIPELINE.md](./CI_CD_PIPELINE.md) for production setup

---

## Volume Management

### Backup Database
```bash
docker-compose exec db mysqldump -u stock_user -pStockPassword123! \
  stock_management_system > database_backup_$(date +%Y%m%d).sql
```

### Restore Database
```bash
docker-compose exec db mysql -u stock_user -pStockPassword123! \
  stock_management_system < database_backup_20260313.sql
```

### Backup Files
```bash
# Backup entire application
docker-compose exec app tar -czf /backup/app_backup_$(date +%Y%m%d).tar.gz /var/www/html
```

---

## Troubleshooting

### Port Already in Use
```bash
# Change port in docker-compose.yml
# Change: "8000:80" to "8001:80"
docker-compose down
docker-compose up -d
```

### Database Connection Error
```bash
# Check if db is running
docker-compose ps

# Check db logs
docker-compose logs db

# Test connection
docker-compose exec app php -r "
  \$conn = new mysqli('db', 'stock_user', 'StockPassword123!', 'stock_management_system');
  echo \$conn->connect_error ?: 'OK';
"
```

### Permission Denied Errors
```bash
# Fix permissions
docker-compose exec app chown -R www-data:www-data /var/www/html
docker-compose exec app chmod -R 755 /var/www/html
```

### Out of Memory
```bash
# Increase Docker memory limit
# Docker Desktop → Settings → Resources → Memory → Increase to 4GB+
```

---

## Performance Optimization

### Enable Caching
```yaml
# In docker-compose.yml, add Redis service:
  redis:
    image: redis:7-alpine
    container_name: stock_cache
    ports:
      - "6379:6379"
    networks:
      - stock_network
```

### Database Optimization
```sql
-- Add indexes
ALTER TABLE customer_orders ADD INDEX idx_customer_id (customer_id);
ALTER TABLE customer_orders ADD INDEX idx_status (status);
ALTER TABLE products ADD INDEX idx_supplier_id (supplier_id);
ALTER TABLE customer_cart ADD INDEX idx_customer_id (customer_id);
```

---

## Next Steps

1. **Create the files** (docker-compose.yml, Dockerfile, .env - instructions below)
2. **Run** `docker-compose up -d`
3. **Test** at http://localhost:8000
4. **For production** - see [CI_CD_PIPELINE.md](./CI_CD_PIPELINE.md)

---

**Last Updated:** March 2026
**Docker Version:** 3.8+
**Status:** ✨ Production Ready ✨
