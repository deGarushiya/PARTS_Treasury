# SQL Server 2012 to MySQL Migration Guide

## Overview
This guide will help you migrate your LGU Real Property Tax System from SQL Server 2012 to the online MySQL-based system.

## Prerequisites

### 1. **Software Requirements**
- SQL Server Management Studio (SSMS) or sqlcmd
- PowerShell (for export script)
- PHP with SQL Server drivers
- MySQL/MariaDB
- Laravel framework

### 2. **SQL Server Drivers for PHP**
Install the Microsoft SQL Server drivers for PHP:

**For Windows:**
```bash
# Download and install Microsoft ODBC Driver for SQL Server
# Then install PHP SQL Server extension
```

**For Linux:**
```bash
# Install Microsoft ODBC Driver
curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add -
curl https://packages.microsoft.com/config/ubuntu/20.04/prod.list > /etc/apt/sources.list.d/mssql-release.list
apt-get update
ACCEPT_EULA=Y apt-get install -y msodbcsql17

# Install PHP SQL Server extension
pecl install sqlsrv pdo_sqlsrv
```

## Migration Methods

### Method 1: Direct Connection (Recommended)

#### Step 1: Configure SQL Server Connection
1. **Add to your `.env` file:**
```env
# SQL Server Connection
SQLSERVER_HOST=your-sqlserver-host
SQLSERVER_PORT=1433
SQLSERVER_DATABASE=your_database_name
SQLSERVER_USERNAME=your_username
SQLSERVER_PASSWORD=your_password
```

2. **Update `config/database.php`:**
```php
'connections' => [
    // ... existing connections ...
    
    'sqlserver' => [
        'driver' => 'sqlsrv',
        'host' => env('SQLSERVER_HOST', 'localhost'),
        'port' => env('SQLSERVER_PORT', '1433'),
        'database' => env('SQLSERVER_DATABASE', ''),
        'username' => env('SQLSERVER_USERNAME', ''),
        'password' => env('SQLSERVER_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
    ],
],
```

#### Step 2: Test Connection
```bash
php artisan tinker
>>> DB::connection('sqlserver')->getPdo();
>>> exit
```

#### Step 3: Run Migration
```bash
php artisan migrate --path=database/migrations/2024_01_01_000000_migrate_standalone_data.php
```

### Method 2: CSV Export/Import

#### Step 1: Export from SQL Server
1. **Using PowerShell Script:**
```powershell
# Run the export script
.\scripts\export_sqlserver_data.ps1 -ServerName "YOUR_SERVER" -DatabaseName "YOUR_DATABASE" -Username "YOUR_USERNAME" -Password "YOUR_PASSWORD"
```

2. **Using SQL Server Management Studio:**
```sql
-- Right-click database → Tasks → Export Data
-- Or use bcp command:
bcp "SELECT * FROM TAXPAYER" queryout "taxpayer.csv" -c -t"," -r"\n" -S your_server -T
```

3. **Using sqlcmd:**
```bash
sqlcmd -S your_server -d your_database -E -Q "SELECT * FROM TAXPAYER" -o "taxpayer.csv" -s ","
```

#### Step 2: Import to MySQL
```bash
# Place CSV files in exports/ directory
php artisan migrate:import-csv
```

### Method 3: SQL Server Migration Assistant (SSMA)

#### Step 1: Download and Install SSMA
1. Download SQL Server Migration Assistant for MySQL
2. Install and configure the tool

#### Step 2: Create Migration Project
1. Create new project in SSMA
2. Connect to SQL Server source
3. Connect to MySQL target
4. Map tables and data types
5. Run migration

## Data Mapping

### Table Structure Mapping

| SQL Server Table | MySQL Table | Notes |
|------------------|-------------|-------|
| TAXPAYER | taxpayer | Column mapping required |
| PROPERTY | property | Direct mapping |
| RPTASSESSMENT | rptassessment | Direct mapping |
| POSTINGJOURNAL | postingjournal | Direct mapping |
| PAYMENT | payment | Direct mapping |
| PAYMENTDETAIL | paymentdetail | Direct mapping |
| PAYMENTCHEQUE | paymentcheque | Direct mapping |
| T_BARANGAY | t_barangay | Reference data |
| T_PROPERTYKIND | t_propertykind | Reference data |

### Column Mapping Examples

#### TAXPAYER Table
```sql
-- SQL Server
LOCAL_TIN → LOCAL_TIN
OWNERNAME → NAME
ADDRESS → ADDRESS
BARANGAY → BARANGAY
MUNICIPALITY → MUNICIPALITY
PROVINCE → PROVINCE
ZIPCODE → ZIPCODE
CONTACTNO → CONTACTNO
EMAIL → EMAIL
```

