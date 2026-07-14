CREATE TABLE user_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    person_id VARCHAR(32) NOT NULL UNIQUE REFERENCES persons (identity_id),
    status VARCHAR(32) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT user_accounts_status_chk
        CHECK (status IN ('active', 'suspended', 'disabled'))
);

CREATE INDEX user_accounts_status_idx ON user_accounts (status);
