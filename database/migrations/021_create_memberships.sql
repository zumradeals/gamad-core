-- ADR-0020 — l'unicité "au plus un membership actif par personne par
-- organisation" est portée par l'index partiel ci-dessous, pas par une
-- contrainte UNIQUE classique, pour laisser subsister les memberships
-- terminés (GENESIS-011 §4 invariant 8).
CREATE TABLE memberships (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    person_id VARCHAR(32) NOT NULL REFERENCES persons (identity_id),
    organization_id VARCHAR(32) NOT NULL REFERENCES organizations (identity_id),
    department_id UUID REFERENCES departments (id),
    membership_type VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    started_at TIMESTAMPTZ NOT NULL,
    ended_at TIMESTAMPTZ,
    CONSTRAINT memberships_type_chk
        CHECK (membership_type IN ('GAMAD_CITIZEN', 'ORDINARY_CITIZEN', 'PARTNER')),
    CONSTRAINT memberships_status_chk
        CHECK (status IN ('active', 'suspended', 'ended'))
);

CREATE UNIQUE INDEX memberships_active_person_org_uidx
    ON memberships (person_id, organization_id)
    WHERE status = 'active';

CREATE INDEX memberships_organization_idx ON memberships (organization_id);
CREATE INDEX memberships_person_idx ON memberships (person_id);
CREATE INDEX memberships_status_idx ON memberships (status);
