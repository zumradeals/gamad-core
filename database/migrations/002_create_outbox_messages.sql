CREATE TABLE outbox_messages (
    id UUID PRIMARY KEY,
    aggregate_id VARCHAR(64) NOT NULL,
    event_name VARCHAR(128) NOT NULL,
    payload JSONB NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    recorded_at TIMESTAMPTZ NOT NULL,
    published_at TIMESTAMPTZ NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    CONSTRAINT outbox_attempts_non_negative_chk CHECK (attempts >= 0)
);

CREATE INDEX outbox_unpublished_idx
    ON outbox_messages (recorded_at, id)
    WHERE published_at IS NULL;

CREATE INDEX outbox_aggregate_idx
    ON outbox_messages (aggregate_id, occurred_at);

CREATE INDEX outbox_event_name_idx
    ON outbox_messages (event_name, occurred_at);
