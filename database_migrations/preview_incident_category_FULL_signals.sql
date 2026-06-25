-- ============================================================================
-- PREVIEW ONLY (read-only): re-categorize the "Uncategorized" records using
-- ALL available signals, not just the four emergency_*_details boxes.
--
-- Searches a combined text built from:
--   emergency_trauma_details + general_details + medical_details + ob_details
--   + other_complaints (the chief-complaint text)
--   + team_leader_notes  (the narrative — often where "VEHICULAR ACCIDENT" etc.
--                          actually lives)
-- plus structured signals (injuries -> Trauma context, OB fields -> OB context).
--
-- Use it to find incidents hiding in the narrative that the detail-only query
-- missed. Changes NOTHING — review the distribution, then decide on a backfill.
-- ============================================================================

USE pre_hospital_db;

-- Distribution: how the currently-uncategorized records WOULD classify
SELECT inferred_category, COUNT(*) AS records
FROM (
    SELECT
        pf.id,
        CASE
            -- VA first (most common, many spelling variants)
            WHEN UPPER(sig) REGEXP '\\bVA\\b|\\bV/A\\b|VEHICULAR|VEHICLE ACCIDENT|ROAD TRAFFIC|\\bRTA\\b|MOTOR ?(CYCLE|VEHICLE)|MOTORCYCLE|COLLISION|HIT AND RUN|SIDESWIPE|RUN OVER|LOSS OF CONTROL' THEN 'Vehicular Accident'
            WHEN UPPER(sig) REGEXP 'HACK'                                            THEN 'Hack Wound'
            WHEN UPPER(sig) REGEXP 'GUN ?SHOT|\\bGSW\\b|\\bSHOT\\b'                   THEN 'Gunshot'
            WHEN UPPER(sig) REGEXP 'STAB|KNIFE|BLADED'                               THEN 'Stabbing'
            WHEN UPPER(sig) REGEXP 'MAUL'                                            THEN 'Mauling'
            WHEN UPPER(sig) REGEXP '\\bGOR(E|ED|ING)?\\b|CARABAO|\\bBULL\\b'         THEN 'Goring'
            WHEN UPPER(sig) REGEXP '\\bBITE\\b|\\bBIT\\b|DOG BITE|SNAKE|RABIES'      THEN 'Animal Bite'
            WHEN UPPER(sig) REGEXP 'DROWN|SUBMER'                                    THEN 'Drowning'
            WHEN UPPER(sig) REGEXP 'CHOK|FBAO'                                       THEN 'Choking'
            WHEN UPPER(sig) REGEXP 'STRANGL|HANG(ING|ED)?|NOOSE'                     THEN 'Strangulation'
            WHEN UPPER(sig) REGEXP 'ELECTRO|ELECTRIC'                                THEN 'Electrocution'
            WHEN UPPER(sig) REGEXP 'CHEMICAL|INGEST|POISON|LASON'                    THEN 'Chemical Ingestion'
            WHEN UPPER(sig) REGEXP 'SEXUAL|HARASS|\\bRAPE\\b|MOLEST'                 THEN 'Sexual Harassment'
            WHEN UPPER(sig) REGEXP '\\bSTON(E|ED|ING)\\b'                            THEN 'Stoning'
            WHEN UPPER(sig) REGEXP '\\bBURN(S|ED|T)?\\b|SCALD|\\bPASO\\b'            THEN 'Burn'
            WHEN UPPER(sig) REGEXP '\\bFALL\\b|\\bFELL\\b|NAHULOG|SLIP(PED)?\\b'     THEN 'Fall'
            ELSE NULL  -- genuinely no incident category (true Medical/OB condition)
        END AS inferred_category
    FROM prehospital_forms pf
    JOIN (
        SELECT id,
               CONCAT_WS(' ',
                   COALESCE(emergency_trauma_details,''),
                   COALESCE(emergency_general_details,''),
                   COALESCE(emergency_medical_details,''),
                   COALESCE(emergency_ob_details,''),
                   COALESCE(other_complaints,''),
                   COALESCE(team_leader_notes,'')
               ) AS sig
        FROM prehospital_forms
    ) t ON t.id = pf.id
    WHERE pf.incident_category IS NULL   -- only the currently-uncategorized ones
) x
GROUP BY inferred_category
ORDER BY records DESC;


-- ----------------------------------------------------------------------------
-- Per-record detail (run this instead of the summary to eyeball matches).
-- Shows the inferred category and the narrative/complaint it matched on.
-- ----------------------------------------------------------------------------

