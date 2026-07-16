-- GENESIS-014 §B — Permission has no lifecycle of its own (no suspend, no
-- reactivate): it is a named value object, stored in its own table for
-- readability, without a transactional boundary of its own.
CREATE TABLE permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(128) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
