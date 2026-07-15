CREATE TABLE departments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id VARCHAR(32) NOT NULL REFERENCES organizations (identity_id),
    name VARCHAR(255) NOT NULL,
    status VARCHAR(32) NOT NULL,
    CONSTRAINT departments_status_chk
        CHECK (status IN ('active', 'inactive'))
);

CREATE INDEX departments_organization_idx ON departments (organization_id);
CREATE INDEX departments_status_idx ON departments (status);
