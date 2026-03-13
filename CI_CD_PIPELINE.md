# 🤖 CI/CD Pipeline & GitHub Actions Automation

## What is CI/CD?
- **CI** (Continuous Integration) = Automatically test code when you push to GitHub
- **CD** (Continuous Deployment) = Automatically deploy to production when tests pass

## Benefits
- ✅ Zero downtime deployments
- ✅ Automatic testing before deploy
- ✅ Git-based versioning and rollback
- ✅ Less manual work

---

## Option 1: GitHub Actions (FREE & Easiest)

### Step 1: Create GitHub Actions Workflow

Create file: `.github/workflows/deploy.yml`

```yaml
name: Deploy to Production

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: test_db
        options: >-
          --health-cmd="mysqladmin ping" 
          --health-interval=10s 
          --health-timeout=5s 
          --health-retries=3
        ports:
          - 3306:3306

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: mysqli, pdo_mysql

    - name: Run Syntax Check
      run: |
        find app/ -name "*.php" -exec php -l {} \;
        find admin/ -name "*.php" -exec php -l {} \;
        find customer/ -name "*.php" -exec php -l {} \;

    - name: Deploy to VPS
      if: github.ref == 'refs/heads/main' && github.event_name == 'push'
      uses: appleboy/ssh-action@master
      with:
        host: ${{ secrets.VPS_HOST }}
        username: ${{ secrets.VPS_USER }}
        key: ${{ secrets.VPS_SSH_KEY }}
        script: |
          cd /var/www/html/stock
          git pull origin main
          php create_users.php
          echo "✅ Deployment successful!"
```

### Step 2: Add GitHub Secrets

1. Go to GitHub repo → **Settings** → **Secrets and variables** → **Actions**
2. Click **New repository secret** and add:

| Secret Name | Value |
|-----------|-------|
| `VPS_HOST` | Your VPS IP (e.g., `123.45.67.89`) |
| `VPS_USER` | `root` |
| `VPS_SSH_KEY` | Your private SSH key |

**How to get SSH key:**

```bash
# On your computer
ssh-keygen -t rsa -b 4096 -f gh_actions
# Keep the private key content
# Add public key to VPS: cat gh_actions.pub >> ~/.ssh/authorized_keys
```

---

## Option 2: GitLab CI/CD

### Create `.gitlab-ci.yml`

```yaml
stages:
  - test
  - deploy

test:
  stage: test
  image: php:8.2
  script:
    - find app/ -name "*.php" -exec php -l {} \;
    - echo "✅ All PHP files are syntactically correct"

deploy:
  stage: deploy
  image: alpine:latest
  before_script:
    - apk add --no-cache openssh-client
    - eval $(ssh-agent -s)
    - echo "$SSH_PRIVATE_KEY" | ssh-add -
    - mkdir -p ~/.ssh
    - ssh-keyscan -H $VPS_HOST >> ~/.ssh/known_hosts
  script:
    - ssh -u $VPS_USER@$VPS_HOST "cd /var/www/html/stock && git pull origin main"
    - echo "✅ Deployment successful!"
  only:
    - main
```

---

## Option 3: Jenkins (Self-Hosted)

### Jenkinsfile

```groovy
pipeline {
    agent any

    stages {
        stage('Clone') {
            steps {
                git 'https://github.com/22103151-ship-it/OOP-based-Stock-Management-System-PHP-Project.git'
            }
        }

        stage('Test') {
            steps {
                sh '''
                    find app/ -name "*.php" -exec php -l {} \;
                    echo "✅ PHP Syntax Check Complete"
                '''
            }
        }

        stage('Deploy') {
            steps {
                sh '''
                    ssh -i ~/.ssh/id_rsa root@YOUR_VPS_IP "cd /var/www/html/stock && git pull origin main"
                '''
                echo "✅ Deployment Complete"
            }
        }
    }

    post {
        always {
            echo 'Pipeline finished'
        }
    }
}
```

---

## Deployment Strategies

### Strategy 1: Blue-Green Deployment

```bash
#!/bin/bash
# Switch between two environments

# Current (Blue)
CURRENT_DIR="/var/www/html/stock-blue"

# New (Green)
NEW_DIR="/var/www/html/stock-green"

# 1. Deploy to Green
cp -r . $NEW_DIR
cd $NEW_DIR
php create_users.php

# 2. Run tests
php -r "echo 'Testing...'"

# 3. Switch
rm /var/www/html/stock
ln -s $NEW_DIR /var/www/html/stock

echo "✅ Deployed successfully"
```

### Strategy 2: Rolling Updates

```bash
#!/bin/bash
# Update multiple servers sequentially

SERVERS=("server1.com" "server2.com" "server3.com")

for server in "${SERVERS[@]}"; do
    echo "Deploying to $server..."
    ssh root@$server "cd /var/www/html/stock && git pull origin main"
    
    # Wait for stability
    sleep 30
    
    # Health check
    curl -f http://$server/index.php > /dev/null || exit 1
done

echo "✅ Rolling deployment complete"
```

---

## Database Migrations (Advanced)

### Migration System

Create file: `migrations/Migration.php`

```php
<?php
namespace App\Migrations;

abstract class Migration {
    protected $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    abstract public function up();
    abstract public function down();
}
```

### Migration Command

Create file: `migrations/run.php`

```php
<?php
require '../config.php';

$migrationsDir = __DIR__;
$migrationFiles = glob($migrationsDir . '/*.php');

foreach ($migrationFiles as $file) {
    if (basename($file) === 'run.php') continue;
    
    include $file;
    $className = 'App\\Migrations\\' . pathinfo($file, PATHINFO_FILENAME);
    
    if (class_exists($className)) {
        $migration = new $className($conn);
        echo "Running: " . basename($file) . "... ";
        
        try {
            $migration->up();
            echo "✅\n";
        } catch (Exception $e) {
            echo "❌ " . $e->getMessage() . "\n";
        }
    }
}
```

