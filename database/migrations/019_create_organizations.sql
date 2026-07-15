CREATE TABLE organizations (
    identity_id VARCHAR(32) PRIMARY KEY REFERENCES identities (id),
    parent_id VARCHAR(32) REFERENCES organizations (identity_id),
    name VARCHAR(255) NOT NULL,
    status VARCHAR(32) NOT NULL,
    founded_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT organizations_status_chk
        CHECK (status IN ('active', 'inactive')),
    CONSTRAINT organizations_root_chk
        CHECK (parent_id IS NOT NULL OR identity_id = 'GAM-GAT-ORG-000001')
);

CREATE INDEX organizations_parent_idx ON organizations (parent_id);
CREATE INDEX organizations_status_idx ON organizations (status);
