-- ==========================================
-- 📋 SAMPLE SQL FOR MANUAL DEBIT PAGE
-- Assessment Table Query
-- ==========================================

-- ==========================================
-- 🎯 PURPOSE:
-- This query fetches assessment records for the Manual Debit page
-- It shows: TD No, Year, PIN, Land value, Improvements, Total, Basic Tax, SEF, Source
-- ==========================================

-- ==========================================
-- 📊 STEP 1: Check if owner exists
-- ==========================================
-- Test with this owner first (known to have data)
SELECT 
    LOCAL_TIN,
    OWNERNAME
FROM propertyowner
WHERE LOCAL_TIN = '0130030072580';
-- Expected: ABELLA, ALEX


-- ==========================================
-- 📊 STEP 2: Check TPACCOUNT records
-- ==========================================
-- This is THE KEY TABLE - if no records here, no data will show!
SELECT 
    LOCAL_TIN,
    TAXYEAR,
    EVENTOBJECT_CT,
    EARMARK_CT,
    DEBITAMOUNT,
    PROP_ID,
    JOURNALID
FROM tpaccount
WHERE 
    LOCAL_TIN = '0130030072580'
    AND EVENTOBJECT_CT = 'ASS'
ORDER BY TAXYEAR DESC;
-- Expected: Should see multiple ASS records
-- EARMARK_CT values: OPN (open), INS (installment), PSD (paid)


-- ==========================================
-- 📊 STEP 3: FULL QUERY (What the system uses)
-- ==========================================
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
    tp.LOCAL_TIN = '0130030072580'           -- 👈 Change this to test different owners
    AND tp.EVENTOBJECT_CT = 'ASS'            -- Only Assessment records
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

-- ==========================================
-- Expected Result for ABELLA, ALEX (0130030072580):
-- ==========================================
-- TD No: 03-0005-01338, Year: 2021, PIN: 013-03-0005-034-27-1002, Land: 0.00, Improvements: 17600.00, Total: 17600.00, Basic: 176, SEF: 176, Source: ASS
-- TD No: 03-0005-01338, Year: 2020, PIN: 013-03-0005-034-27-1002, Land: 0.00, Improvements: 17600.00, Total: 17600.00, Basic: 176, SEF: 176, Source: ASS
-- TD No: 03-0005-01338, Year: 2019, PIN: 013-03-0005-034-27-1002, Land: 0.00, Improvements: 17600.00, Total: 17600.00, Basic: 176, SEF: 176, Source: ASS
-- TD No: 03-0005-01338, Year: 2018, PIN: 013-03-0005-034-27-1002, Land: 0.00, Improvements: 17600.00, Total: 17600.00, Basic: 176, SEF: 176, Source: ASS


-- ==========================================
-- 🔍 STEP 4: Test with different owners
-- ==========================================

-- Test Case 1: Owner with data (should show results)
-- Change the WHERE clause to:
-- WHERE tp.LOCAL_TIN = '0130030026473'  -- Rivera, Rodrigo

-- Test Case 2: Owner without TPACCOUNT records (should show NO results)
-- Change the WHERE clause to:
-- WHERE tp.LOCAL_TIN = '0130030052733'  -- Garcia, Abraham


-- ==========================================
-- 📚 TABLES USED AND THEIR PURPOSE:
-- ==========================================

-- TABLE 1: tpaccount (tp)
-- ✅ PRIMARY TABLE - This is the source of truth!
-- Contains: Assessment records (ASS), Penalties (PEN), Discounts (DED), Credits (TCR)
-- Key Columns:
--   - LOCAL_TIN: Taxpayer ID
--   - TAXYEAR: Tax year
--   - EVENTOBJECT_CT: Type of record (ASS, PEN, DED, TCR, TDF)
--   - EARMARK_CT: Payment status (OPN=open, INS=installment, PSD=paid)
--   - DEBITAMOUNT: Amount
--   - PROP_ID: Property ID
--   - JOURNALID: Links to postingjournal

SELECT 'Checking tpaccount table structure...' as Info;
DESCRIBE tpaccount;


