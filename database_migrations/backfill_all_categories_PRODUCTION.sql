-- ============================================================================
-- BACKFILL incident_category for ALL still-NULL records  (PRODUCTION)
--
-- Fills the ~505 records that have no incident_category with a medical/OB
-- clinical category (or "Trauma (unspecified)" for generic wounds), mirroring
-- includes/functions.php -> classify_medical_category() exactly.
--
-- The trauma incidents (Vehicular Accident, Fall, Mauling, ...) were already
-- backfilled in a prior run; those rows are NOT NULL and are left untouched.
--
-- *** HOW TO RUN ***  (this is why earlier scripts changed 0 rows)
--   1. In phpMyAdmin, CLICK the `btrahnqi_pre_hospital_db` database on the LEFT
--      panel first.  Do NOT add `USE pre_hospital_db;` — that DB does not exist
--      in production and aborts the whole script.
--   2. Run STEP 1 (preview), then STEP 2 (backup), then STEP 3 (update),
--      then STEP 4 (re-check).  MariaDB 11.4 supports \bWORD\b boundaries.
--
-- Signal string (sig) mirrors the PHP classifier:
--   emergency_*_details + other_complaints + team_leader_notes
--   + chief_complaints (JSON, punctuation stripped)
--   + pseudo-tokens injected from structured fields:
--       __FASTPOS__  when any fast_* field = 'positive'   (=> Stroke/CVA)
--       __CPR__      when care_management mentions CPR     (=> Cardiac Arrest)
--       consciousness text (e.g. UNCONSCIOUS)             (=> Cardiac Arrest)
-- ============================================================================


-- ----------------------------------------------------------------------------
-- Reusable signal builder is inlined in each step (no stored routine needed).
-- ----------------------------------------------------------------------------

