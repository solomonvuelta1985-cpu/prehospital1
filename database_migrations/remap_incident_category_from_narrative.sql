-- ============================================================================
-- ANALYZE + REMAP: pull incident categories out of the NARRATIVE (and complaint)
-- for records that are currently incident_category = NULL, then backfill them.
--
-- Why: many real incidents (goring, stoning, drowning, stab, mauling, V/A...)
-- were typed only into team_leader_notes / other_complaints, not the specify box,
-- so they show as "Uncategorized". This catches them.
--
-- FALSE-POSITIVE GUARDS (from real data review):
--   * 'BURNING MICTURITION' (a urinary symptom) must NOT become 'Burn'
--   * 'GALLSTONES' / 'STONE' must NOT become 'Stoning'  -> require word STONING
--   * require GUNSHOT / GUN SHOT (not bare 'SHOT')
--
-- SAFE WORKFLOW:
--   STEP 1 — run PREVIEW. Eyeball the category vs narrative for each record.
--   STEP 2 — (optional) BACKUP the rows.
--   STEP 3 — run UPDATE. Only confidently-matched rows get a category; the rest
--            stay NULL (true medical/OB conditions).
-- ============================================================================

USE pre_hospital_db;

-- The matching expression is identical in PREVIEW and UPDATE. Search a combined
-- "signal" string = the four detail boxes + other_complaints + team_leader_notes.

-- ----------------------------------------------------------------------------
-- STEP 1 — PREVIEW (read-only)
-- ----------------------------------------------------------------------------
SELECT
    pf.id, pf.form_number, pf.form_date,
    (CASE
        WHEN UPPER(sig) REGEXP '\\bVA\\b|\\bV/A\\b|VEHICULAR|VEHICLE ACCIDENT|ROAD TRAFFIC|\\bRTA\\b|MOTOR ?(CYCLE|VEHICLE)|MOTORCYCLE|COLLISION|HIT AND RUN|SIDESWIPE|RUN OVER|LOSS OF CONTROL' THEN 'Vehicular Accident'
        WHEN UPPER(sig) REGEXP 'MAUL'                                            THEN 'Mauling'
        WHEN UPPER(sig) REGEXP '\\bGOR(E|ED|ING)?\\b|CARABAO'                    THEN 'Goring'
        WHEN UPPER(sig) REGEXP 'GUN ?SHOT|\\bGSW\\b'                             THEN 'Gunshot'
        WHEN UPPER(sig) REGEXP 'STAB|KNIFE|BLADED'                              THEN 'Stabbing'
        WHEN UPPER(sig) REGEXP 'HACK'                                           THEN 'Hack Wound'
        WHEN UPPER(sig) REGEXP 'DROWN|SUBMER'                                   THEN 'Drowning'
        WHEN UPPER(sig) REGEXP '\\bBITE\\b|DOG BITE|SNAKE|RABIES'               THEN 'Animal Bite'
        WHEN UPPER(sig) REGEXP 'CHOK|FBAO'                                      THEN 'Choking'
        WHEN UPPER(sig) REGEXP 'STRANGL|\\bHANGING\\b|NOOSE'                    THEN 'Strangulation'
        WHEN UPPER(sig) REGEXP 'ELECTRO|ELECTROCUT'                             THEN 'Electrocution'
        WHEN UPPER(sig) REGEXP 'CHEMICAL|INGEST|POISON|LASON'                  THEN 'Chemical Ingestion'
        WHEN UPPER(sig) REGEXP 'SEXUAL|\\bRAPE\\b|MOLEST'                       THEN 'Sexual Harassment'
        WHEN UPPER(sig) REGEXP 'STONING'                                        THEN 'Stoning'           -- not 'STONE'/'GALLSTONE'
        WHEN UPPER(sig) REGEXP 'FIRE INCIDENT|BURNED|BURNT|SCALD|FLAME'
             OR (UPPER(sig) REGEXP '\\bBURN\\b' AND UPPER(sig) NOT REGEXP 'BURNING MICTURITION|BURNING URINATION|BURNING SENSATION') THEN 'Burn'
        WHEN UPPER(sig) REGEXP '\\bFALL\\b|\\bFELL\\b|NAHULOG|SLIPPED'          THEN 'Fall'
        ELSE NULL
    END) AS inferred_category,
    LEFT(COALESCE(pf.other_complaints,''),40)  AS complaint,
    LEFT(COALESCE(pf.team_leader_notes,''),70) AS narrative
