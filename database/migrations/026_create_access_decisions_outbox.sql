-- ADR-0022 — a dedicated Outbox queue for AccessDecisionMade, isolated from
-- outbox_messages so its potentially high volume never crowds out
-- business-domain events. Same structure and delivery lifecycle as
-- outbox_messages (002_create_outbox_messages.sql, 003_add_outbox_delivery_lifecycle.sql).
CREATE TABLE access_decisions_outbox (
    id UUID PRIMARY KEY,
    aggregate_id VARCHAR(64) NOT NULL,
    event_name VARCHAR(128) NOT NULL,
    payload JSONB NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    recorded_at TIMESTAMPTZ NOT NULL,
    published_at TIMESTAMPTZ NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    available_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    locked_until TIMESTAMPTZ NULL,
    locked_by VARCHAR(128) NULL,
    CONSTRAINT access_decisions_outbox_attempts_non_negative_chk CHECK (attempts >= 0)
);

CREATE INDEX access_decisions_outbox_dispatchable_idx
    ON access_decisions_outbox (available_at, recorded_at, id)
    WHERE published_at IS NULL;

CREATE INDEX access_decisions_outbox_lock_expiry_idx
    ON access_decisions_outbox (locked_until)
    WHERE published_at IS NULL AND locked_until IS NOT NULL;

CREATE INDEX access_decisions_outbox_aggregate_idx
    ON access_decisions_outbox (aggregate_id, occurred_at);

CREATE INDEX access_decisions_outbox_event_name_idx
    ON access_decisions_outbox (event_name, occurred_at);
