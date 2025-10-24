# 📚 DATABASE TABLES GUIDE - For Manual Debit Assessment Table

## 🎯 Quick Overview

The Assessment Table uses **4 main tables** joined together:

```
tpaccount (tp) 
    ↓ JOIN
postingjournal (pj)
    ↓ JOIN
rptassessment (ra)
    ↓ JOIN
property (p)
```

---

## 📋 TABLE 1: `tpaccount` (Alias: tp)

### Purpose
**This is the MAIN table!** It stores all tax account transactions including assessments, penalties, discounts, and credits.

### Important Columns

| Column | Type | Description | Example Values |
|--------|------|-------------|---------------|
| `POSTING_ID` | INT | Primary key | 12345 |
| `LOCAL_TIN` | VARCHAR | Taxpayer ID | 0130030072580 |
| `TAXYEAR` | INT | Tax year | 2021 |
| `EVENTOBJECT_CT` | VARCHAR | Type of record | ASS, PEN, DED, TCR, TDF |
| `EARMARK_CT` | VARCHAR | Payment status | OPN, INS, PSD, DBP |
| `DEBITAMOUNT` | DECIMAL | Amount | 176.00 |
| `PROP_ID` | INT | Property ID | 567 |
| `JOURNALID` | INT | Links to postingjournal | 890 |
| `TAXPERIOD_CT` | INT | Tax period code | 99 (Yearly) |

### Event Object Types (EVENTOBJECT_CT)
- **ASS** = Assessment (base tax amount) ← **We use this for Manual Debit!**
- **PEN** = Penalty
- **DED** = Discount
- **TCR** = Tax Credit
- **TDF** = Tax Difference

### Earmark Status (EARMARK_CT)
- **OPN** = Open (unpaid) → Shows as **White** in table
- **INS** = Installment → Shows as **Teal/Green** in table
- **DBP** = Double Post → Shows as **Teal/Green** in table
- **PSD** = Paid → Shows as **Gray** in table

### Why Start Here?
✅ The old VB app ONLY shows records that exist in TPACCOUNT  
✅ If no TPACCOUNT records → No data shows (this is correct behavior!)

---

## 📋 TABLE 2: `postingjournal` (Alias: pj)

### Purpose
Stores posted tax bills for each property per year.

### Important Columns

| Column | Type | Description | Example Values |
|--------|------|-------------|---------------|
| `POSTINGJOURNAL_ID` | INT | Primary key | 890 |
| `TDNO` | VARCHAR | Tax Declaration Number | 03-0005-01338 |
| `TAXYEAR` | INT | Tax year | 2021 |
| `PROP_ID` | INT | Property ID | 567 |
| `LOCAL_TIN` | VARCHAR | Taxpayer ID | 0130030072580 |
| `RPTTAXDUE` | DECIMAL | Basic RPT tax | 176.00 |
| `SEFTAXDUE` | DECIMAL | SEF tax | 176.00 |
| `POSTED_BV` | TINYINT | Posted flag (0/1) | 1 |
| `CANCELLED_BV` | TINYINT | Cancelled flag (0/1) | 0 |

### Join Condition with tpaccount
```sql
ON tp.PROP_ID = pj.PROP_ID 
AND tp.TAXYEAR = pj.TAXYEAR 
AND tp.JOURNALID = pj.POSTINGJOURNAL_ID
```

### What We Get From This Table
- `TDNO` (Tax Declaration Number)
- `RPTTAXDUE` (Basic tax amount)
- `SEFTAXDUE` (SEF tax amount)

---

## 📋 TABLE 3: `rptassessment` (Alias: ra)

### Purpose
Stores property assessment values (land and building).

### Important Columns

| Column | Type | Description | Example Values |
|--------|------|-------------|---------------|
| `TDNO` | VARCHAR | Tax Declaration Number | 03-0005-01338 |
| `PROP_ID` | INT | Property ID | 567 |
| `LANDASSESSEDVALUE` | DECIMAL | Land value | 0.00 |
| `BLDGASSESSEDVALUE` | DECIMAL | Building value | 17600.00 |
| `STARTYEAR` | INT | First assessment year | 2018 |
| `ended_bv` | TINYINT | Expired flag (0/1) | 0 |

