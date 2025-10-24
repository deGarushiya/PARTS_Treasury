# XAMPP Migration Guide

## Overview
Since you've already converted your SQL Server database to XAMPP/MySQL, this guide will help you migrate that data to your online Laravel system.

## Prerequisites
- ✅ XAMPP with your converted database
- ✅ Laravel project set up
- ✅ Both databases accessible from your development machine

## Step 1: Configure XAMPP Connection

### 1.1 Update your `.env` file
Add these lines to your `.env` file:

```env
# XAMPP Database Connection (your standalone data)
XAMPP_DB_HOST=127.0.0.1
XAMPP_DB_PORT=3306
XAMPP_DB_DATABASE=your_xampp_database_name
XAMPP_DB_USERNAME=root
XAMPP_DB_PASSWORD=your_xampp_password

# Laravel Database Connection (your online system)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_laravel_database_name
DB_USERNAME=root
DB_PASSWORD=your_laravel_password
```

### 1.2 Test XAMPP Connection
```bash
php artisan tinker
>>> DB::connection('xampp')->getPdo();
>>> exit
```

## Step 2: Run Database Migrations

### 2.1 Create Laravel Database Tables
```bash
# Run the main migrations first
php artisan migrate

# Run the XAMPP data migration
php artisan migrate --path=database/migrations/2024_01_01_000000_migrate_xampp_data.php
```

## Step 3: Verify Migration

### 3.1 Check Data Counts
```bash
php artisan tinker
>>> DB::connection('xampp')->table('TAXPAYER')->count();
>>> DB::table('taxpayer')->count();
>>> exit
```

### 3.2 Test API Endpoints
```bash
# Test taxpayer search
curl -X GET "http://127.0.0.1:8000/api/ownersearch?search=test"

# Test tax due calculation
curl -X GET "http://127.0.0.1:8000/api/tax-due/TIN001"
```

## Step 4: Start Your System

### 4.1 Start Laravel API
```bash
php artisan serve
```

### 4.2 Start React Frontend
```bash
cd main
npm start
```

### 4.3 Access Your System
- **Frontend**: http://localhost:3000
- **API**: http://localhost:8000/api

## Troubleshooting

### Issue 1: Connection Failed
**Problem**: Cannot connect to XAMPP database
**Solution**:
1. Check if XAMPP MySQL is running
2. Verify database name and credentials
3. Check firewall settings

### Issue 2: Table Not Found
**Problem**: Migration script can't find tables
**Solution**:
1. Check table names in your XAMPP database
2. Update migration script with correct table names
3. Verify table structure matches expected format

### Issue 3: Data Type Mismatch
**Problem**: Data types don't match between systems
**Solution**:
1. Check column types in both databases
2. Update migration script with proper data casting
3. Handle NULL values appropriately

## Data Validation Checklist

After migration, verify:

- [ ] All taxpayers migrated
- [ ] All properties migrated
- [ ] All assessments migrated
- [ ] All payments migrated
- [ ] Tax due calculations work
- [ ] Payment processing works
- [ ] Receipt generation works

## Next Steps

1. **Test the complete system** with real data
2. **Train users** on the new interface
3. **Set up backup procedures**
4. **Plan for go-live**

## Support

If you encounter issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Run system test: `php test_system.php`
3. Verify database connections
4. Check API endpoints

---

**Your XAMPP migration should be straightforward since both systems use MySQL!** 🎉
