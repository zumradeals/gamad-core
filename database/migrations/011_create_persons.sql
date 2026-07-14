CREATE TABLE persons (
    identity_id VARCHAR(32) PRIMARY KEY REFERENCES identities (id),
    declared_name VARCHAR(255) NOT NULL,
    status VARCHAR(32) NOT NULL,
    registered_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT persons_status_chk
        CHECK (status IN ('active', 'inactive', 'deceased'))
);

CREATE INDEX persons_status_idx ON persons (status);
