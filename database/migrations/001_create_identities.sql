CREATE TABLE identities (
    id VARCHAR(32) PRIMARY KEY,
    type VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    registered_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT identities_id_format_chk
        CHECK (id ~ '^GAM-[A-Z]{3}-[0-9]{6,}$'),
    CONSTRAINT identities_type_chk
        CHECK (type IN ('person', 'organization', 'application', 'service', 'agent', 'device', 'resource')),
    CONSTRAINT identities_status_chk
        CHECK (status IN ('draft', 'active', 'suspended', 'archived', 'revoked'))
);

CREATE INDEX identities_type_idx ON identities (type);
CREATE INDEX identities_status_idx ON identities (status);
