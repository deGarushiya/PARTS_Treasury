# 📋 SQL QUERIES AND AFFECTED FILES - REFERENCE GUIDE

## Overview
This document contains the exact SQL queries and all files modified for both the **Payment Posting** (Tax Dues table) and **Manual Debit** (Assessment table) features.

---

## 🔹 PAYMENT POSTING - TAX DUES TABLE

### Purpose
Displays tax dues for a specific property (TDNO) owned by a taxpayer, including penalties, discounts, and credits.

### SQL Query

```sql
SELECT 
    tp.TAXYEAR,
    pj.TDNO,
    tp.PROP_ID,
    tp.JOURNALID as POSTINGJOURNAL_ID,
    SUM(CASE WHEN tp.EVENTOBJECT_CT = 'ASS' THEN tp.DEBITAMOUNT ELSE 0 END) as amount_due,
    SUM(CASE WHEN tp.EVENTOBJECT_CT IN ('PEN', 'DED') THEN tp.DEBITAMOUNT ELSE 0 END) as penalty_discount,
    SUM(CASE WHEN tp.EVENTOBJECT_CT IN ('TCR', 'TDF') THEN tp.DEBITAMOUNT / 2 ELSE 0 END) as credits
FROM tpaccount as tp
INNER JOIN postingjournal as pj 
    ON tp.PROP_ID = pj.PROP_ID 
    AND tp.TAXYEAR = pj.TAXYEAR 
    AND tp.JOURNALID = pj.POSTINGJOURNAL_ID
WHERE 
    tp.LOCAL_TIN = ?              -- Dynamic parameter (taxpayer)
    AND pj.TDNO = ?                -- Dynamic parameter (property)
    AND tp.EARMARK_CT = 'OPN'      -- Only open/unpaid records
GROUP BY 
    tp.TAXYEAR, 
    pj.TDNO, 
    tp.PROP_ID, 
    tp.JOURNALID
ORDER BY 
    tp.TAXYEAR ASC;
```

### Tables Joined
1. **`tpaccount`** (tp) - Main source, contains ASS/PEN/DED/TCR records
2. **`postingjournal`** (pj) - Contains TDNO and tax year information

