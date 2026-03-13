#!/bin/bash

# ===================================
# Stock Management System Setup Script
# For Linux/Mac VPS Servers
# ===================================

set -e  # Exit on error

echo "🚀 Starting Stock Management System Setup..."

# Check if running as root
if [ "$(id -u)" != "0" ]; then
   echo "❌ This script must be run as root"
   exit 1
fi

# Get server details
echo "📍 Detected OS: $(uname -s)"

# ===================================
# Step 1: Update System
# ===================================
echo ""
echo "📦 Updating system packages..."
apt-get update
apt-get upgrade -y

# ===================================
# Step 2: Install Dependencies
# ===================================
echo ""
echo "📦 Installing Apache, PHP, MySQL..."
apt-get install -y \
    apache2 \
    mysql-server \
    php \
    php-mysql \
    php-curl \
    php-json \
    php-cli \
    git \
    curl \
    wget \
    nano

# ===================================
# Step 3: Configure Apache
# ===================================
echo ""
echo "⚙️  Configuring Apache..."
a2enmod rewrite
a2enmod headers
a2enmod deflate

# ===================================
# Step 4: Start Services
# ===================================
echo ""
echo "▶️  Starting services..."
systemctl start apache2
systemctl start mysql
systemctl enable apache2
systemctl enable mysql

# ===================================
# Step 5: Clone Repository
# ===================================
echo ""
echo "📥 Cloning project from GitHub..."
cd /var/www/html
rm -rf *

git clone https://github.com/22103151-ship-it/OOP-based-Stock-Management-System-PHP-Project.git
mv OOP-based-Stock-Management-System-PHP-Project/* .
rm -rf OOP-based-Stock-Management-System-PHP-Project

# ===================================
# Step 6: Setup MySQL Database
# ===================================
echo ""
echo "🗄️  Setting up MySQL database..."

mysql -e "CREATE DATABASE IF NOT EXISTS stock_management_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

mysql -e "CREATE USER IF NOT EXISTS 'stock_user'@'localhost' IDENTIFIED BY 'StockPassword123!';"
mysql -e "GRANT ALL PRIVILEGES ON stock_management_system.* TO 'stock_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# ===================================
# Step 7: Import Database Schemas
# ===================================
echo ""
echo "📊 Importing database schemas..."

mysql stock_management_system < stock_management_system.sql
mysql stock_management_system < customer_schema_updates.sql
mysql stock_management_system < guest_membership_schema.sql
mysql stock_management_system < notification_dots_schema.sql
mysql stock_management_system < add_product_images.sql

# ===================================
# Step 8: Configure Apache VHost
# ===================================
echo ""
echo "📝 Configuring Apache virtual host..."

cat > /etc/apache2/sites-available/stock.conf << 'VHOST'
<VirtualHost *:80>
    ServerName stock.local
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/stock_error.log
    CustomLog ${APACHE_LOG_DIR}/stock_access.log combined

    # Enable gzip
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
    </IfModule>

    # Enable caching
    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType image/* "access plus 1 year"
        ExpiresByType text/css "access plus 30 days"
        ExpiresByType application/javascript "access plus 30 days"
    </IfModule>
</VirtualHost>
VHOST

a2ensite stock.conf
a2dissite 000-default.conf 2>/dev/null || true

# ===================================
# Step 9: Set Permissions
# ===================================
echo ""
echo "🔐 Setting file permissions..."

chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 777 /var/www/html/tmp 2>/dev/null || mkdir -p /var/www/html/tmp && chmod 777 /var/www/html/tmp
chmod -R 777 /var/www/html/logs 2>/dev/null || mkdir -p /var/www/html/logs && chmod 777 /var/www/html/logs

# ===================================
# Step 10: Create config.php
# ===================================
echo ""
echo "📄 Creating config.php..."

cat > /var/www/html/config.php << 'CONFIG'
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'stock_user');
define('DB_PASS', 'StockPassword123!');
define('DB_NAME', 'stock_management_system');

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

// Error reporting
error_reporting(0);
ini_set('display_errors', 0);

// Session configuration
ini_set('session.gc_maxlifetime', 1440);
ini_set('session.cookie_lifetime', 0);
?>
CONFIG

# ===================================
# Step 11: Reload Apache
# ===================================
echo ""
echo "🔄 Reloading Apache..."
systemctl reload apache2

# ===================================
# Step 12: Create Backup Script
# ===================================
echo ""
echo "💾 Creating backup script..."

mkdir -p /var/www/html/backups

cat > /var/www/html/scripts/backup.sh << 'BACKUP'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/www/html/backups"

mkdir -p $BACKUP_DIR

mysqldump -u stock_user -pStockPassword123! stock_management_system > \
  $BACKUP_DIR/stock_db_$DATE.sql

gzip $BACKUP_DIR/stock_db_$DATE.sql

# Keep only last 30 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

echo "✅ Backup complete: stock_db_$DATE.sql.gz"
BACKUP

chmod +x /var/www/html/scripts/backup.sh

# ===================================
# Step 13: Setup SSL (Optional)
# ===================================
echo ""
read -p "Do you want to setup Let's Encrypt SSL? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    apt-get install -y certbot python3-certbot-apache
    
    read -p "Enter your domain name: " DOMAIN
    certbot --apache -d $DOMAIN
    
    # Create renewal cron job
    echo "0 3 * * * /usr/bin/certbot renew --quiet" | crontab -
    
    echo "✅ SSL certificate installed"
fi

# ===================================
# Step 14: Setup Cron Backup
# ===================================
echo ""
echo "⏰ Setting up daily database backups..."
(crontab -l 2>/dev/null; echo "0 2 * * * /var/www/html/scripts/backup.sh") | crontab -

# ===================================
# Success Message
# ===================================
echo ""
echo "╔════════════════════════════════════════════════╗"
echo "║     ✅ Setup Complete!                         ║"
echo "╚════════════════════════════════════════════════╝"
echo ""
echo "🌐 Your site is ready at:"
echo "   http://$(hostname -I | awk '{print $1}')"
echo ""
echo "📝 Default login credentials:"
echo "   Email: admin@stock.com"
echo "   Password: 123"
echo ""
echo "📊 phpMyAdmin:"
echo "   Not installed (install separately if needed)"
echo ""
echo "💾 Backups:"
echo "   Location: /var/www/html/backups"
echo "   Schedule: Daily at 2:00 AM"
echo ""
echo "📖 Next steps:"
echo "   1. Configure domain DNS pointing to this server"
echo "   2. Setup SSL certificate (certbot)"
echo "   3. Update config.php if needed"
echo "   4. Change admin password"
echo "   5. Configure payment gateway credentials"
echo ""
echo "📞 Support: https://github.com/22103151-ship-it"
echo ""
