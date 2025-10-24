# LGU Real Property Tax System - Migration Guide

## Overview
This guide will help you migrate your standalone LGU Real Property Tax System to the online platform.

## Prerequisites
1. **Standalone Database Access**: You need access to your current standalone database
2. **Database Export**: Export your standalone database structure and data
3. **Backup**: Always backup your data before migration

## Step 1: Database Analysis

### Required Tables (Based on your current system)
Your standalone system likely has these key tables:

#### Core Tables:
- `TAXPAYER` - Property owners/taxpayers
- `PROPERTY` - Property information
- `RPTASSESSMENT` - Tax assessments
- `POSTINGJOURNAL` - Tax due and payment records
- `PAYMENT` - Payment records
- `PAYMENTDETAIL` - Payment breakdown
- `PAYMENTCHEQUE` - Cheque payment details

#### Reference Tables:
- `T_BARANGAY` - Barangay codes and names
- `T_PROPERTYKIND` - Property types
- `TPACCOUNT` - Taxpayer accounts

## Step 2: SQL Server 2012 Migration Process

### Option A: Direct SQL Server Connection (Recommended)
1. **Set up SQL Server Connection**:
   Add to `config/database.php`:
   ```php
   'sqlserver' => [
       'driver' => 'sqlsrv',
       'host' => env('SQLSERVER_HOST', 'localhost'),
       'port' => env('SQLSERVER_PORT', '1433'),
       'database' => env('SQLSERVER_DATABASE'),
       'username' => env('SQLSERVER_USERNAME'),
       'password' => env('SQLSERVER_PASSWORD'),
       'charset' => 'utf8',
       'prefix' => '',
       'prefix_indexes' => true,
   ],
   ```

2. **Add to .env file**:
   ```env
   SQLSERVER_HOST=your-sqlserver-host
   SQLSERVER_DATABASE=your_database_name
   SQLSERVER_USERNAME=your_username
   SQLSERVER_PASSWORD=your_password
   ```

3. **Run Migration**:
   ```bash
   php artisan migrate --path=database/migrations/2024_01_01_000000_migrate_standalone_data.php
   ```

### Option B: Export/Import via CSV Files
1. **Export from SQL Server 2012**:
   ```sql
   -- Use SQL Server Management Studio or sqlcmd
   -- Export TAXPAYER table
   bcp "SELECT * FROM TAXPAYER" queryout "taxpayer.csv" -c -t"," -r"\n" -S your_server -T
   
   -- Export PROPERTY table
   bcp "SELECT * FROM PROPERTY" queryout "property.csv" -c -t"," -r"\n" -S your_server -T
   
   -- Export POSTINGJOURNAL table
   bcp "SELECT * FROM POSTINGJOURNAL" queryout "postingjournal.csv" -c -t"," -r"\n" -S your_server -T
   
   -- Export PAYMENT table
   bcp "SELECT * FROM PAYMENT" queryout "payment.csv" -c -t"," -r"\n" -S your_server -T
   ```

2. **Import to MySQL**:
   ```bash
   php artisan migrate:import-csv
   ```

### Option C: SQL Server to MySQL Migration Tool
Use tools like:
- **SQL Server Migration Assistant (SSMA)**
- **MySQL Workbench Migration Wizard**
- **Custom PowerShell scripts**

## Step 3: Data Validation

### Critical Data Checks:
1. **Taxpayer Count**: Verify all taxpayers migrated
2. **Property Count**: Check property records
3. **Tax Due Calculations**: Validate posting journal totals
4. **Payment History**: Ensure payment records are intact

### Validation Queries:
```sql
-- Check taxpayer count
SELECT COUNT(*) FROM taxpayer;

-- Check property count  
SELECT COUNT(*) FROM property;

-- Verify tax due calculations
SELECT LOCAL_TIN, SUM(RPT_DUE + SEF_DUE - TOTAL_PAID) as balance 
FROM postingjournal 
GROUP BY LOCAL_TIN;
```

## Step 4: System Testing

### Test Scenarios:
1. **Taxpayer Search**: Search for existing taxpayers
2. **Tax Due Retrieval**: Get tax due for known taxpayers
3. **Payment Processing**: Create test payments
4. **Receipt Generation**: Generate payment receipts

## Step 5: Go-Live Preparation

### Pre-Launch Checklist:
- [ ] All data migrated successfully
- [ ] Tax calculations match standalone system
- [ ] Payment processing works correctly
- [ ] Receipt generation functional
- [ ] User training completed
- [ ] Backup procedures in place

## Common Issues and Solutions

### Issue 1: Column Name Mismatches
**Problem**: Standalone uses different column names
**Solution**: Update migration script column mappings

### Issue 2: Data Type Differences
**Problem**: Data types don't match between systems
**Solution**: Add data type conversion in migration

### Issue 3: Missing Reference Data
**Problem**: Some reference tables missing
**Solution**: Create missing tables and populate with reference data

## Support and Maintenance

### Post-Migration Tasks:
1. **Monitor System Performance**
2. **User Feedback Collection**
3. **Data Integrity Checks**
4. **Regular Backups**

### Contact Information:
- Technical Support: [Your contact info]
- Database Issues: [DBA contact]
- User Training: [Training coordinator]

## Next Steps

After successful migration:
1. Train users on the new system
2. Set up monitoring and alerts
3. Plan for future enhancements
4. Document any customizations made

---

**Important**: Always test the migration process with a copy of your data first!