## Common Issues and Solutions

### Issue 1: Connection Timeout
**Problem**: SQL Server connection times out
**Solution**:
```php
'options' => [
    PDO::ATTR_TIMEOUT => 60,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
],
```

### Issue 2: Character Encoding Issues
**Problem**: Special characters not displaying correctly
**Solution**:
```php
'charset' => 'utf8mb4',
'collation' => 'utf8mb4_unicode_ci',
```

### Issue 3: Date Format Issues
**Problem**: Date formats don't match between systems
**Solution**:
```php
// Convert SQL Server date format
$date = Carbon::createFromFormat('Y-m-d H:i:s', $sqlServerDate);
```

### Issue 4: Large Dataset Performance
**Problem**: Migration is slow for large datasets
**Solution**:
```php
// Process in chunks
DB::connection('sqlserver')->table('TAXPAYER')->chunk(1000, function ($taxpayers) {
    // Process chunk
});
```

## Data Validation

### Pre-Migration Validation
```sql
-- Count records in SQL Server
SELECT 
    'TAXPAYER' as TableName, COUNT(*) as RecordCount FROM TAXPAYER
UNION ALL
SELECT 'PROPERTY', COUNT(*) FROM PROPERTY
UNION ALL
SELECT 'POSTINGJOURNAL', COUNT(*) FROM POSTINGJOURNAL
UNION ALL
SELECT 'PAYMENT', COUNT(*) FROM PAYMENT;
```

### Post-Migration Validation
```sql
-- Count records in MySQL
SELECT 
    'taxpayer' as TableName, COUNT(*) as RecordCount FROM taxpayer
UNION ALL
SELECT 'property', COUNT(*) FROM property
UNION ALL
SELECT 'postingjournal', COUNT(*) FROM postingjournal
UNION ALL
SELECT 'payment', COUNT(*) FROM payment;
```

### Data Integrity Checks
```sql
-- Check for missing data
SELECT COUNT(*) as MissingTaxpayers 
FROM postingjournal pj 
LEFT JOIN taxpayer t ON pj.LOCAL_TIN = t.LOCAL_TIN 
WHERE t.LOCAL_TIN IS NULL;

-- Check payment totals
SELECT 
    LOCAL_TIN,
    SUM(RPT_DUE + SEF_DUE) as TotalDue,
    SUM(TOTAL_PAID) as TotalPaid,
    SUM(RPT_DUE + SEF_DUE) - SUM(TOTAL_PAID) as Balance
FROM postingjournal 
GROUP BY LOCAL_TIN;
```

## Testing the Migration

### 1. **Run System Test**
```bash
php test_system.php
```

### 2. **Test API Endpoints**
```bash
# Test taxpayer search
curl -X GET "http://127.0.0.1:8000/api/ownersearch?search=Juan"

# Test tax due calculation
curl -X GET "http://127.0.0.1:8000/api/tax-due/TIN001"
```

### 3. **Test Frontend**
1. Start React development server
2. Search for migrated taxpayers
3. Verify tax due calculations
4. Test payment processing

## Rollback Plan

### If Migration Fails
1. **Stop the migration process**
2. **Restore from backup** (if available)
3. **Identify the issue** using logs
4. **Fix the problem** and retry

### Backup Strategy
```bash
# Backup MySQL before migration
mysqldump -u username -p lgu_tax_system > backup_before_migration.sql

# Backup SQL Server (if possible)
sqlcmd -S server -d database -E -Q "BACKUP DATABASE [database] TO DISK = 'backup.bak'"
```

## Performance Optimization

### For Large Datasets
1. **Disable foreign key checks** during migration
2. **Use bulk insert** operations
3. **Process data in chunks**
4. **Optimize indexes** after migration

```sql
-- Disable foreign key checks
SET FOREIGN_KEY_CHECKS = 0;

-- Your migration here

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
```

## Security Considerations

### 1. **Connection Security**
- Use encrypted connections
- Limit database user permissions
- Use strong passwords

### 2. **Data Privacy**
- Ensure sensitive data is properly handled
- Consider data masking for test environments
- Implement proper access controls

## Support and Troubleshooting

### Common Commands
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Test database connection
php artisan tinker
>>> DB::connection('sqlserver')->getPdo();

# Check migration status
php artisan migrate:status
```

### Contact Information
- Technical Support: [Your contact info]
- Database Issues: [DBA contact]
- SQL Server Issues: [System admin contact]

---

**Important**: Always test the migration process with a copy of your data first!