### Key Filters
- `LOCAL_TIN` = Specific taxpayer
- `TDNO` = Specific property
- `EARMARK_CT = 'OPN'` = Only open/unpaid accounts
- **NO filter on `POSTED_BV`** (VB app doesn't use it)

### Calculation Logic
```
Total Tax Due = amount_due + penalty_discount - credits

Where:
- amount_due = SUM(ASS records)
- penalty_discount = SUM(PEN/DED records)
- credits = SUM(TCR/TDF records) / 2
```

### Backend Files Modified

**File:** `app/Http/Controllers/Api/TaxDueController.php`  
**Method:** `getTaxDueByTdno($localTin, $tdno)`  
**Lines:** 168-222

```php
public function getTaxDueByTdno($localTin, $tdno)
{
    try {
        \Log::info("🔍 getTaxDueByTdno called", ['LOCAL_TIN' => $localTin, 'TDNO' => $tdno]);
        
        $baseDues = DB::table('tpaccount as tp')
            ->join('postingjournal as pj', function($join) {
                $join->on('tp.PROP_ID', '=', 'pj.PROP_ID')
                     ->on('tp.TAXYEAR', '=', 'pj.TAXYEAR')
                     ->on('tp.JOURNALID', '=', 'pj.POSTINGJOURNAL_ID');
            })
            ->select(
                'tp.TAXYEAR',
                'pj.TDNO',
                'tp.PROP_ID',
                'tp.JOURNALID as POSTINGJOURNAL_ID',
                DB::raw('SUM(CASE WHEN tp.EVENTOBJECT_CT = "ASS" THEN tp.DEBITAMOUNT ELSE 0 END) as amount_due'),
                DB::raw('SUM(CASE WHEN tp.EVENTOBJECT_CT IN ("PEN", "DED") THEN tp.DEBITAMOUNT ELSE 0 END) as penalty_discount'),
                DB::raw('SUM(CASE WHEN tp.EVENTOBJECT_CT IN ("TCR", "TDF") THEN tp.DEBITAMOUNT / 2 ELSE 0 END) as credits')
            )
            ->where('tp.LOCAL_TIN', $localTin)
            ->where('pj.TDNO', $tdno)
            ->where('tp.EARMARK_CT', 'OPN')
            ->groupBy('tp.TAXYEAR', 'pj.TDNO', 'tp.PROP_ID', 'tp.JOURNALID')
            ->orderBy('tp.TAXYEAR')
            ->get();
        
        // ... formatting logic ...
    }
}
```

**File:** `routes/api.php`  
**Route:** `Route::get('/tax-due/{localTin}/{tdno}', [TaxDueController::class, 'getTaxDueByTdno']);`

### Frontend Files Modified

**File:** `main/src/services/api.js`
```javascript
export const taxDueAPI = {
  getTaxDueByTdno: (localTin, tdno) => api.get(`/tax-due/by-tdno/${localTin}/${tdno}`)
};
```

**File:** `main/src/pages/PaymentPosting/GetTaxDueModal.js`  
**Lines:** 44-75 (fetchTaxDueByTdno function)

```javascript
const fetchTaxDueByTdno = async (tdno) => {
  if (!localTin || !tdno) return;
  console.log(`🔍 Fetching tax dues for LOCAL_TIN: ${localTin}, TDNO: ${tdno}`);
  setLoadingDues(true);
  setErrorDues(null);
  try {
    const response = await taxDueAPI.getTaxDueByTdno(localTin, tdno);
    console.log('📊 Tax dues response:', response.data);
    const duesData = Array.isArray(response.data) ? response.data : [];
    setDues(duesData);
    // ... auto-select logic ...
  } catch (err) {
    console.error("❌ Error fetching tax due by TDNO:", err);
    setErrorDues("Failed to fetch tax due data.");
  } finally {
    setLoadingDues(false);
  }
};
```

---

## 🔹 MANUAL DEBIT - ASSESSMENT TABLE

### Purpose
Displays all tax assessment records for a taxpayer (all properties), showing assessed values, tax amounts, and payment status.

### SQL Query

```sql
SELECT 
    pj.TDNO,
    tp.TAXYEAR as Year,
    p.PINNO as PIN,
    ra.LANDASSESSEDVALUE as Land,
    ra.BLDGASSESSEDVALUE as Improvements,
    (ra.LANDASSESSEDVALUE + ra.BLDGASSESSEDVALUE) as Total,
    pj.RPTTAXDUE as Basic,
    pj.SEFTAXDUE as SEF,
    tp.EVENTOBJECT_CT as Source,
    tp.EARMARK_CT as Status
FROM tpaccount as tp
INNER JOIN postingjournal as pj 
    ON tp.PROP_ID = pj.PROP_ID 
    AND tp.TAXYEAR = pj.TAXYEAR 
    AND tp.JOURNALID = pj.POSTINGJOURNAL_ID
INNER JOIN rptassessment as ra 
    ON pj.TDNO = ra.TDNO
INNER JOIN property as p 
    ON ra.PROP_ID = p.PROP_ID
WHERE 
    tp.LOCAL_TIN = ?                 -- Dynamic parameter (taxpayer)
    AND tp.EVENTOBJECT_CT = 'ASS'    -- Only assessment records
GROUP BY 
    pj.TDNO, 
    tp.TAXYEAR, 
    p.PINNO, 
    ra.LANDASSESSEDVALUE, 
    ra.BLDGASSESSEDVALUE,
    pj.RPTTAXDUE,
    pj.SEFTAXDUE,
    tp.EVENTOBJECT_CT,
    tp.EARMARK_CT,
    ra.PROP_ID,
    pj.POSTINGJOURNAL_ID
ORDER BY 
    pj.TDNO ASC,
    tp.TAXYEAR DESC;
```

### Tables Joined
1. **`tpaccount`** (tp) - Main source, ensures only initialized records
2. **`postingjournal`** (pj) - Contains TDNO, RPTTAXDUE, SEFTAXDUE
3. **`rptassessment`** (ra) - Contains assessed values (LANDASSESSEDVALUE, BLDGASSESSEDVALUE)
4. **`property`** (p) - Contains PIN number

### Key Filters
- `LOCAL_TIN` = Specific taxpayer
- `EVENTOBJECT_CT = 'ASS'` = Only assessment records (not PEN/DED/TCR)
- **NO TDNO filter** - Shows all properties for the taxpayer

### Color Coding Logic
```javascript
if (EARMARK_CT === 'OPN') {
  status = 'open';        // White background
} else if (EARMARK_CT === 'INS' || EARMARK_CT === 'DBP') {
  status = 'installment'; // Teal/green background
} else if (EARMARK_CT === 'PSD') {
  status = 'paid';        // Gray background
}
```

### Backend Files Modified

**File:** `app/Http/Controllers/Api/TaxDueController.php`  
**Method:** `getAssessmentDetails($localTin)`  
**Lines:** 365-449

```php
public function getAssessmentDetails($localTin)
{
    try {
        \Log::info("🔍 Fetching assessment details for Manual Debit", ['LOCAL_TIN' => $localTin]);
        
        $assessments = DB::table('tpaccount as tp')
            ->join('postingjournal as pj', function($join) {
                $join->on('tp.PROP_ID', '=', 'pj.PROP_ID')
                     ->on('tp.TAXYEAR', '=', 'pj.TAXYEAR')
                     ->on('tp.JOURNALID', '=', 'pj.POSTINGJOURNAL_ID');
            })
            ->join('rptassessment as ra', 'pj.TDNO', '=', 'ra.TDNO')
            ->join('property as p', 'ra.PROP_ID', '=', 'p.PROP_ID')
            ->select(
                'pj.TDNO',
                'tp.TAXYEAR as Year',
                'p.PINNO as PIN',
                'ra.LANDASSESSEDVALUE as Land',
                'ra.BLDGASSESSEDVALUE as Improvements',
                DB::raw('(ra.LANDASSESSEDVALUE + ra.BLDGASSESSEDVALUE) as Total'),
                'pj.RPTTAXDUE as Basic',
                'pj.SEFTAXDUE as SEF',
                'tp.EVENTOBJECT_CT as Source',
                'tp.EARMARK_CT as Status'
            )
            ->where('tp.LOCAL_TIN', $localTin)
            ->where('tp.EVENTOBJECT_CT', 'ASS')
            ->groupBy(/* ... */)
            ->orderBy('pj.TDNO')
            ->orderByDesc('tp.TAXYEAR')
            ->get();
        
        // ... color coding and formatting logic ...
    }
}
```

**File:** `routes/api.php`  
**Route:** `Route::get('/tax-due/assessments/{localTin}', [TaxDueController::class, 'getAssessmentDetails']);`

### Frontend Files Modified

**File:** `main/src/services/api.js`
```javascript
export const taxDueAPI = {
  getAssessmentDetails: (localTin) => api.get(`/tax-due/assessments/${localTin}`)
};
```

**File:** `main/src/pages/ManualDebit/ManualDebitPage.js`  
**Key Changes:**
- Added state variables: `localTin`, `ownerName`, `assessments`, `loadingAssessments`, `errorAssessments`
- Added useEffect to fetch data when `localTin` changes
- Updated OwnerSearchModal callback to set `localTin` and `ownerName`
- Updated table rendering to display real data with color coding

**File:** `main/src/pages/ManualDebit/ManualDebitPage.css`  
**Added:** Color coding styles for `.status-open`, `.status-installment`, `.status-paid`

---

## 📊 Comparison: Payment Posting vs Manual Debit

| Feature | Payment Posting (Tax Dues) | Manual Debit (Assessments) |
|---------|---------------------------|---------------------------|
| **Scope** | Single property (TDNO) | All properties (LOCAL_TIN) |
| **Filter** | By TDNO | No TDNO filter |
| **Records** | ASS + PEN + DED + TCR | ASS only |
| **Display** | Tax dues with penalties | Assessment values |
| **Joins** | 2 tables (tp, pj) | 4 tables (tp, pj, ra, p) |
| **Columns** | Tax Year, Amount Due, Penalty, Credits, Total | TD, Year, PIN, Land, Improvements, Basic, SEF |
| **Color** | Blue highlight on selection | White/Teal/Gray by status |
| **Purpose** | Calculate payment amount | View assessment history |

---

## 🗂️ All Database Tables Used

### Core Tables
1. **`tpaccount`** 
   - Source of truth for both queries
   - Contains: EVENTOBJECT_CT (ASS/PEN/DED/TCR), EARMARK_CT (OPN/INS/PSD), DEBITAMOUNT
   - **KEY POINT:** Only records in this table will be shown

2. **`postingjournal`**
   - Contains: TDNO, TAXYEAR, RPTTAXDUE, SEFTAXDUE, PROP_ID, POSTINGJOURNAL_ID
   - Links TPACCOUNT to properties

3. **`rptassessment`**
   - Contains: TDNO, LANDASSESSEDVALUE, BLDGASSESSEDVALUE, STARTYEAR, ended_bv
   - Provides assessed values

4. **`property`**
   - Contains: PINNO, PROP_ID
   - Provides PIN number

### Lookup Tables
5. **`t_barangay`** - Barangay names
6. **`t_propertykind`** - Property type (Land/Building)
7. **`propertyowner`** - Links LOCAL_TIN to PROP_ID

---

## 🔑 Critical Discovery: The TPACCOUNT Rule

**IMPORTANT:** Both queries start from `TPACCOUNT`, not `postingjournal`!

### Why?
The old VB app shows tax data **ONLY if TPACCOUNT records exist** with `EARMARK_CT = 'OPN'`.

### This means:
- ✅ If TPACCOUNT has records → Data shows
- ❌ If TPACCOUNT has NO records → NO data shows (even if postingjournal has data)

### This explains:
- **Garcia's properties** (03-0016-00255, 03-0016-00264) show NO data → No TPACCOUNT records
- **Rivera's property** (03-0019-01094) shows data → Has TPACCOUNT records
- **Angeles properties** are mixed → Some have TPACCOUNT, some don't

---

## 🎯 Test Data Reference

### Test Case 1: Owner with Data
```
LOCAL_TIN: 0130030026473
Owner: Rivera, Rodrigo
TD: 03-0019-01094
Expected: 7 records (2015-2021), Total: 2,864.00
```

### Test Case 2: Owner with Some Properties Having Data
```
LOCAL_TIN: 0130030052733
Owner: Garcia, Abraham

TD: 03-0016-00276 (Expired) → NO DATA
TD: 03-0016-00255           → NO DATA
TD: 03-0016-00264           → NO DATA
```

### Test Case 3: Owner for Manual Debit
```
LOCAL_TIN: 0130030072580
Owner: Abella, Alex
Expected: 4 records for TD 03-0005-01338 (2018-2021)
```

---

## 📋 Quick Reference: API Endpoints

```bash
# Get properties for a taxpayer (upper table in Get Tax Due modal)
GET /api/get-tax-due/properties/{localTin}

# Get tax dues for a property (lower table in Get Tax Due modal)
GET /api/tax-due/{localTin}/{tdno}

# Get assessments for taxpayer (Manual Debit table)
GET /api/tax-due/assessments/{localTin}

# Initialize taxpayer debit (create TPACCOUNT ASS records)
POST /api/tax-due/initialize/{localTin}
```

---

## 🛠️ How to Test SQL Directly in Database

### Test Tax Dues Query:
```sql
-- Replace with actual values
SET @localTin = '0130030026473';
SET @tdno = '03-0019-01094';

SELECT 
    tp.TAXYEAR,
    pj.TDNO,
    SUM(CASE WHEN tp.EVENTOBJECT_CT = 'ASS' THEN tp.DEBITAMOUNT ELSE 0 END) as amount_due,
    SUM(CASE WHEN tp.EVENTOBJECT_CT IN ('PEN', 'DED') THEN tp.DEBITAMOUNT ELSE 0 END) as penalty_discount,
    SUM(CASE WHEN tp.EVENTOBJECT_CT IN ('TCR', 'TDF') THEN tp.DEBITAMOUNT / 2 ELSE 0 END) as credits
FROM tpaccount as tp
INNER JOIN postingjournal as pj 
    ON tp.PROP_ID = pj.PROP_ID 
    AND tp.TAXYEAR = pj.TAXYEAR 
    AND tp.JOURNALID = pj.POSTINGJOURNAL_ID
WHERE 
    tp.LOCAL_TIN = @localTin
    AND pj.TDNO = @tdno
    AND tp.EARMARK_CT = 'OPN'
GROUP BY 
    tp.TAXYEAR, pj.TDNO, tp.PROP_ID, tp.JOURNALID
ORDER BY tp.TAXYEAR;
```

### Test Assessment Query:
```sql
-- Replace with actual value
SET @localTin = '0130030072580';

SELECT 
    pj.TDNO,
    tp.TAXYEAR as Year,
    p.PINNO as PIN,
    ra.LANDASSESSEDVALUE as Land,
    ra.BLDGASSESSEDVALUE as Improvements,
    (ra.LANDASSESSEDVALUE + ra.BLDGASSESSEDVALUE) as Total,
    pj.RPTTAXDUE as Basic,
    pj.SEFTAXDUE as SEF,
    tp.EVENTOBJECT_CT as Source,
    tp.EARMARK_CT as Status
FROM tpaccount as tp
INNER JOIN postingjournal as pj 
    ON tp.PROP_ID = pj.PROP_ID 
    AND tp.TAXYEAR = pj.TAXYEAR 
    AND tp.JOURNALID = pj.POSTINGJOURNAL_ID
INNER JOIN rptassessment as ra 
    ON pj.TDNO = ra.TDNO
INNER JOIN property as p 
    ON ra.PROP_ID = p.PROP_ID
WHERE 
    tp.LOCAL_TIN = @localTin
    AND tp.EVENTOBJECT_CT = 'ASS'
GROUP BY 
    pj.TDNO, tp.TAXYEAR, p.PINNO, 
    ra.LANDASSESSEDVALUE, ra.BLDGASSESSEDVALUE,
    pj.RPTTAXDUE, pj.SEFTAXDUE,
    tp.EVENTOBJECT_CT, tp.EARMARK_CT,
    ra.PROP_ID, pj.POSTINGJOURNAL_ID
ORDER BY pj.TDNO, tp.TAXYEAR DESC;
```

---

## ✅ Implementation Checklist

### Payment Posting (Completed)
- [x] Backend: `getTaxDueByTdno()` method
- [x] Backend: API route for tax dues
- [x] Frontend: API service function
- [x] Frontend: GetTaxDueModal component
- [x] Frontend: CSS styling
- [x] Testing: Verified with multiple owners
- [x] Bug fixes: TPACCOUNT-based filtering

### Manual Debit (Completed)
- [x] Backend: `getAssessmentDetails()` method
- [x] Backend: API route for assessments
- [x] Frontend: API service function
- [x] Frontend: ManualDebitPage component updates
- [x] Frontend: CSS color coding
- [x] Testing: Ready for teammate to test
- [ ] Integration: Connect with other Manual Debit features

---

**Document Version:** 1.0  
**Last Updated:** October 22, 2025  
**Author:** Development Team  
**Project:** Parts Online - RPT System

