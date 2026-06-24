-- ============================================================================
-- PREVIEW ONLY (read-only): how the existing records WOULD be categorized
-- into incident_category from the free-text emergency_*_details fields.
--
-- This does NOT change any data. It shows, for every form, the category the
-- backfill would assign (or NULL = stays Uncategorized) plus the text matched.
-- Run it on production to see the distribution BEFORE deciding to backfill.
--
-- Ordered keyword logic mirrors the app's classifier: first match wins,
-- VA detected from common variants (V/A, vehicular, loss of control, etc.).
-- ============================================================================

USE pre_hospital_db;

-- Distribution summary: how many records map to each category
SELECT inferred_category, COUNT(*) AS records
FROM (
    SELECT
        pf.id,
        CASE
            WHEN UPPER(d) REGEXP '\\bVA\\b|\\bV/A\\b|VEHICULAR|VEHICLE ACCIDENT|ROAD TRAFFIC|\\bRTA\\b|MOTOR ?(CYCLE|VEHICLE)|MOTORCYCLE|COLLISION|HIT AND RUN|SIDESWIPE|RUN OVER|LOSS OF CONTROL' THEN 'Vehicular Accident'
            WHEN UPPER(d) REGEXP 'HACK'                                            THEN 'Hack Wound'
            WHEN UPPER(d) REGEXP 'GUN ?SHOT|\\bGSW\\b|\\bSHOT\\b'                   THEN 'Gunshot'
            WHEN UPPER(d) REGEXP 'STAB|KNIFE|BLADED'                               THEN 'Stabbing'
            WHEN UPPER(d) REGEXP 'MAUL'                                            THEN 'Mauling'
            WHEN UPPER(d) REGEXP '\\bGOR(E|ED|ING)?\\b|CARABAO|\\bBULL\\b'         THEN 'Goring'
            WHEN UPPER(d) REGEXP '\\bBITE\\b|\\bBIT\\b|DOG BITE|SNAKE|RABIES|ANIMAL' THEN 'Animal Bite'
            WHEN UPPER(d) REGEXP 'DROWN|SUBMER'                                    THEN 'Drowning'
            WHEN UPPER(d) REGEXP 'CHOK|FBAO'                                       THEN 'Choking'
            WHEN UPPER(d) REGEXP 'STRANGL|HANG(ING|ED)?|NOOSE'                     THEN 'Strangulation'
            WHEN UPPER(d) REGEXP 'ELECTRO|ELECTRIC'                                THEN 'Electrocution'
            WHEN UPPER(d) REGEXP 'CHEMICAL|INGEST|POISON|LASON|OVERDOSE|\\bOD\\b'  THEN 'Chemical Ingestion'
            WHEN UPPER(d) REGEXP 'SEXUAL|HARASS|\\bRAPE\\b|MOLEST|ABUSE'           THEN 'Sexual Harassment'
            WHEN UPPER(d) REGEXP '\\bSTON(E|ED|ING)\\b'                            THEN 'Stoning'
            WHEN UPPER(d) REGEXP '\\bBURN(S|ED|T)?\\b|SCALD|\\bPASO\\b'            THEN 'Burn'
            WHEN UPPER(d) REGEXP '\\bFALL\\b|\\bFELL\\b|NAHULOG|SLIP(PED)?\\b'     THEN 'Fall'
            ELSE NULL
        END AS inferred_category
    FROM prehospital_forms pf
    -- Combine the four detail fields so a category typed in any of them is caught.
    JOIN (SELECT id,
                 CONCAT_WS(' ',
                     COALESCE(emergency_trauma_details,''),
                     COALESCE(emergency_general_details,''),
                     COALESCE(emergency_medical_details,''),
                     COALESCE(emergency_ob_details,'')
                 ) AS d
          FROM prehospital_forms) t ON t.id = pf.id
) x
GROUP BY inferred_category
ORDER BY records DESC;


-- ----------------------------------------------------------------------------
-- Per-record detail: see exactly which records map where (and the source text).
-- Comment out the summary above and run this if you want the full list.
-- ----------------------------------------------------------------------------
SELECT
    pf.id, pf.form_number, pf.form_date,
    CASE
        WHEN UPPER(CONCAT_WS(' ',COALESCE(emergency_trauma_details,''),COALESCE(emergency_general_details,''),COALESCE(emergency_medical_details,''),COALESCE(emergency_ob_details,'')))
             REGEXP '\\bVA\\b|\\bV/A\\b|VEHICULAR|LOSS OF CONTROL|MOTORCYCLE|COLLISION' THEN 'Vehicular Accident'
        WHEN UPPER(CONCAT_WS(' ',COALESCE(emergency_trauma_details,''),COALESCE(emergency_general_details,''))) REGEXP 'MAUL' THEN 'Mauling'
        WHEN UPPER(CONCAT_WS(' ',COALESCE(emergency_trauma_details,''),COALESCE(emergency_general_details,''))) REGEXP '\\bFALL\\b|\\bFELL\\b' THEN 'Fall'
        ELSE '(other / none — see full classifier)'
    END AS inferred_category,
    emergency_trauma_details, emergency_general_details, emergency_medical_details, emergency_ob_details
FROM prehospital_forms pf
WHERE incident_category IS NULL
ORDER BY inferred_category, pf.id;
