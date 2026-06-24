-- ============================================
-- DIAGNOSTIC QUERY: Records with NO emergency type checked
-- Finds prehospital_forms where none of Medical/Trauma/OB/General is selected.
-- Read-only — safe to run on production.
-- ============================================

-- 1) How many, and broken down by status (draft vs completed vs archived)
SELECT
    status,
    COUNT(*) AS records_with_no_type
FROM prehospital_forms
WHERE COALESCE(emergency_medical, 0)  = 0
  AND COALESCE(emergency_trauma, 0)   = 0
  AND COALESCE(emergency_ob, 0)       = 0
  AND COALESCE(emergency_general, 0)  = 0
GROUP BY status WITH ROLLUP;

-- 2) The actual records (so you can inspect / fix them)
SELECT
    id,
    form_number,
    form_date,
    patient_name,
    status,
    created_by,
    created_at,
    -- show any specify text that was typed even though no box was ticked
    emergency_medical_details,
    emergency_trauma_details,
    emergency_ob_details,
    emergency_general_details
FROM prehospital_forms
WHERE COALESCE(emergency_medical, 0)  = 0
  AND COALESCE(emergency_trauma, 0)   = 0
  AND COALESCE(emergency_ob, 0)       = 0
  AND COALESCE(emergency_general, 0)  = 0
ORDER BY created_at DESC;