FROM prehospital_forms pf
JOIN (
    SELECT id, CONCAT_WS(' ',
        COALESCE(emergency_trauma_details,''), COALESCE(emergency_general_details,''),
        COALESCE(emergency_medical_details,''), COALESCE(emergency_ob_details,''),
        COALESCE(other_complaints,''), COALESCE(team_leader_notes,'')) AS sig
    FROM prehospital_forms
) t ON t.id = pf.id
WHERE pf.incident_category IS NULL
HAVING inferred_category IS NOT NULL      -- show only the records that WOULD be remapped
ORDER BY inferred_category, pf.id;


-- ----------------------------------------------------------------------------
-- STEP 2 — BACKUP (optional): snapshot before changing.
-- ----------------------------------------------------------------------------
CREATE TABLE backup_incidentcat_20260624 AS
SELECT id, incident_category, other_complaints, team_leader_notes,
       emergency_trauma_details, emergency_general_details,
       emergency_medical_details, emergency_ob_details
FROM prehospital_forms WHERE incident_category IS NULL;


-- ----------------------------------------------------------------------------
-- STEP 3 — REMAP (UPDATE). Same CASE; writes the category only when matched.
-- NULL results are left as-is (genuine medical/OB conditions).
-- ----------------------------------------------------------------------------
UPDATE prehospital_forms pf
JOIN (
    SELECT id, CONCAT_WS(' ',
        COALESCE(emergency_trauma_details,''), COALESCE(emergency_general_details,''),
        COALESCE(emergency_medical_details,''), COALESCE(emergency_ob_details,''),
        COALESCE(other_complaints,''), COALESCE(team_leader_notes,'')) AS sig
    FROM prehospital_forms
) t ON t.id = pf.id
SET pf.incident_category = (CASE
        WHEN UPPER(sig) REGEXP '\\bVA\\b|\\bV/A\\b|VEHICULAR|VEHICLE ACCIDENT|ROAD TRAFFIC|\\bRTA\\b|MOTOR ?(CYCLE|VEHICLE)|MOTORCYCLE|COLLISION|HIT AND RUN|SIDESWIPE|RUN OVER|LOSS OF CONTROL' THEN 'Vehicular Accident'
        WHEN UPPER(sig) REGEXP 'MAUL'                                            THEN 'Mauling'
        WHEN UPPER(sig) REGEXP '\\bGOR(E|ED|ING)?\\b|CARABAO'                    THEN 'Goring'
        WHEN UPPER(sig) REGEXP 'GUN ?SHOT|\\bGSW\\b'                             THEN 'Gunshot'
        WHEN UPPER(sig) REGEXP 'STAB|KNIFE|BLADED'                              THEN 'Stabbing'
        WHEN UPPER(sig) REGEXP 'HACK'                                           THEN 'Hack Wound'
        WHEN UPPER(sig) REGEXP 'DROWN|SUBMER'                                   THEN 'Drowning'
        WHEN UPPER(sig) REGEXP '\\bBITE\\b|DOG BITE|SNAKE|RABIES'               THEN 'Animal Bite'
        WHEN UPPER(sig) REGEXP 'CHOK|FBAO'                                      THEN 'Choking'
        WHEN UPPER(sig) REGEXP 'STRANGL|\\bHANGING\\b|NOOSE'                    THEN 'Strangulation'
        WHEN UPPER(sig) REGEXP 'ELECTRO|ELECTROCUT'                             THEN 'Electrocution'
        WHEN UPPER(sig) REGEXP 'CHEMICAL|INGEST|POISON|LASON'                  THEN 'Chemical Ingestion'
        WHEN UPPER(sig) REGEXP 'SEXUAL|\\bRAPE\\b|MOLEST'                       THEN 'Sexual Harassment'
        WHEN UPPER(sig) REGEXP 'STONING'                                        THEN 'Stoning'
        WHEN UPPER(sig) REGEXP 'FIRE INCIDENT|\\bBURNED\\b|\\bBURNT\\b|SCALD|FLAME'
             OR (UPPER(sig) REGEXP '\\bBURN\\b' AND UPPER(sig) NOT REGEXP 'BURNING MICTURITION|BURNING URINATION|BURNING SENSATION') THEN 'Burn'
        WHEN UPPER(sig) REGEXP '\\bFALL\\b|\\bFELL\\b|NAHULOG|SLIPPED'          THEN 'Fall'
        ELSE pf.incident_category   -- unchanged (stays NULL)
    END)
WHERE pf.incident_category IS NULL;

-- After STEP 3, re-run STEP 1 — it should return 0 rows (all matchable ones done).
