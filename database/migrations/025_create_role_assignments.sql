-- GENESIS-014 §D — no structural FK to persons/organizations: those tables
-- belong to other bounded contexts (ADR-0013). Existence is verified
-- applicatively in AssignRoleHandler, exactly as Organizations and
-- Memberships verifies persons and identities before inserting.
CREATE TABLE role_assignments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role_id UUID NOT NULL REFERENCES roles (id),
    person_id VARCHAR(32) NOT NULL,
    organization_id VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    assigned_at TIMESTAMPTZ NOT NULL,
    revoked_at TIMESTAMPTZ,
    CONSTRAINT role_assignments_status_chk
        CHECK (status IN ('active', 'revoked'))
);

-- GENESIS-014 §D — only active assignments are constrained to uniqueness;
-- an actor may receive the same role in the same organization again after
-- a prior revocation, and that history is kept (same patron as ADR-0020).
CREATE UNIQUE INDEX role_assignments_active_uidx
    ON role_assignments (role_id, person_id, organization_id)
    WHERE status = 'active';

CREATE INDEX role_assignments_person_idx ON role_assignments (person_id);
CREATE INDEX role_assignments_organization_idx ON role_assignments (organization_id);
CREATE INDEX role_assignments_status_idx ON role_assignments (status);
