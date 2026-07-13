CREATE EXTENSION IF NOT EXISTS pgcrypto;

ALTER TABLE identities
    ADD COLUMN internal_id UUID;

UPDATE identities
SET internal_id = gen_random_uuid()
WHERE internal_id IS NULL;

ALTER TABLE identities
    ALTER COLUMN internal_id SET NOT NULL;

CREATE UNIQUE INDEX identities_internal_id_uidx
    ON identities (internal_id);

CREATE TABLE identity_identifier_sequences (
    identity_type VARCHAR(32) PRIMARY KEY,
    prefix CHAR(3) NOT NULL UNIQUE,
    last_value BIGINT NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT identity_identifier_sequence_non_negative_chk CHECK (last_value >= 0)
);

INSERT INTO identity_identifier_sequences (identity_type, prefix)
VALUES
    ('person', 'PER'),
    ('organization', 'ORG'),
    ('application', 'APP'),
    ('service', 'SRV'),
    ('agent', 'AGT'),
    ('device', 'DEV'),
    ('resource', 'RES')
ON CONFLICT (identity_type) DO NOTHING;
