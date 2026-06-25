-- ============================================================================
-- FIXED REMAP — phpMyAdmin / MySQL safe (NO \b word boundaries).
--
-- Why the previous UPDATE changed 0 rows: it used '\\bVA\\b', '\\bFALL\\b', etc.
-- MySQL's REGEXP engine does NOT support \b — those patterns matched nothing.
-- This version uses plain words + POSIX boundaries [[:<:]] [[:>:]] which MySQL
-- DOES support, so it matches the same way the working PREVIEW did.
--
-- Order: STEP 1 preview (read-only) -> STEP 2 backup -> STEP 3 update.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- STEP 1 — PREVIEW (read-only): what each NULL record WOULD be set to
-- ----------------------------------------------------------------------------
SELECT inferred_category, COUNT(*) AS records
FROM (
  SELECT
    (CASE
      WHEN UPPER(sig) REGEXP 'VEHICULAR|VEHICLE ACCIDENT|ROAD TRAFFIC|[[:<:]]RTA[[:>:]]|MOTORCYCLE|MOTOR ?VEHICLE|COLLISION|HIT AND RUN|SIDESWIPE|RUN OVER|LOSS OF CONTROL|[[:<:]]VA[[:>:]]|V/A' THEN 'Vehicular Accident'
      WHEN UPPER(sig) REGEXP 'MAUL'                                  THEN 'Mauling'
      WHEN UPPER(sig) REGEXP 'GORING|[[:<:]]GORED?[[:>:]]|CARABAO'   THEN 'Goring'
      WHEN UPPER(sig) REGEXP 'GUN ?SHOT|[[:<:]]GSW[[:>:]]'           THEN 'Gunshot'
      WHEN UPPER(sig) REGEXP 'STAB|KNIFE|BLADED'                     THEN 'Stabbing'
      WHEN UPPER(sig) REGEXP 'HACK'                                  THEN 'Hack Wound'
      WHEN UPPER(sig) REGEXP 'DROWN|SUBMER'                          THEN 'Drowning'
      WHEN UPPER(sig) REGEXP '[[:<:]]BITE[[:>:]]|DOG BITE|SNAKE|RABIES' THEN 'Animal Bite'
      WHEN UPPER(sig) REGEXP 'CHOKING|FBAO'                          THEN 'Choking'
      WHEN UPPER(sig) REGEXP 'STRANGL|[[:<:]]HANGING[[:>:]]|NOOSE'   THEN 'Strangulation'
      WHEN UPPER(sig) REGEXP 'ELECTRO|ELECTROCUT'                    THEN 'Electrocution'
      WHEN UPPER(sig) REGEXP 'CHEMICAL|INGEST|POISON|HERBICIDE|LASON' THEN 'Chemical Ingestion'
      WHEN UPPER(sig) REGEXP 'SEXUAL|[[:<:]]RAPE[[:>:]]|MOLEST'      THEN 'Sexual Harassment'
      WHEN UPPER(sig) REGEXP 'STONING'                              THEN 'Stoning'
      WHEN UPPER(sig) REGEXP 'FIRE INCIDENT|BURNED|BURNT|SCALD|FLAME'
           OR (UPPER(sig) REGEXP '[[:<:]]BURN[[:>:]]'
               AND UPPER(sig) NOT REGEXP 'BURNING MICTURITION|BURNING URINATION|BURNING SENSATION') THEN 'Burn'
      WHEN UPPER(sig) REGEXP '[[:<:]]FALL[[:>:]]|[[:<:]]FELL[[:>:]]|NAHULOG|SLIPPED' THEN 'Fall'
      ELSE NULL
    END) AS inferred_category
  FROM (
    SELECT CONCAT_WS(' ',
      COALESCE(emergency_trauma_details,''), COALESCE(emergency_general_details,''),
      COALESCE(emergency_medical_details,''), COALESCE(emergency_ob_details,''),
      COALESCE(other_complaints,''), COALESCE(team_leader_notes,'')) AS sig
    FROM prehospital_forms WHERE incident_category IS NULL
  ) s
) x
GROUP BY inferred_category ORDER BY records DESC;