### Join Condition with postingjournal
```sql
ON pj.TDNO = ra.TDNO
```

### What We Get From This Table
- `LANDASSESSEDVALUE` (Land assessed value)
- `BLDGASSESSEDVALUE` (Building/improvements value)
- **Total** = LANDASSESSEDVALUE + BLDGASSESSEDVALUE

---

## 📋 TABLE 4: `property` (Alias: p)

### Purpose
Stores basic property information.

### Important Columns

| Column | Type | Description | Example Values |
|--------|------|-------------|---------------|
| `PROP_ID` | INT | Primary key | 567 |
| `PINNO` | VARCHAR | Property ID Number | 013-03-0005-034-27-1002 |
| `BARANGAY_CT` | VARCHAR | Barangay code | 005 |
| `PROPERTYKIND_CT` | VARCHAR | Property type | LND, BLD |

### Join Condition with rptassessment
```sql
ON ra.PROP_ID = p.PROP_ID
```

### What We Get From This Table
- `PINNO` (Property Identification Number)

---

## 🔗 How The Tables Connect

```
Step 1: tpaccount
  - Has: LOCAL_TIN, TAXYEAR, PROP_ID, JOURNALID
  - Filter: EVENTOBJECT_CT = 'ASS'

Step 2: Join → postingjournal
  - Match: PROP_ID, TAXYEAR, JOURNALID
  - Get: TDNO, RPTTAXDUE, SEFTAXDUE

Step 3: Join → rptassessment
  - Match: TDNO
  - Get: LANDASSESSEDVALUE, BLDGASSESSEDVALUE

Step 4: Join → property
  - Match: PROP_ID
  - Get: PINNO
```

---

## 📊 The Complete Data Flow

```
User clicks "Search" button
    ↓
Selects owner (LOCAL_TIN: 0130030072580)
    ↓
System queries: tpaccount
    WHERE LOCAL_TIN = '0130030072580'
    AND EVENTOBJECT_CT = 'ASS'
    ↓
Joins with: postingjournal (get TDNO, taxes)
    ↓
Joins with: rptassessment (get land/building values)
    ↓
Joins with: property (get PIN)
    ↓
Returns data:
    TD No: 03-0005-01338
    Year: 2021
    PIN: 013-03-0005-034-27-1002
    Land: 0.00
    Improvements: 17,600.00
    Total: 17,600.00
    Basic: 176
    SEF: 176
    Source: ASS
    Status: OPN (shown as White row)
```

---

## 🎨 Row Colors Explained

The color of each row is determined by the `EARMARK_CT` field in `tpaccount`:

| EARMARK_CT | Meaning | Color in Table |
|------------|---------|----------------|
| OPN | Open (unpaid) | ⚪ White |
| INS | Installment | 🟢 Teal/Green |
| DBP | Double Post | 🟢 Teal/Green |
| PSD | Paid | ⚫ Gray |

---

## 🔍 Sample Data Walk-Through

### Example: ABELLA, ALEX (LOCAL_TIN: 0130030072580)

**Step 1: Check tpaccount**
```sql
SELECT * FROM tpaccount 
WHERE LOCAL_TIN = '0130030072580' AND EVENTOBJECT_CT = 'ASS';
```
Result: 4 records (2018, 2019, 2020, 2021)

**Step 2: Join with postingjournal**
```sql
-- Gets TDNO: 03-0005-01338
-- Gets RPTTAXDUE: 176.00, SEFTAXDUE: 176.00
```

**Step 3: Join with rptassessment**
```sql
-- Gets LANDASSESSEDVALUE: 0.00
-- Gets BLDGASSESSEDVALUE: 17600.00
-- Total: 17600.00
```

**Step 4: Join with property**
```sql
-- Gets PINNO: 013-03-0005-034-27-1002
```

