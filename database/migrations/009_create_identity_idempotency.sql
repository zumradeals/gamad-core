CREATE TABLE identity_idempotency (
    actor_id VARCHAR(64) NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    status_code INTEGER NOT NULL,
    response_body JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    PRIMARY KEY (actor_id, idempotency_key),
    CONSTRAINT identity_idempotency_status_chk CHECK (status_code BETWEEN 200 AND 599)
);

CREATE INDEX identity_idempotency_created_idx
    ON identity_idempotency (created_at);