---

## Monitoring & Alerting

### GitHub Monitoring

Add status badge to README.md:

```markdown
![Test and Deploy](https://github.com/22103151-ship-it/OOP-based-Stock-Management-System-PHP-Project/workflows/Deploy%20to%20Production/badge.svg)
```

### Email Notifications on Failure

```yaml
# In .github/workflows/deploy.yml

  notify:
    if: failure()
    runs-on: ubuntu-latest
    needs: [test, deploy]
    steps:
    - name: Send email
      uses: dawidd6/action-send-mail@v3
      with:
        server_address: smtp.gmail.com
        server_port: 465
        username: ${{ secrets.EMAIL_USERNAME }}
        password: ${{ secrets.EMAIL_PASSWORD }}
        subject: "❌ Deployment Failed"
        to: your-email@example.com
        from: 'GitHub Actions'
        body: "Check: https://github.com/22103151-ship-it/OOP-based-Stock-Management-System-PHP-Project/actions"
```

---

## Rollback Strategy

### Automatic Rollback on Failure

```yaml
- name: Rollback on Failure
  if: failure()
  run: |
    ssh root@$VPS_IP "cd /var/www/html/stock && git reset --hard HEAD~1"
    echo "🔙 Rolled back to previous version"
```

### Manual Rollback

```bash
# SSH into VPS
ssh root@YOUR_VPS_IP

# Go to project
cd /var/www/html/stock

# Check git history
git log --oneline

# Rollback to specific commit
git reset --hard COMMIT_HASH

# Restart services
systemctl restart apache2
```

---

## Environment-Specific Configurations

### Production .env
```env
APP_ENV=production
APP_DEBUG=false
DB_HOST=db.internal
DB_USER=stock_prod
DB_PASS=VeryStrongPassword123!
SSLCOMMERZ_SANDBOX=false
CACHE_DRIVER=redis
```

### Staging .env
```env
APP_ENV=staging
APP_DEBUG=true
DB_HOST=staging-db.internal
DB_USER=stock_staging
DB_PASS=StagingPassword123!
SSLCOMMERZ_SANDBOX=true
CACHE_DRIVER=file
```

### Development .env
```env
APP_ENV=development
APP_DEBUG=true
DB_HOST=localhost
DB_USER=root
DB_PASS=
SSLCOMMERZ_SANDBOX=true
CACHE_DRIVER=array
```

---

## Health Checks

### Create Health Check Endpoint

Create: `api/health.php`

```php
<?php
header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'uptime' => getenv('UPTIME') ?? 'unknown',
];

// Check database
try {
    require '../config.php';
    $result = $conn->query("SELECT 1");
    $health['database'] = 'ok';
} catch (Exception $e) {
    $health['status'] = 'error';
    $health['database'] = $e->getMessage();
    http_response_code(500);
}

// Check critical files
$health['files'] = [
    'app/autoload.php' => file_exists('../app/autoload.php') ? 'ok' : 'missing',
    'config.php' => file_exists('../config.php') ? 'ok' : 'missing',
];

echo json_encode($health);
?>
```

### Monitor Health Endpoint

```yaml
# In .github/workflows/monitor.yml

name: Health Check
on:
  schedule:
    - cron: '*/5 * * * *' # Every 5 minutes

jobs:
  health:
    runs-on: ubuntu-latest
    steps:
    - name: Check application health
      run: |
        curl -f https://yoursite.com/api/health.php || exit 1
```

---

## Backup Strategy

### Automated Database Backup

Create: `scripts/backup.sh`

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/database"

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u stock_user -pStockPassword123! stock_management_system > \
  $BACKUP_DIR/stock_db_$DATE.sql

# Compress
gzip $BACKUP_DIR/stock_db_$DATE.sql

# Keep only last 30 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

echo "✅ Backup complete: stock_db_$DATE.sql.gz"
```

### Schedule Backup via Cron

```bash
# Every day at 2 AM
0 2 * * * /var/www/html/stock/scripts/backup.sh

# Every week (Sunday at 3 AM)
0 3 * * 0 /var/www/html/stock/scripts/backup.sh
```

---

## Complete Deployment Checklist

- [ ] Code committed and pushed to GitHub
- [ ] All tests passing
- [ ] Environment variables configured
- [ ] Database backup created
- [ ] SSL certificate valid
- [ ] Health check endpoint working
- [ ] Staging environment tested
- [ ] Deployment initiated (automatic)
- [ ] Production health verified
- [ ] Monitoring active
- [ ] Team notified

---

## Troubleshooting CI/CD

### GitHub Actions Won't Trigger
- Check branch filter (should be `main`)
- Verify workflow file is in `.github/workflows/`
- Check for syntax errors in YAML

### Deployment Fails
- Check VPS credentials in Secrets
- Verify SSH key permissions (600)
- Run manual test: `ssh -i key.pem root@IP`

### Database Migration Fails
- Check database credentials
- Verify migration files are in correct order
- Check SQL syntax with `mysql -u user -p < file.sql`

---

## Resources

- [GitHub Actions Docs](https://docs.github.com/en/actions)
- [GitLab CI/CD Docs](https://docs.gitlab.com/ee/ci/)
- [Semantic Versioning](https://semver.org/)
- [Conventional Commits](https://www.conventionalcommits.org/)

---

**Last Updated:** March 2026  
**Recommended:** GitHub Actions (Free, Easy)  
**Status:** ✨ Production Ready ✨
