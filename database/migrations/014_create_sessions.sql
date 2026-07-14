CREATE TABLE sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_account_id UUID NOT NULL REFERENCES user_accounts (id),
    authentication_method_id UUID NOT NULL REFERENCES authentication_methods (id),
    token_hash CHAR(64) NOT NULL,
    issued_at TIMESTAMPTZ NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    revoked_at TIMESTAMPTZ,
    CONSTRAINT sessions_token_hash_format_chk
        CHECK (token_hash ~ '^[0-9a-f]{64}$')
);

CREATE UNIQUE INDEX sessions_token_hash_uidx ON sessions (token_hash);
CREATE INDEX sessions_account_idx ON sessions (user_account_id);
CREATE INDEX sessions_expires_idx ON sessions (expires_at);
