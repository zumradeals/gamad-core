-- ADR-0017 §1 — transitional widening: accepts EITHER the old 3-segment
-- format (GAM-{TYPE}-{NUMERO}) or the new realm-tagged format
-- (GAM-{REALM}-{TYPE}-{NUMERO}).
--
-- A single strict constraint cannot work here: tightening straight to the
-- new-only pattern would reject every pre-existing legacy row before
-- bin/migrate-realm-legacy-identities has a chance to rewrite them, and
-- keeping the old-only pattern would reject the very rows the migration
-- writes. This transitional constraint is safe to apply any time before
-- running the migration script; 018_tighten_identity_id_format_check.sql
-- narrows it to new-format-only once every row has been rewritten.
ALTER TABLE identities
    DROP CONSTRAINT identities_id_format_chk,
    ADD CONSTRAINT identities_id_format_chk
        CHECK (
            id ~ '^GAM-[A-Z]{3}-[0-9]{6,}$'
            OR id ~ '^GAM-[A-Z0-9]{2,6}-[A-Z]{3}-[0-9]{6,}$'
        );
