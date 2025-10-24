# 📋 MANUAL DEBIT PAGE - ASSESSMENT TABLE INTEGRATION GUIDE

## Overview
This guide shows how to populate the Assessment Table in the Manual Debit page with real data from the database using the same logic as the Payment Posting page.

---

## 🎯 What This Does

The Assessment Table displays tax assessment records for a selected taxpayer with:
- **Columns**: TD No, Year, PIN No, Land, Improvements, Total, Basic, SEF, Source
- **Color Coding**: 
  - ⚪ White = Open Account (`EARMARK_CT = 'OPN'`)
  - 🟢 Teal/Green = Installment/Double Post (`EARMARK_CT = 'INS'` or `'DBP'`)
  - ⚫ Gray = Paid Account (`EARMARK_CT = 'PSD'`)

---

## 📂 Files Modified

### Backend (Laravel)
1. **`app/Http/Controllers/Api/TaxDueController.php`** - Added `getAssessmentDetails()` method
2. **`routes/api.php`** - Added route for `/tax-due/assessments/{localTin}`

### Frontend (React)
1. **`main/src/pages/ManualDebit/ManualDebitPage.js`** - Updated to fetch and display real data
2. **`main/src/pages/ManualDebit/ManualDebitPage.css`** - Added color coding styles
3. **`main/src/services/api.js`** - Added `getAssessmentDetails()` API function (if not already there)

---

## 🔧 Implementation Steps

### STEP 1: Backend - Controller Method

The `getAssessmentDetails($localTin)` method in `TaxDueController.php` fetches assessment records from the database.

**SQL Logic:**
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
    tp.LOCAL_TIN = ?
    AND tp.EVENTOBJECT_CT = 'ASS'
GROUP BY 
    pj.TDNO, 
    tp.TAXYEAR, 
    p.PINNO, 
    ra.LANDASSESSEDVALUE, 
    ra.BLDGASSESSEDVALUE,
    pj.RPTTAXDUE,
    pj.SEFTAXDUE,
    tp.EVENTOBJECT_CT,
    tp.EARMARK_CT
ORDER BY 
    pj.TDNO ASC,
    tp.TAXYEAR DESC;