-- ----------------------------------------------------------------------------
-- STEP 2 — BACKUP (run before STEP 3)
-- ----------------------------------------------------------------------------
-- CREATE TABLE backup_incidentcat_fix AS
-- SELECT id, incident_category FROM prehospital_forms WHERE incident_category IS NULL;


-- ----------------------------------------------------------------------------
-- STEP 3 — UPDATE (writes the category). Same CASE as preview.
-- ----------------------------------------------------------------------------
-- UPDATE prehospital_forms pf
-- JOIN (
--   SELECT id, CONCAT_WS(' ',
--     COALESCE(emergency_trauma_details,''), COALESCE(emergency_general_details,''),
--     COALESCE(emergency_medical_details,''), COALESCE(emergency_ob_details,''),
--     COALESCE(other_complaints,''), COALESCE(team_leader_notes,'')) AS sig
--   FROM prehospital_forms
-- ) t ON t.id = pf.id
-- SET pf.incident_category = (CASE
--     WHEN UPPER(sig) REGEXP 'VEHICULAR|VEHICLE ACCIDENT|ROAD TRAFFIC|[[:<:]]RTA[[:>:]]|MOTORCYCLE|MOTOR ?VEHICLE|COLLISION|HIT AND RUN|SIDESWIPE|RUN OVER|LOSS OF CONTROL|[[:<:]]VA[[:>:]]|V/A' THEN 'Vehicular Accident'
--     WHEN UPPER(sig) REGEXP 'MAUL'                                  THEN 'Mauling'
--     WHEN UPPER(sig) REGEXP 'GORING|[[:<:]]GORED?[[:>:]]|CARABAO'   THEN 'Goring'
--     WHEN UPPER(sig) REGEXP 'GUN ?SHOT|[[:<:]]GSW[[:>:]]'           THEN 'Gunshot'
--     WHEN UPPER(sig) REGEXP 'STAB|KNIFE|BLADED'                     THEN 'Stabbing'
--     WHEN UPPER(sig) REGEXP 'HACK'                                  THEN 'Hack Wound'
--     WHEN UPPER(sig) REGEXP 'DROWN|SUBMER'                          THEN 'Drowning'
--     WHEN UPPER(sig) REGEXP '[[:<:]]BITE[[:>:]]|DOG BITE|SNAKE|RABIES' THEN 'Animal Bite'
--     WHEN UPPER(sig) REGEXP 'CHOKING|FBAO'                          THEN 'Choking'
--     WHEN UPPER(sig) REGEXP 'STRANGL|[[:<:]]HANGING[[:>:]]|NOOSE'   THEN 'Strangulation'
--     WHEN UPPER(sig) REGEXP 'ELECTRO|ELECTROCUT'                    THEN 'Electrocution'
--     WHEN UPPER(sig) REGEXP 'CHEMICAL|INGEST|POISON|HERBICIDE|LASON' THEN 'Chemical Ingestion'
--     WHEN UPPER(sig) REGEXP 'SEXUAL|[[:<:]]RAPE[[:>:]]|MOLEST'      THEN 'Sexual Harassment'
--     WHEN UPPER(sig) REGEXP 'STONING'                              THEN 'Stoning'
--     WHEN UPPER(sig) REGEXP 'FIRE INCIDENT|BURNED|BURNT|SCALD|FLAME'
--          OR (UPPER(sig) REGEXP '[[:<:]]BURN[[:>:]]'
--              AND UPPER(sig) NOT REGEXP 'BURNING MICTURITION|BURNING URINATION|BURNING SENSATION') THEN 'Burn'
--     WHEN UPPER(sig) REGEXP '[[:<:]]FALL[[:>:]]|[[:<:]]FELL[[:>:]]|NAHULOG|SLIPPED' THEN 'Fall'
--     ELSE pf.incident_category
--   END)
-- WHERE pf.incident_category IS NULL;

-- After STEP 3, re-run STEP 1 — only 'NULL' (true medical/OB) rows should remain.