-- TABLE 2: postingjournal (pj)
-- ✅ Contains tax year details and tax amounts
-- Key Columns:
--   - POSTINGJOURNAL_ID: Primary key
--   - TDNO: Tax Declaration Number
--   - TAXYEAR: Tax year
--   - PROP_ID: Property ID
--   - LOCAL_TIN: Taxpayer ID
--   - RPTTAXDUE: Basic Real Property Tax
--   - SEFTAXDUE: Special Education Fund Tax
--   - POSTED_BV: Posted flag (but we don't use this in our query!)
--   - CANCELLED_BV: Cancelled flag

SELECT 'Checking postingjournal table structure...' as Info;
DESCRIBE postingjournal;


-- TABLE 3: rptassessment (ra)
-- ✅ Contains property assessment values
-- Key Columns:
--   - TDNO: Tax Declaration Number
--   - PROP_ID: Property ID
--   - LANDASSESSEDVALUE: Assessed value of land
--   - BLDGASSESSEDVALUE: Assessed value of building/improvements
--   - STARTYEAR: First year of assessment
--   - ended_bv: Expired flag (0=active, 1=expired)

SELECT 'Checking rptassessment table structure...' as Info;
DESCRIBE rptassessment;


-- TABLE 4: property (p)
-- ✅ Contains property basic information
-- Key Columns:
--   - PROP_ID: Property ID (primary key)
--   - PINNO: Property Identification Number
--   - BARANGAY_CT: Barangay code
--   - PROPERTYKIND_CT: Property type code (Land/Building)

SELECT 'Checking property table structure...' as Info;
DESCRIBE property;


-- ==========================================
-- 🔗 HOW THE TABLES ARE JOINED:
-- ==========================================

-- 1. Start from: tpaccount (tp)
--    Filter: LOCAL_TIN and EVENTOBJECT_CT = 'ASS'
--    
-- 2. Join with: postingjournal (pj)
--    ON: tp.PROP_ID = pj.PROP_ID 
--        AND tp.TAXYEAR = pj.TAXYEAR 
--        AND tp.JOURNALID = pj.POSTINGJOURNAL_ID
--    Purpose: Get TDNO, RPTTAXDUE, SEFTAXDUE
--    
-- 3. Join with: rptassessment (ra)
--    ON: pj.TDNO = ra.TDNO
--    Purpose: Get LANDASSESSEDVALUE, BLDGASSESSEDVALUE
--    
-- 4. Join with: property (p)
--    ON: ra.PROP_ID = p.PROP_ID
--    Purpose: Get PINNO


-- ==========================================
-- 🎨 COLOR CODING LOGIC:
-- ==========================================

-- The color of each row depends on EARMARK_CT field:

SELECT 
    EARMARK_CT,
    CASE 
        WHEN EARMARK_CT = 'OPN' THEN 'White (Open Account)'
        WHEN EARMARK_CT = 'INS' THEN 'Teal/Green (Installment)'
        WHEN EARMARK_CT = 'DBP' THEN 'Teal/Green (Double Post)'
        WHEN EARMARK_CT = 'PSD' THEN 'Gray (Paid)'
        ELSE 'Other'
    END as ColorMeaning
FROM tpaccount
WHERE LOCAL_TIN = '0130030072580'
GROUP BY EARMARK_CT;


-- ==========================================
-- 🧪 DEBUGGING QUERIES
-- ==========================================

-- If no data shows, run these to debug:

-- 1. Check if owner exists in propertyowner
SELECT * FROM propertyowner WHERE LOCAL_TIN = '0130030072580';

-- 2. Check if TPACCOUNT records exist
SELECT COUNT(*) as record_count 
FROM tpaccount 
WHERE LOCAL_TIN = '0130030072580' AND EVENTOBJECT_CT = 'ASS';

-- 3. Check which properties the owner has
SELECT po.LOCAL_TIN, po.PROP_ID, p.PINNO, ra.TDNO
FROM propertyowner po
JOIN property p ON po.PROP_ID = p.PROP_ID
JOIN rptassessment ra ON p.PROP_ID = ra.PROP_ID
WHERE po.LOCAL_TIN = '0130030072580';

-- 4. Check if postingjournal exists for these properties
SELECT pj.TDNO, pj.TAXYEAR, pj.RPTTAXDUE, pj.SEFTAXDUE
FROM postingjournal pj
WHERE pj.LOCAL_TIN = '0130030072580';

-- 5. Check the join results step by step
SELECT tp.LOCAL_TIN, tp.TAXYEAR, tp.PROP_ID, tp.JOURNALID, pj.TDNO
FROM tpaccount tp
LEFT JOIN postingjournal pj 
    ON tp.PROP_ID = pj.PROP_ID 
    AND tp.TAXYEAR = pj.TAXYEAR 
    AND tp.JOURNALID = pj.POSTINGJOURNAL_ID
WHERE tp.LOCAL_TIN = '0130030072580' AND tp.EVENTOBJECT_CT = 'ASS';


-- ==========================================
-- 📝 NOTES FOR YOUR TEAMMATE:
-- ==========================================

-- ✅ IMPORTANT RULES:
-- 1. Always start from TPACCOUNT table, not postingjournal
-- 2. Only records with EVENTOBJECT_CT = 'ASS' show in assessment table
-- 3. If TPACCOUNT has no records, the table will be empty (this is correct!)
-- 4. The old VB app works the same way

-- ✅ TESTING TIPS:
-- 1. Run queries step by step (STEP 1, then STEP 2, then STEP 3)
-- 2. Use DESCRIBE commands to see table structure
-- 3. Check debugging queries if data doesn't match
-- 4. Compare results with old VB app

-- ✅ COMMON ISSUES:
-- Issue: "No data showing"
-- Solution: Check if TPACCOUNT records exist for that owner

-- Issue: "Different data than VB app"
-- Solution: Verify EARMARK_CT values (OPN/INS/PSD)

-- Issue: "JOIN returns no rows"
-- Solution: Check if PROP_ID, TAXYEAR, JOURNALID match between tables


-- ==========================================
-- 🎯 QUICK TEST (Copy-Paste Ready):
-- ==========================================

-- Test 1: Simple query to see all assessment records for ABELLA
SELECT * FROM tpaccount 
WHERE LOCAL_TIN = '0130030072580' AND EVENTOBJECT_CT = 'ASS'
ORDER BY TAXYEAR DESC;

-- Test 2: Full query with all joins
SELECT 
    pj.TDNO as 'TD No',
    tp.TAXYEAR as 'Year',
    p.PINNO as 'PIN',
    FORMAT(ra.LANDASSESSEDVALUE, 2) as 'Land',
    FORMAT(ra.BLDGASSESSEDVALUE, 2) as 'Improvements',
    FORMAT(ra.LANDASSESSEDVALUE + ra.BLDGASSESSEDVALUE, 2) as 'Total',
    pj.RPTTAXDUE as 'Basic',
    pj.SEFTAXDUE as 'SEF',
    tp.EVENTOBJECT_CT as 'Source',
    tp.EARMARK_CT as 'Status'
FROM tpaccount tp
INNER JOIN postingjournal pj ON tp.PROP_ID = pj.PROP_ID AND tp.TAXYEAR = pj.TAXYEAR AND tp.JOURNALID = pj.POSTINGJOURNAL_ID
INNER JOIN rptassessment ra ON pj.TDNO = ra.TDNO
INNER JOIN property p ON ra.PROP_ID = p.PROP_ID
WHERE tp.LOCAL_TIN = '0130030072580' AND tp.EVENTOBJECT_CT = 'ASS'
ORDER BY pj.TDNO, tp.TAXYEAR DESC;


-- ==========================================
-- ✅ ALL DONE! 
-- Your teammate can now:
-- 1. Copy this entire file
-- 2. Open phpMyAdmin in XAMPP
-- 3. Select the database
-- 4. Paste and run queries one by one
-- 5. Study the table structures
-- 6. Test with different LOCAL_TIN values
-- ==========================================

