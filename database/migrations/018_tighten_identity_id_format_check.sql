-- ADR-0017 §1 — final tightening. Only apply once
-- bin/migrate-realm-legacy-identities --execute has rewritten every legacy
-- row (see 015_widen_identity_id_format_check.sql for why this is a
-- separate, later step, not folded into that one).
ALTER TABLE identities
    DROP CONSTRAINT identities_id_format_chk,
    ADD CONSTRAINT identities_id_format_chk
        CHECK (id ~ '^GAM-[A-Z0-9]{2,6}-[A-Z]{3}-[0-9]{6,}$');
