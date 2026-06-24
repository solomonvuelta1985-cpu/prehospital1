
-- ============================================================================
-- RECLASSIFY: records saved with NO emergency type checked
-- Production data fix. The form's Medical/Trauma/OB/General checkbox was left
-- blank on these COMPLETED records, but other fields reveal the type:
--   * injuries present                         -> Trauma
--   * narrative says TRAUMA / fall / VA / stab -> Trauma
--   * OB fields (LMP/EDC/delivery) or labor/   -> OB
--     water-discharge wording
--   * FAST stroke fields present               -> Medical
--   * narrative/complaint mentions a medical   -> Medical
--     complaint (pain, weakness, DOB, etc.)
-- Records with no usable signal are reported as UNKNOWN and left untouched.
--
-- HOW TO USE:
--   STEP 1 — run the PREVIEW (SELECT). Sanity-check the inferred types.
--   STEP 2 — (optional) run the BACKUP to snapshot the rows.
--   STEP 3 — run the UPDATE to apply. Only confidently-inferred rows change.
-- Re-run STEP 1 afterwards to confirm 0 rows remain mis-set.
-- ============================================================================

USE pre_hospital_db;

-- Shared inference expression is repeated in the preview and the update so the
-- file stays copy-paste friendly (no stored routine needed).

-- ----------------------------------------------------------------------------
-- STEP 1 — PREVIEW (read-only): what each no-type record WOULD become + why
-- ----------------------------------------------------------------------------
SELECT
    pf.id,
    pf.form_number,
    pf.form_date,
    (SELECT COUNT(*) FROM injuries i WHERE i.form_id = pf.id) AS injuries,
    CASE
        WHEN (SELECT COUNT(*) FROM injuries i WHERE i.form_id = pf.id) > 0 THEN 'Trauma'
        WHEN UPPER(COALESCE(pf.team_leader_notes,'')) REGEXP 'TRAUMA|VEHICULAR|MAULING|STAB|GUNSHOT|HACK|GORING|DROWN|BURN|\\bFELL\\b|\\bFALL\\b|INJUR|LACERAT|FRACTURE|WOUND' THEN 'Trauma'
        WHEN (pf.ob_delivery_time IS NOT NULL OR pf.ob_lmp IS NOT NULL OR pf.ob_aog IS NOT NULL OR pf.ob_edc IS NOT NULL
              OR (pf.ob_baby_status IS NOT NULL AND TRIM(pf.ob_baby_status) <> ''))
             OR UPPER(COALESCE(pf.team_leader_notes,'')) REGEXP 'OBSTETRIC|\\bOB\\b|LABOR|BAG OF WATER|WATER DISCHARGE|MUCUS DISCHARGE|\\bLMP\\b|\\bG[0-9]P[0-9]'
             OR UPPER(COALESCE(pf.other_complaints,'')) REGEXP 'LABOR|BAG OF WATER|WATER DISCHARGE|MUCUS DISCHARGE|\\bG[0-9]P[0-9]' THEN 'OB'
        WHEN (pf.fast_face_drooping IS NOT NULL OR pf.fast_arm_weakness IS NOT NULL OR pf.fast_speech_difficulty IS NOT NULL) THEN 'Medical'
        WHEN UPPER(COALESCE(pf.team_leader_notes,'')) REGEXP 'MEDICAL|\\bMED\\b|MED\\.' THEN 'Medical'
        WHEN UPPER(CONCAT_WS(' ', COALESCE(pf.other_complaints,''), COALESCE(pf.team_leader_notes,''))) REGEXP
             'PAIN|WEAKNESS|VOMIT|FEVER|DIZZ|COUGH|BREATH|\\bDOB\\b|HYPERTEN|ASTHMA|DIARRHEA|NUMBNESS|CHILL|BLOOD PRESSURE|HEAD ?ACHE|CHEST|SEIZURE|UNCONSCIOUS|UNCONCIOUS|UNRESPONSIVE|RASHES|COLDS|STOMACH|ABDOM|BACK ?PAIN|HYPOGLYCEM|SUGAR' THEN 'Medical'
        ELSE 'UNKNOWN — review manually'
    END AS inferred_type,
    pf.status,
    LEFT(COALESCE(pf.other_complaints,''), 40) AS complaint_text,
    LEFT(COALESCE(pf.team_leader_notes,''), 70) AS narrative
FROM prehospital_forms pf
WHERE COALESCE(pf.emergency_medical,0)=0
  AND COALESCE(pf.emergency_trauma,0)=0
  AND COALESCE(pf.emergency_ob,0)=0
  AND COALESCE(pf.emergency_general,0)=0
ORDER BY inferred_type, pf.id;


-- ----------------------------------------------------------------------------
-- STEP 2 — BACKUP (optional but recommended): snapshot affected rows first.
-- Lets you restore the four flag columns if anything looks wrong.
-- ----------------------------------------------------------------------------
CREATE TABLE backup_no_type_20260624 AS
SELECT id, emergency_medical, emergency_trauma, emergency_ob, emergency_general,
       other_complaints, team_leader_notes