-- ====================  STEP 1 — PREVIEW (read-only)  ========================
SELECT inferred_category, COUNT(*) AS records
FROM (
  SELECT
    (CASE
      -- OB first (incl. vaginal bleeding in an obstetric context)
      WHEN emergency_ob = 1
        OR sig REGEXP 'LABOR|DELIVERY|BAG OF WATER|WATER DISCHARGE|MUCUS DISCHARGE|\\bG[0-9]P[0-9]|AMNIOTIC|OBSTETRIC|PREGNAN|VAGINAL BLEED'
        THEN 'Labor/Delivery (OB)'
      -- Generic trauma residue: wounds/injuries with no specific incident
      WHEN sig REGEXP 'LACERAT|LACEATED|WOUND|ABRASION|CONTUSION|SPORTS INJURY|WORK ?RELATED INJURY|WORK INJURY|FRACTURE|DISLOCAT|PUNCTURE'
        AND sig NOT REGEXP 'CHEST PAIN|ABDOMIN|EPIGASTR'
        THEN 'Trauma (unspecified)'
      WHEN sig REGEXP '__FASTPOS__|\\bCVA\\b|STROKE|FACIAL DROOP|SLURRED|ARM WEAKNESS|HEMIPARE|FACE DROOP' THEN 'Stroke/CVA'
      WHEN sig REGEXP 'UNRESPONSIVE|UNCONSCIOUS|UNCONCIOUS|\\(-\\) ?PULSE|NO PULSE|NEGATIVE PULSE|CARDIAC ARREST|__CPR__|\\bCPR\\b|ASYSTOLE' THEN 'Cardiac Arrest'
      WHEN sig REGEXP 'CHEST PAIN|ANGINA|MYOCARD|\\bMI\\b|PALPITATION' THEN 'Chest Pain/Cardiac'
      WHEN sig REGEXP 'DIFFICULTY ?OF ?BREATHING|SHORTNESS OF BREATH|\\bDOB\\b|DYSPNEA|ASTHMA|BREATHING' THEN 'Difficulty of Breathing'
      WHEN sig REGEXP 'SEIZURE|CONVULS|EPILEP' THEN 'Seizure'
      WHEN sig REGEXP 'HYPERTEN|HIGH BLOOD|ELEVATED BP|\\bHPN\\b' THEN 'Hypertension'
      WHEN sig REGEXP 'HYPOGLYCEM|HYPERGLYCEM|DIABET|HIGH SUGAR|LOW SUGAR|BLOOD SUGAR' THEN 'Diabetic/Glucose'
      WHEN sig REGEXP 'VOMIT|DIARRHEA|LOOSE STOOL|ABDOMINAL|STOMACH|GASTRO|HYPERACID|EPIGASTR|FLANK PAIN|\\bRLQ\\b|LOWER QUADRANT|BACK ?PAIN' THEN 'GI/Abdominal'
      WHEN sig REGEXP 'FEVER|CHILL|FLU|COUGH|COLDS|INFLUENZA|INFECTION' THEN 'Fever/Infection'
      WHEN sig REGEXP 'DIZZ|HEAD ?ACHE|VERTIGO|BLURRED VISION|FAINT|SYNCOPE' THEN 'Dizziness/Headache'
      WHEN sig REGEXP 'BODY ?WEAK|MALAISE|BODY ?PAIN|GENERALIZED WEAK|NUMBNESS|WEAKNESS' THEN 'Generalized Weakness'
      ELSE 'Other Medical (unspecified)'
    END) AS inferred_category
  FROM (
    SELECT
      emergency_ob,
      UPPER(CONCAT_WS(' ',
        COALESCE(emergency_trauma_details,''), COALESCE(emergency_general_details,''),
        COALESCE(emergency_medical_details,''), COALESCE(emergency_ob_details,''),
        COALESCE(other_complaints,''), COALESCE(team_leader_notes,''),
        REPLACE(REPLACE(REPLACE(COALESCE(chief_complaints,''),'[',''),']',''),'"',''),
        COALESCE(initial_consciousness,''),
        CASE WHEN LOWER(COALESCE(fast_face_drooping,''))='positive'
               OR LOWER(COALESCE(fast_arm_weakness,''))='positive'
               OR LOWER(COALESCE(fast_speech_difficulty,''))='positive'
               OR LOWER(COALESCE(fast_time_to_call,''))='positive'
             THEN '__FASTPOS__' ELSE '' END,
        CASE WHEN UPPER(COALESCE(care_management,'')) LIKE '%CPR%' THEN '__CPR__' ELSE '' END
      )) AS sig
    FROM prehospital_forms
    WHERE incident_category IS NULL
  ) s
) x
GROUP BY inferred_category
ORDER BY records DESC;


-- ====================  STEP 2 — BACKUP (run before STEP 3)  =================
-- Snapshot the rows about to change so you can roll back.
CREATE TABLE backup_incidentcat_medical_20260625 AS
SELECT id, incident_category
FROM prehospital_forms
WHERE incident_category IS NULL;


