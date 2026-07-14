CREATE TABLE authentication_methods (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_account_id UUID NOT NULL REFERENCES user_accounts (id),
    method_type VARCHAR(32) NOT NULL,
    credential_ref TEXT NOT NULL,
    added_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT authentication_methods_method_type_chk
        CHECK (method_type IN ('password', 'oidc_external'))
);

CREATE INDEX authentication_methods_account_idx ON authentication_methods (user_account_id);