**Final Result:**
| TD No | Year | PIN | Land | Improvements | Total | Basic | SEF | Source |
|-------|------|-----|------|--------------|-------|-------|-----|--------|
| 03-0005-01338 | 2021 | 013-03-0005-034-27-1002 | 0.00 | 17,600.00 | 17,600.00 | 176 | 176 | ASS |
| 03-0005-01338 | 2020 | 013-03-0005-034-27-1002 | 0.00 | 17,600.00 | 17,600.00 | 176 | 176 | ASS |
| 03-0005-01338 | 2019 | 013-03-0005-034-27-1002 | 0.00 | 17,600.00 | 17,600.00 | 176 | 176 | ASS |
| 03-0005-01338 | 2018 | 013-03-0005-034-27-1002 | 0.00 | 17,600.00 | 17,600.00 | 176 | 176 | ASS |

---

## 🧪 Test in phpMyAdmin (XAMPP)

### Step-by-Step Testing:

1. **Open phpMyAdmin**
   - Go to: http://localhost/phpmyadmin
   - Select your database

2. **Test Query 1: Check owner exists**
   ```sql
   SELECT LOCAL_TIN, OWNERNAME 
   FROM propertyowner 
   WHERE LOCAL_TIN = '0130030072580';
   ```
   Expected: ABELLA, ALEX

3. **Test Query 2: Check TPACCOUNT records**
   ```sql
   SELECT TAXYEAR, EVENTOBJECT_CT, EARMARK_CT, DEBITAMOUNT
   FROM tpaccount
   WHERE LOCAL_TIN = '0130030072580' AND EVENTOBJECT_CT = 'ASS'
   ORDER BY TAXYEAR DESC;
   ```
   Expected: 4 rows (2018-2021)

4. **Test Query 3: Full joined query**
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
   FROM tpaccount tp
   INNER JOIN postingjournal pj 
       ON tp.PROP_ID = pj.PROP_ID 
       AND tp.TAXYEAR = pj.TAXYEAR 
       AND tp.JOURNALID = pj.POSTINGJOURNAL_ID
   INNER JOIN rptassessment ra 
       ON pj.TDNO = ra.TDNO
   INNER JOIN property p 
       ON ra.PROP_ID = p.PROP_ID
   WHERE 
       tp.LOCAL_TIN = '0130030072580'
       AND tp.EVENTOBJECT_CT = 'ASS'
   ORDER BY pj.TDNO, tp.TAXYEAR DESC;
   ```
   Expected: 4 rows with all columns filled

---

## ❓ Common Questions

### Q: Why start from tpaccount instead of postingjournal?
**A:** Because the old VB app only shows records that have been initialized in TPACCOUNT. This is the correct behavior!

### Q: What if TPACCOUNT has no records?
**A:** Then no data will show in the table. This is normal - it means the taxpayer debit hasn't been initialized yet.

### Q: What does EVENTOBJECT_CT = 'ASS' mean?
**A:** ASS = Assessment. It's the base tax amount. Other types are PEN (penalty), DED (discount), TCR (credit).

### Q: Why do we join on 3 fields (PROP_ID, TAXYEAR, JOURNALID)?
**A:** Because the combination of these 3 fields uniquely identifies a posting entry. One property can have multiple years, and each year has a unique journal ID.

### Q: What if I want to show ALL records, not just ASS?
**A:** Remove the `AND tp.EVENTOBJECT_CT = 'ASS'` filter. But for the assessment table, we only want ASS records.

---

## 📝 Summary

**✅ Key Points:**
1. **tpaccount** is the main table - if no records here, no data shows
2. We only show **EVENTOBJECT_CT = 'ASS'** (assessment records)
3. Color coding comes from **EARMARK_CT** (OPN/INS/PSD)
4. Join on **3 fields**: PROP_ID, TAXYEAR, JOURNALID
5. The query joins **4 tables** to get all needed data

**✅ Files to Reference:**
- `SAMPLE_SQL_FOR_MANUAL_DEBIT.sql` - Copy-paste ready SQL queries
- `SQL_AND_FILES_REFERENCE.md` - Complete technical reference
- `MANUAL_DEBIT_INTEGRATION_GUIDE.md` - Full implementation guide

**✅ Next Steps:**
1. Run the sample SQL queries in phpMyAdmin
2. Study the table structures using `DESCRIBE tablename`
3. Test with different LOCAL_TIN values
4. Compare results with old VB app

---

**Good luck with your Manual Debit page! 🚀**

