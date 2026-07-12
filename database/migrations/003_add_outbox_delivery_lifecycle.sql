ALTER TABLE outbox_messages
    ADD COLUMN available_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ADD COLUMN locked_until TIMESTAMPTZ NULL,
    ADD COLUMN locked_by VARCHAR(128) NULL;

DROP INDEX IF EXISTS outbox_unpublished_idx;

CREATE INDEX outbox_dispatchable_idx
    ON outbox_messages (available_at, recorded_at, id)
    WHERE published_at IS NULL;

CREATE INDEX outbox_lock_expiry_idx
    ON outbox_messages (locked_until)
    WHERE published_at IS NULL AND locked_until IS NOT NULL;

CREATE TABLE outbox_dead_letters (
    id UUID PRIMARY KEY,
    aggregate_id VARCHAR(64) NOT NULL,
    event_name VARCHAR(128) NOT NULL,
    payload JSONB NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    recorded_at TIMESTAMPTZ NOT NULL,
    attempts INTEGER NOT NULL,
    last_error TEXT NOT NULL,
    failed_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT dead_letter_attempts_positive_chk CHECK (attempts > 0)
);

CREATE INDEX outbox_dead_letters_event_idx
    ON outbox_dead_letters (event_name, failed_at);

CREATE INDEX outbox_dead_letters_aggregate_idx
    ON outbox_dead_letters (aggregate_id, failed_at);
