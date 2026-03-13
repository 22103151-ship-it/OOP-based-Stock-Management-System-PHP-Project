# 🚀 Complete Deployment Guide - All Options

## 📚 Table of Contents
1. [Option A: Web Hosting (Free/Cheap)](#option-a-web-hosting) ⭐ Easiest
2. [Option B: DigitalOcean VPS](#option-b-digitalocean-vps) 🔥 Professional
3. [Option C: Docker Deployment](#option-c-docker-deployment) 🐳 Advanced
4. [Comparison Table](#comparison-table)
5. [Post-Deployment Checklist](#post-deployment-checklist)

---

## Option A: Web Hosting (Free/Cheap) ⭐ EASIEST

### Prerequisites
- GitHub account (✅ You have it)
- Free hosting account (create below)
- FTP client (FileZilla - free)

### Step 1: Create Free Hosting Account

**Choose ONE:**

#### **InfinityFree** (Recommended - Most reliable)
1. Go to [infinityfree.net](https://infinityfree.net)
2. Click **Sign Up** → Fill form
3. Verify email
4. Get free domain: `yoursite.infinityfreeapp.com`

#### **000webhost** (Alternative - Faster support)
1. Go to [000webhost.com](https://000webhost.com)
2. Click **Sign Up** → Fill form
3. Verify email
4. Get free domain: `yoursite.000webhostapp.com`

### Step 2: Access Control Panel

After signup, go to **Control Panel**:
- Find **File Manager** button
- Find **MySQL** or **Database** section
- Find **FTP** credentials

### Step 3: Create MySQL Database

**In Control Panel:**
1. Go to **MySQL** / **Databases**
2. Click **Create Database**
3. Name it: `stock_management_system`
4. Note down: **Database name**, **Username**, **Password**
5. Database host is usually: `localhost`

### Step 4: Download Project Files

You have two options:

**Option A1: Clone from GitHub**
```bash
# Go to any folder and run:
git clone https://github.com/22103151-ship-it/OOP-based-Stock-Management-System-PHP-Project.git
cd OOP-based-Stock-Management-System-PHP-Project
```

**Option A2: Download as ZIP**
1. Go to [GitHub repo](https://github.com/22103151-ship-it/OOP-based-Stock-Management-System-PHP-Project)
2. Click **Code** → **Download ZIP**
3. Extract to a folder

### Step 5: Update config.php

Edit the file with new database credentials:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');           // Usually: localhost
define('DB_USER', 'your_db_username');    // From Step 3
define('DB_PASS', 'your_db_password');    // From Step 3
define('DB_NAME', 'stock_management_system');

// Rest stays the same...
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
require_once __DIR__ . '/app/autoload.php';
?>
```

### Step 6: Download & Install FileZilla

1. Go to [filezilla-project.org](https://filezilla-project.org/download.php)
2. Download **FileZilla Client** (free)
3. Install it

### Step 7: Connect via FTP

**Get FTP credentials from Control Panel:**
1. Find **FTP Accounts** or **FTP Manager**
2. Create FTP account or use default
3. Note: **Host**, **Username**, **Password**

**In FileZilla:**
1. Go to **File** → **Site Manager**
2. Click **New Site**
3. Fill in:
   - **Host:** (from FTP credentials)
   - **Username:** (from FTP credentials)
   - **Password:** (from FTP credentials)
4. Click **Connect**

### Step 8: Upload Project Files

**In FileZilla:**
1. Left side: Navigate to project folder (with README.md visible)
2. Right side: Go to `public_html` folder
3. Select all files (Ctrl+A in left panel)
4. Drag to right panel OR right-click **Upload**
5. Wait until done (check status bar)

### Step 9: Import Database Schema

**In Control Panel → phpMyAdmin:**
1. Select database: `stock_management_system`
2. Click **Import** tab
3. Click **Choose File**
4. Upload these in order:
   - `stock_management_system.sql`
   - `customer_schema_updates.sql`
   - `guest_membership_schema.sql`
   - `notification_dots_schema.sql`
   - `add_product_images.sql`
5. Click **Go** after each

### Step 10: Test & Go Live!

1. Open browser
2. Go to: `https://yoursite.infinityfreeapp.com`
3. You should see login page
4. Login with: 
   - Email: `admin@stock.com`
   - Password: `123`

**✅ YOU'RE LIVE!**

### Troubleshooting (Web Hosting)

**"Database connection failed"**
- Update `config.php` with correct credentials
- Check database name, username, password are exact

**"Class not found: App\*"**
- Make sure `app/` folder is uploaded
- Check folder structure matches local

**"Blank page / 500 error"**
- Enable error logging in `config.php`:
  ```php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ```

**SLOW PERFORMANCE**
- This is normal for free hosting
- Upgrade to paid plan if needed

---

## Option B: DigitalOcean VPS 🔥 PROFESSIONAL

### Prerequisites
- GitHub account (✅ You have)
- DigitalOcean account ([signup here](https://digitalocean.com))
- Terminal/Command line knowledge
- SSH client ([PuTTY](https://www.putty.org/) on Windows, or use terminal)

### Cost
- **$6/month** for basic droplet (5GB SSD, 1GB RAM)
- **$12/month** for better performance

### Step 1: Create DigitalOcean Account & Droplet

1. Go to [DigitalOcean](https://digitalocean.com) → Sign up
2. Create project: Name it "Stock Management"
3. Click **Create** → **Droplets**
4. Choose:
   - **Region:** Singapore or closest to your users
   - **Image:** Ubuntu 22.04 x64
   - **Size:** $6/month (1GB RAM is enough)
   - **Auth:** Add SSH key (safer) or password
5. Click **Create Droplet**
6. Wait 2-3 minutes for setup
7. Copy your **Droplet IP** (e.g., `123.45.67.89`)

### Step 2: Connect via SSH

**On Windows (using Terminal/PowerShell):**
```powershell
ssh root@YOUR_DROPLET_IP
# Enter password when asked
```

**On Mac/Linux:**
```bash
ssh root@YOUR_DROPLET_IP
```

### Step 3: Run Automated Setup Script

Create a file called `setup.sh` with this content:

```bash
#!/bin/bash
set -e

echo "🚀 Installing dependencies..."
apt update
apt install -y apache2 mysql-server php php-mysql php-curl php-json git

echo "🔧 Configuring Apache..."
a2enmod rewrite
systemctl restart apache2

echo "📦 Cloning project..."
cd /var/www/html
rm -rf *
git clone https://github.com/22103151-ship-it/OOP-based-Stock-Management-System-PHP-Project.git
mv OOP-based-Stock-Management-System-PHP-Project/* .
rm -rf OOP-based-Stock-Management-System-PHP-Project

echo "🗄️ Setting up MySQL..."
mysql -e "CREATE DATABASE IF NOT EXISTS stock_management_system CHARACTER SET utf8mb4;"
mysql stock_management_system < stock_management_system.sql
mysql stock_management_system < customer_schema_updates.sql
mysql stock_management_system < guest_membership_schema.sql
mysql stock_management_system < notification_dots_schema.sql
mysql stock_management_system < add_product_images.sql

echo "🔐 Creating MySQL user..."
mysql -e "CREATE USER IF NOT EXISTS 'stock_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';"
mysql -e "GRANT ALL PRIVILEGES ON stock_management_system.* TO 'stock_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo "📝 Updating config.php..."
cat > /var/www/html/config.php << 'EOF'
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'stock_user');
define('DB_PASS', 'StrongPassword123!');
define('DB_NAME', 'stock_management_system');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
require_once __DIR__ . '/app/autoload.php';
?>
EOF

echo "🔒 Setting permissions..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

echo "✅ Setup complete!"
echo "Your site is live at: http://YOUR_DROPLET_IP"
```

**Run the script:**
```bash
# Paste the script content, save as setup.sh
curl -fsSL https://raw.githubusercontent.com/YOUR_USERNAME/OOP-based-Stock-Management-System-PHP-Project/main/setup.sh | bash

# OR manually run each command above
```

### Step 4: Add SSL Certificate (HTTPS)

```bash
apt install -y certbot python3-certbot-apache
certbot --apache -d yourdomain.com
# Follow prompts
```

### Step 5: Set Up Domain

1. Go to your domain registrar (GoDaddy, Namecheap, etc)
2. Go to **DNS Settings**
3. Create **A Record:**
   - Name: `@` (or blank)
   - Value: `YOUR_DROPLET_IP`
4. Wait 24 hours for DNS to propagate

### Step 6: Test

```
http://YOUR_DROPLET_IP/index.php
# Should see login page
```

---

## Option C: Docker Deployment 🐳 ADVANCED

### Prerequisites
- Docker installed ([Download](https://www.docker.com/products/docker-desktop))
- Docker Compose
- GitHub account (✅ You have)

### Step 1: Create docker-compose.yml

See [DOCKER_SETUP.md](./DOCKER_SETUP.md) for complete file

```bash
cd c:\Users\ASUS\OneDrive\Desktop\New\ folder\ \(3\)\stock
docker-compose up -d
```

This automatically:
- Creates PHP container
- Creates MySQL container
- Imports all databases
- Sets up networking

### Step 2: Access Application

```
http://localhost:8000
```

### Step 3: Deploy to Production

See [CI_CD_PIPELINE.md](./CI_CD_PIPELINE.md) for GitHub Actions automation

---

## Comparison Table

| Feature | Web Hosting | DigitalOcean | Docker |
|---------|----------|----------------|--------|
| **Cost** | Free-$5/mo | $6/mo | Free (self-hosted) |
| **Ease of Setup** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ |
| **Performance** | Slow | Fast ⚡ | Very Fast ⚡⚡ |
| **Can use own domain** | ❌ No | ✅ Yes | ✅ Yes |
| **Full control** | ❌ Limited | ✅ Yes | ✅ Yes |
| **Uptime SLA** | 90% | 99.9% | 99.9% |
| **Best for** | Testing | Production | Dev/Production |
| **Who should use** | Beginners | Everyone | Developers |

---

## Post-Deployment Checklist

### ✅ Security

- [ ] Change admin password: `admin@stock.com` → Set new password
  ```sql
  UPDATE users SET password_hash = PASSWORD('YourNewPassword') WHERE email = 'admin@stock.com';
  ```
- [ ] Remove test accounts:
  ```sql
  DELETE FROM users WHERE email LIKE 'test%' OR email LIKE 'demo%';
  ```
- [ ] Update `config.php` to not show errors in production
  ```php
  error_reporting(0);
  ini_set('display_errors', 0);
  ```
- [ ] Set up HTTPS/SSL (covered above for DigitalOcean)
- [ ] Backup your database regularly

### ✅ Functionality

- [ ] Test login with admin account
- [ ] Test customer registration
- [ ] Test guest order flow
- [ ] Test payment gateway (SSLCommerz)
- [ ] Test notifications
- [ ] Test product search
- [ ] Test all admin features

### ✅ Performance

- [ ] Check page load time (should be < 2 seconds)
- [ ] Enable gzip compression in `.htaccess`:
  ```apache
  <IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
  </IfModule>
  ```
- [ ] Optimize images in `assets/images/`
- [ ] Cache static assets (CSS, JS)

### ✅ Monitoring

- [ ] Set up error logging
- [ ] Monitor database size
- [ ] Check server uptime
- [ ] Monitor traffic patterns

---

## File Structure for Deployment

```
stock/
├── config.php                      # ⭐ UPDATE WITH YOUR DATABASE CREDENTIALS
├── README.md
├── DEPLOYMENT_GUIDE.md             # THIS FILE
├── DOCKER_SETUP.md                 # ⭐ For Docker deployment
├── CI_CD_PIPELINE.md               # ⭐ For GitHub Actions
├── .env.example                    # ⭐ Copy to .env and fill
├── .htaccess                       # ⭐ For Apache (rewrite rules)
├── docker-compose.yml              # ⭐ For Docker
├── Dockerfile                      # ⭐ For Docker
├── app/
├── admin/
├── customer/
├── staff/
├── supplier/
└── ... (rest of project)
```

---

## Quick Reference Commands

### GitHub (Always do before deployment)
```bash
git add .
git commit -m "Deploy to production"
git push origin main
```

### DigitalOcean Common Commands
```bash
# SSH into droplet
ssh root@YOUR_IP

# Check Apache status
systemctl status apache2

# Check MySQL status
systemctl status mysql

# View logs
tail -f /var/log/apache2/error.log

# Restart services
systemctl restart apache2
systemctl restart mysql
```

### Docker Commands
```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f

# Execute command in container
docker-compose exec app bash

# Clean up
docker system prune
```

---

## Troubleshooting by Option

### Web Hosting Issues
- Database connection → Check credentials in `config.php`
- File permissions → Upload .htaccess file
- Class not found → Check app/ folder is uploaded

### DigitalOcean Issues
- Connection refused → Check firewall, allow port 80/443
- Database error → Check MySQL is running: `systemctl status mysql`
- Slow performance → Check droplet specs, consider upgrade

### Docker Issues
- Port already in use → Change port in docker-compose.yml
- Container won't start → Check `docker-compose logs`
- Database permission denied → Check MySQL user permissions

---

## Next Steps

1. **Choose your option** (A, B, or C)
2. **Follow the step-by-step guide** for that option
3. **Test everything** using the checklist
4. **Share your live URL** with the world! 🎉

---

**Need help with a specific step?** Each option has detailed files:
- [WEB_HOSTING_DETAILED.md](./WEB_HOSTING_DETAILED.md) - Full step-by-step with screenshots
- [DOCKER_SETUP.md](./DOCKER_SETUP.md) - Complete Docker configuration
- [CI_CD_PIPELINE.md](./CI_CD_PIPELINE.md) - GitHub Actions automation

---

**Last Updated:** March 2026  
**Project Status:** ✨ Ready for Production ✨
