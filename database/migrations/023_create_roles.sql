CREATE TABLE roles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(64) NOT NULL UNIQUE,
    scope VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT roles_scope_chk
        CHECK (scope IN ('realm', 'organization')),
    CONSTRAINT roles_status_chk
        CHECK (status IN ('active', 'deprecated'))
);

CREATE INDEX roles_status_idx ON roles (status);