-- ====================  STEP 3 — UPDATE (writes the category)  ===============
-- Same CASE as STEP 1. Only NULL rows change; the 210 trauma rows are untouched.
UPDATE prehospital_forms pf
JOIN (
  SELECT
    id,
    emergency_ob,
    UPPER(CONCAT_WS(' ',
      COALESCE(emergency_trauma_details,''), COALESCE(emergency_general_details,''),
      COALESCE(emergency_medical_details,''), COALESCE(emergency_ob_details,''),
      COALESCE(other_complaints,''), COALESCE(team_leader_notes,''),
      REPLACE(REPLACE(REPLACE(COALESCE(chief_complaints,''),'[',''),']',''),'"',''),
      COALESCE(initial_consciousness,''),
      CASE WHEN LOWER(COALESCE(fast_face_drooping,''))='positive'
             OR LOWER(COALESCE(fast_arm_weakness,''))='positive'
             OR LOWER(COALESCE(fast_speech_difficulty,''))='positive'
             OR LOWER(COALESCE(fast_time_to_call,''))='positive'
           THEN '__FASTPOS__' ELSE '' END,
      CASE WHEN UPPER(COALESCE(care_management,'')) LIKE '%CPR%' THEN '__CPR__' ELSE '' END
    )) AS sig
  FROM prehospital_forms
) t ON t.id = pf.id
SET pf.incident_category = (CASE
    WHEN t.emergency_ob = 1
      OR t.sig REGEXP 'LABOR|DELIVERY|BAG OF WATER|WATER DISCHARGE|MUCUS DISCHARGE|\\bG[0-9]P[0-9]|AMNIOTIC|OBSTETRIC|PREGNAN|VAGINAL BLEED'
      THEN 'Labor/Delivery (OB)'
    WHEN t.sig REGEXP 'LACERAT|LACEATED|WOUND|ABRASION|CONTUSION|SPORTS INJURY|WORK ?RELATED INJURY|WORK INJURY|FRACTURE|DISLOCAT|PUNCTURE'
      AND t.sig NOT REGEXP 'CHEST PAIN|ABDOMIN|EPIGASTR'
      THEN 'Trauma (unspecified)'
    WHEN t.sig REGEXP '__FASTPOS__|\\bCVA\\b|STROKE|FACIAL DROOP|SLURRED|ARM WEAKNESS|HEMIPARE|FACE DROOP' THEN 'Stroke/CVA'
    WHEN t.sig REGEXP 'UNRESPONSIVE|UNCONSCIOUS|UNCONCIOUS|\\(-\\) ?PULSE|NO PULSE|NEGATIVE PULSE|CARDIAC ARREST|__CPR__|\\bCPR\\b|ASYSTOLE' THEN 'Cardiac Arrest'
    WHEN t.sig REGEXP 'CHEST PAIN|ANGINA|MYOCARD|\\bMI\\b|PALPITATION' THEN 'Chest Pain/Cardiac'
    WHEN t.sig REGEXP 'DIFFICULTY ?OF ?BREATHING|SHORTNESS OF BREATH|\\bDOB\\b|DYSPNEA|ASTHMA|BREATHING' THEN 'Difficulty of Breathing'
    WHEN t.sig REGEXP 'SEIZURE|CONVULS|EPILEP' THEN 'Seizure'
    WHEN t.sig REGEXP 'HYPERTEN|HIGH BLOOD|ELEVATED BP|\\bHPN\\b' THEN 'Hypertension'
    WHEN t.sig REGEXP 'HYPOGLYCEM|HYPERGLYCEM|DIABET|HIGH SUGAR|LOW SUGAR|BLOOD SUGAR' THEN 'Diabetic/Glucose'
    WHEN t.sig REGEXP 'VOMIT|DIARRHEA|LOOSE STOOL|ABDOMINAL|STOMACH|GASTRO|HYPERACID|EPIGASTR|FLANK PAIN|\\bRLQ\\b|LOWER QUADRANT|BACK ?PAIN' THEN 'GI/Abdominal'
    WHEN t.sig REGEXP 'FEVER|CHILL|FLU|COUGH|COLDS|INFLUENZA|INFECTION' THEN 'Fever/Infection'
    WHEN t.sig REGEXP 'DIZZ|HEAD ?ACHE|VERTIGO|BLURRED VISION|FAINT|SYNCOPE' THEN 'Dizziness/Headache'
    WHEN t.sig REGEXP 'BODY ?WEAK|MALAISE|BODY ?PAIN|GENERALIZED WEAK|NUMBNESS|WEAKNESS' THEN 'Generalized Weakness'
    ELSE 'Other Medical (unspecified)'
  END)
WHERE pf.incident_category IS NULL;


-- ====================  STEP 4 — RE-CHECK  ===================================
-- Should report 0 (every record now has a category).
SELECT COUNT(*) AS still_null FROM prehospital_forms WHERE incident_category IS NULL;

-- Final distribution across ALL records (trauma incidents + medical + OB):
SELECT incident_category, COUNT(*) AS records
FROM prehospital_forms
GROUP BY incident_category
ORDER BY records DESC;


-- ====================  ROLLBACK (only if needed)  ==========================
-- UPDATE prehospital_forms pf
-- JOIN backup_incidentcat_medical_20260625 b ON b.id = pf.id
-- SET pf.incident_category = b.incident_category;
-- DROP TABLE backup_incidentcat_medical_20260625;
