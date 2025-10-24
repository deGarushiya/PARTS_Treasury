# LGU Real Property Tax System - Testing & Deployment Guide

## Pre-Deployment Testing

### 1. **Database Setup and Migration**

#### Step 1: Run Database Migrations
```bash
# Navigate to Laravel project root
cd /path/to/your/laravel/project

# Run migrations
php artisan migrate

# Seed reference data
php artisan db:seed --class=ReferenceDataSeeder
```

#### Step 2: Test Database Connection
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit
```

### 2. **API Testing**

#### Test Core Endpoints
```bash
# Test taxpayer search
curl -X GET "http://127.0.0.1:8000/api/ownersearch?search=Juan"

# Test tax due calculation
curl -X GET "http://127.0.0.1:8000/api/tax-due/TIN001"

# Test payment creation
curl -X POST "http://127.0.0.1:8000/api/payments" \
  -H "Content-Type: application/json" \
  -d '{
    "LOCAL_TIN": "TIN001",
    "PAYMENTDATE": "2024-01-15",
    "AMOUNT": 1000.00,
    "RECEIPTNO": "OR-2024-000001",
    "PAIDBY": "Juan Dela Cruz",
    "details": [
      {
        "TDNO": "TD001",
        "TAX_YEAR": 2024,
        "DESCRIPTION": "Real Property Tax",
        "AMOUNT": 1000.00
      }
    ]
  }'
```

### 3. **Frontend Testing**

#### Start React Development Server
```bash
# Navigate to React project
cd main

# Install dependencies (if not already done)
npm install

# Start development server
npm start
```

#### Test Frontend Features
1. **Taxpayer Search**: Search for existing taxpayers
2. **Tax Due Retrieval**: Get tax due for selected taxpayers
3. **Payment Processing**: Create test payments
4. **Journal Display**: View payment journal

### 4. **Integration Testing**

#### Test Complete Payment Flow
1. Search for a taxpayer
2. Retrieve their tax due
3. Create a payment
4. Verify payment appears in journal
5. Check database records

## Deployment Options

### Option 1: Local Development (Current Setup)
**Best for**: Development and testing

**Requirements**:
- XAMPP (Apache, MySQL, PHP)
- Node.js and npm
- Git

**Setup**:
```bash
# Clone repository
git clone [your-repo-url]
cd parts-online

# Laravel setup
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=ReferenceDataSeeder

# React setup
cd main
npm install
npm start
```

### Option 2: Shared Hosting
**Best for**: Small to medium LGUs with limited technical resources

**Requirements**:
- Shared hosting with PHP 8.1+ and MySQL
- cPanel or similar control panel

**Steps**:
1. Upload Laravel files to public_html
2. Configure database connection
3. Run migrations via hosting control panel
4. Build React app: `npm run build`
5. Upload built files to web server

### Option 3: VPS/Cloud Server
**Best for**: Larger LGUs with technical staff

**Requirements**:
- VPS with Ubuntu/CentOS
- Nginx or Apache
- MySQL/MariaDB
- PHP 8.1+
- Node.js

**Setup Script**:
```bash
#!/bin/bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install nginx mysql-server php8.1-fpm php8.1-mysql php8.1-xml php8.1-mbstring php8.1-curl -y

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Configure Nginx
sudo nano /etc/nginx/sites-available/lgu-tax-system
```

**Nginx Configuration**:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/lgu-tax-system/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Option 4: Docker Deployment
**Best for**: Technical teams wanting consistent environments

**Docker Compose Configuration**:
```yaml
version: '3.8'
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8000:8000"
    volumes:
      - .:/var/www/html
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_DATABASE=lgu_tax_system
      - DB_USERNAME=root
      - DB_PASSWORD=password

  db:
    image: mysql:8.0
    ports:
      - "3306:3306"
    environment:
      - MYSQL_ROOT_PASSWORD=password
      - MYSQL_DATABASE=lgu_tax_system
    volumes:
      - mysql_data:/var/lib/mysql

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

volumes:
  mysql_data:
```

## Security Considerations

### 1. **Environment Configuration**
```bash
# .env file security
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-generated-key

# Database security
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lgu_tax_system
DB_USERNAME=secure_username
DB_PASSWORD=strong_password
```

### 2. **Database Security**
```sql
-- Create dedicated database user
CREATE USER 'lgu_tax_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON lgu_tax_system.* TO 'lgu_tax_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. **File Permissions**
```bash
# Set proper file permissions
sudo chown -R www-data:www-data /var/www/lgu-tax-system
sudo chmod -R 755 /var/www/lgu-tax-system
sudo chmod -R 775 /var/www/lgu-tax-system/storage
sudo chmod -R 775 /var/www/lgu-tax-system/bootstrap/cache
```

## Backup Strategy

### 1. **Database Backup**
```bash
#!/bin/bash
# Daily backup script
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u username -p lgu_tax_system > backup_$DATE.sql
gzip backup_$DATE.sql
```

### 2. **File Backup**
```bash
# Backup application files
tar -czf lgu_tax_system_files_$(date +%Y%m%d).tar.gz /var/www/lgu-tax-system
```

## Monitoring and Maintenance

### 1. **Log Monitoring**
```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log

# Monitor Nginx logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log
```

### 2. **Performance Monitoring**
- Set up monitoring for database performance
- Monitor disk space usage
- Track API response times
- Monitor user sessions

### 3. **Regular Maintenance**
- Weekly database optimization
- Monthly security updates
- Quarterly backup testing
- Annual security audit

## Go-Live Checklist

### Pre-Launch
- [ ] All tests passing
- [ ] Database migrated successfully
- [ ] Security measures implemented
- [ ] Backup procedures in place
- [ ] User training completed
- [ ] Documentation updated

### Launch Day
- [ ] Final backup of standalone system
- [ ] DNS/domain configuration
- [ ] SSL certificate installed
- [ ] System monitoring active
- [ ] Support team ready

### Post-Launch
- [ ] Monitor system performance
- [ ] Collect user feedback
- [ ] Address any issues quickly
- [ ] Document lessons learned

## Support and Troubleshooting

### Common Issues

#### 1. **Database Connection Issues**
```bash
# Check database service
sudo systemctl status mysql

# Test connection
mysql -u username -p -h localhost
```

#### 2. **API Endpoint Not Working**
```bash
# Check Laravel routes
php artisan route:list

# Test specific route
curl -X GET "http://your-domain.com/api/payments"
```

#### 3. **Frontend Not Loading**
```bash
# Check if React build is current
cd main
npm run build

# Check Nginx configuration
sudo nginx -t
sudo systemctl reload nginx
```

### Contact Information
- Technical Support: [Your contact info]
- Database Issues: [DBA contact]
- Server Issues: [System admin contact]

---

**Remember**: Always test thoroughly in a staging environment before going live!