FROM prehospital_forms
WHERE COALESCE(emergency_medical,0)=0 AND COALESCE(emergency_trauma,0)=0
  AND COALESCE(emergency_ob,0)=0 AND COALESCE(emergency_general,0)=0;


-- ----------------------------------------------------------------------------
-- STEP 3 — APPLY: set the single inferred flag. UNKNOWN rows are NOT touched
-- (the CASE returns the original 0 for them). Review STEP 1 before running.
-- ----------------------------------------------------------------------------
UPDATE prehospital_forms pf
SET
  emergency_trauma = CASE WHEN (
        (SELECT COUNT(*) FROM injuries i WHERE i.form_id = pf.id) > 0
     OR UPPER(COALESCE(pf.team_leader_notes,'')) REGEXP 'TRAUMA|VEHICULAR|MAULING|STAB|GUNSHOT|HACK|GORING|DROWN|BURN|\\bFELL\\b|\\bFALL\\b|INJUR|LACERAT|FRACTURE|WOUND'
  ) THEN 1 ELSE 0 END,
  emergency_ob = CASE WHEN (
        (SELECT COUNT(*) FROM injuries i WHERE i.form_id = pf.id) = 0
     AND NOT (UPPER(COALESCE(pf.team_leader_notes,'')) REGEXP 'TRAUMA|VEHICULAR|MAULING|STAB|GUNSHOT|HACK|GORING|DROWN|BURN|\\bFELL\\b|\\bFALL\\b|INJUR|LACERAT|FRACTURE|WOUND')
     AND (
          (pf.ob_delivery_time IS NOT NULL OR pf.ob_lmp IS NOT NULL OR pf.ob_aog IS NOT NULL OR pf.ob_edc IS NOT NULL
           OR (pf.ob_baby_status IS NOT NULL AND TRIM(pf.ob_baby_status) <> ''))
       OR UPPER(COALESCE(pf.team_leader_notes,'')) REGEXP 'OBSTETRIC|\\bOB\\b|LABOR|BAG OF WATER|WATER DISCHARGE|MUCUS DISCHARGE|\\bLMP\\b|\\bG[0-9]P[0-9]'
       OR UPPER(COALESCE(pf.other_complaints,'')) REGEXP 'LABOR|BAG OF WATER|WATER DISCHARGE|MUCUS DISCHARGE|\\bG[0-9]P[0-9]'
     )
  ) THEN 1 ELSE 0 END,
  emergency_medical = CASE WHEN (
        (SELECT COUNT(*) FROM injuries i WHERE i.form_id = pf.id) = 0
     AND NOT (UPPER(COALESCE(pf.team_leader_notes,'')) REGEXP 'TRAUMA|VEHICULAR|MAULING|STAB|GUNSHOT|HACK|GORING|DROWN|BURN|\\bFELL\\b|\\bFALL\\b|INJUR|LACERAT|FRACTURE|WOUND')
     AND NOT (
          (pf.ob_delivery_time IS NOT NULL OR pf.ob_lmp IS NOT NULL OR pf.ob_aog IS NOT NULL OR pf.ob_edc IS NOT NULL
           OR (pf.ob_baby_status IS NOT NULL AND TRIM(pf.ob_baby_status) <> ''))
       OR UPPER(COALESCE(pf.team_leader_notes,'')) REGEXP 'OBSTETRIC|\\bOB\\b|LABOR|BAG OF WATER|WATER DISCHARGE|MUCUS DISCHARGE|\\bLMP\\b|\\bG[0-9]P[0-9]'
       OR UPPER(COALESCE(pf.other_complaints,'')) REGEXP 'LABOR|BAG OF WATER|WATER DISCHARGE|MUCUS DISCHARGE|\\bG[0-9]P[0-9]'
     )
     AND (
          pf.fast_face_drooping IS NOT NULL OR pf.fast_arm_weakness IS NOT NULL OR pf.fast_speech_difficulty IS NOT NULL
       OR UPPER(COALESCE(pf.team_leader_notes,'')) REGEXP 'MEDICAL|\\bMED\\b|MED\\.'
       OR UPPER(CONCAT_WS(' ', COALESCE(pf.other_complaints,''), COALESCE(pf.team_leader_notes,''))) REGEXP
          'PAIN|WEAKNESS|VOMIT|FEVER|DIZZ|COUGH|BREATH|\\bDOB\\b|HYPERTEN|ASTHMA|DIARRHEA|NUMBNESS|CHILL|BLOOD PRESSURE|HEAD ?ACHE|CHEST|SEIZURE|UNCONSCIOUS|UNCONCIOUS|UNRESPONSIVE|RASHES|COLDS|STOMACH|ABDOM|BACK ?PAIN|HYPOGLYCEM|SUGAR'
     )
  ) THEN 1 ELSE 0 END
WHERE COALESCE(pf.emergency_medical,0)=0
  AND COALESCE(pf.emergency_trauma,0)=0
  AND COALESCE(pf.emergency_ob,0)=0
  AND COALESCE(pf.emergency_general,0)=0;

-- After STEP 3, re-run STEP 1: only 'UNKNOWN — review manually' rows should remain.
