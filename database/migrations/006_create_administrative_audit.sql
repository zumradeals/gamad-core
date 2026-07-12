CREATE TABLE administrative_audit (
    id BIGSERIAL PRIMARY KEY,
    actor_id VARCHAR(64) NOT NULL,
    action VARCHAR(128) NOT NULL,
    target TEXT NOT NULL,
    status_code INTEGER NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    context JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT administrative_audit_status_chk CHECK (status_code BETWEEN 100 AND 599)
);

CREATE INDEX administrative_audit_actor_idx
    ON administrative_audit (actor_id, occurred_at DESC);

CREATE INDEX administrative_audit_action_idx
    ON administrative_audit (action, occurred_at DESC);