```

**Key Points:**
- Starts from `TPACCOUNT` (only shows initialized records)
- Joins with `POSTINGJOURNAL`, `RPTASSESSMENT`, and `PROPERTY`
- Filters by `EVENTOBJECT_CT = 'ASS'` (Assessment records only)
- Orders by TD No and Year descending (newest first)

---

### STEP 2: Backend - API Route

Added in `routes/api.php`:
```php
Route::get('/tax-due/assessments/{localTin}', [TaxDueController::class, 'getAssessmentDetails']);
```

**API Endpoint:**
```
GET http://localhost:8000/api/tax-due/assessments/{localTin}
```

**Response Format:**
```json
[
  {
    "tdNo": "03-0005-01338",
    "year": 2021,
    "pin": "013-03-0005-034-27-1002",
    "land": 0.00,
    "improvements": 17600.00,
    "total": 17600.00,
    "basic": 176.00,
    "sef": 176.00,
    "source": "ASS",
    "status": "open"
  },
  ...
]
```

---

### STEP 3: Frontend - API Service

In `main/src/services/api.js`, add:
```javascript
export const taxDueAPI = {
  // ... existing methods ...
  getAssessmentDetails: (localTin) => api.get(`/tax-due/assessments/${localTin}`)
};
```

---

### STEP 4: Frontend - React Component

**Key Changes in `ManualDebitPage.js`:**

1. **Import the API:**
   ```javascript
   import { taxDueAPI } from "../../services/api";
   ```

2. **Add State Variables:**
   ```javascript
   const [selectedOwner, setSelectedOwner] = useState(null);
   const [localTin, setLocalTin] = useState("");
   const [ownerName, setOwnerName] = useState("");
   const [assessments, setAssessments] = useState([]);
   const [loadingAssessments, setLoadingAssessments] = useState(false);
   const [errorAssessments, setErrorAssessments] = useState(null);
   ```

3. **Add useEffect to Fetch Data:**
   ```javascript
   useEffect(() => {
     if (!localTin) {
       setAssessments([]);
       return;
     }

     const fetchAssessments = async () => {
       setLoadingAssessments(true);
       setErrorAssessments(null);
       try {
         const response = await taxDueAPI.getAssessmentDetails(localTin);
         setAssessments(response.data);
       } catch (err) {
         console.error('Error fetching assessments:', err);
         setErrorAssessments('Failed to load assessments');
       } finally {
         setLoadingAssessments(false);
       }
     };

     fetchAssessments();
   }, [localTin]);
   ```

4. **Update OwnerSearchModal Callback:**
   ```javascript
   onSelectOwner={(owner) => {
     setSelectedOwner(owner);
     setLocalTin(owner.LOCAL_TIN);
     setOwnerName(owner.OWNERNAME);
     setShowOwnerModal(false);
   }}
   ```

5. **Update Table Rendering:**
   ```javascript
   {!loadingAssessments && !errorAssessments && assessments.length > 0 && (
     <table>
       <thead>
         <tr>
           <th>TD No</th>
           <th>Year</th>
           <th>PIN No</th>
           <th>Land</th>
           <th>Improvements</th>
           <th>Total</th>
           <th>Basic</th>
           <th>SEF</th>
           <th>Source</th>
         </tr>
       </thead>
       <tbody>
         {assessments.map((a, i) => (
           <tr key={i} className={`status-${a.status}`}>
             <td>{a.tdNo}</td>
             <td>{a.year}</td>
             <td>{a.pin}</td>
             <td className="text-right">{a.land.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
             <td className="text-right">{a.improvements.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
             <td className="text-right"><strong>{a.total.toLocaleString('en-US', { minimumFractionDigits: 2 })}</strong></td>
             <td className="text-right">{a.basic.toFixed(0)}</td>
             <td className="text-right">{a.sef.toFixed(0)}</td>
             <td className="text-center">{a.source}</td>
           </tr>
         ))}
       </tbody>
     </table>
   )}
   ```

---

### STEP 5: Frontend - CSS Styling

In `ManualDebitPage.css`, add color coding:
```css
/* Row color coding based on status */
.assessment-table tbody tr.status-open {
  background-color: #fff !important;
}
.assessment-table tbody tr.status-open td {
  background-color: #fff !important;
}

.assessment-table tbody tr.status-installment {
  background-color: #009688 !important;
  color: white !important;
}
.assessment-table tbody tr.status-installment td {
  background-color: #009688 !important;
  color: white !important;
}

.assessment-table tbody tr.status-paid {
  background-color: #888 !important;
  color: white !important;
}
.assessment-table tbody tr.status-paid td {
  background-color: #888 !important;
  color: white !important;
}
```

---

## 🧪 Testing

### Test Data:
```javascript
// Owner with assessment records
Local TIN: '0130030072580'
Owner Name: 'ABELLA, ALEX'
Expected: 4 records for TD 03-0005-01338 (Years 2018-2021)

// Owner with no TPACCOUNT records
Local TIN: '0130030052733'
Owner Name: 'GARCIA, ABRAHAM'
Expected: No records (message: "No assessment records found")
```

### How to Test:
1. Open Manual Debit page
2. Click **Search** button
3. Search for owner (e.g., "ABELLA")
4. Select the owner
5. Assessment table should populate with data
6. Verify:
   - ✅ Data matches database
   - ✅ Color coding is correct
   - ✅ Numbers are formatted properly
   - ✅ Total = Land + Improvements

---

## 🔑 Key Differences from Payment Posting Table

| Payment Posting (Tax Dues) | Manual Debit (Assessments) |
|----------------------------|----------------------------|
| Shows per TDNO (one property) | Shows ALL properties for LOCAL_TIN |
| Displays dues and totals | Displays assessment values & taxes |
| Filters by TDNO in WHERE | No TDNO filter |
| Shows ASS + PEN + DED + TCR | Shows only ASS records |
| Aggregates penalty/credits | Shows base assessment only |

---

## 📊 Database Tables Used

1. **`tpaccount`** - Source of truth (only shows initialized records)
2. **`postingjournal`** - Tax year and tax amounts (RPTTAXDUE, SEFTAXDUE)
3. **`rptassessment`** - TDNO and assessed values (LANDASSESSEDVALUE, BLDGASSESSEDVALUE)
4. **`property`** - PIN number

---

## ✅ Implementation Checklist

- [x] Backend method `getAssessmentDetails()` added to `TaxDueController.php`
- [x] API route added to `routes/api.php`
- [x] API function added to `api.js`
- [x] State variables added to `ManualDebitPage.js`
- [x] `useEffect` hook added to fetch data
- [x] Table rendering updated with real data
- [x] CSS color coding added
- [x] Owner selection callback updated
- [ ] Test with real data
- [ ] Verify color coding works
- [ ] Check performance with large datasets

---

## 🚀 Next Steps

After implementing this:
1. Test with multiple owners
2. Verify data accuracy against old VB app
3. Implement other button functionalities (Insert, Unpost, Repost, etc.)
4. Add pagination if needed for large datasets
5. Add filters (e.g., "Hide Cancelled")

---

## 💡 Tips for Your Teammate

1. **Always start from TPACCOUNT** - This ensures only initialized records are shown
2. **The color coding comes from EARMARK_CT** - Don't confuse it with other status fields
3. **Use the same SQL pattern** - It's proven to work in Payment Posting
4. **Test with known data first** - Use the test LOCAL_TINs provided
5. **Check Laravel logs** - If data doesn't appear, check `storage/logs/laravel.log`

---

## 🐛 Troubleshooting

### Problem: No data showing
**Solution:** Check if TPACCOUNT records exist for that LOCAL_TIN with `EVENTOBJECT_CT = 'ASS'`

### Problem: Color coding not working
**Solution:** Verify CSS classes are being applied (`status-open`, `status-installment`, `status-paid`)

### Problem: API error 500
**Solution:** Check `storage/logs/laravel.log` for SQL errors or missing columns

### Problem: Wrong data showing
**Solution:** Verify the SQL query joins are correct (3-way join on PROP_ID, TAXYEAR, JOURNALID)

---

## 📞 Need Help?

If you encounter issues:
1. Check the Laravel logs: `storage/logs/laravel.log`
2. Check browser console for JavaScript errors
3. Test the API endpoint directly in Postman/Thunder Client
4. Compare with Payment Posting implementation

---

**Created by:** Your Development Team  
**Date:** October 22, 2025  
**Project:** Parts Online - RPT System

