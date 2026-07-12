CREATE TABLE worker_heartbeats (
    worker_id VARCHAR(128) PRIMARY KEY,
    last_seen_at TIMESTAMPTZ NOT NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::JSONB
);

CREATE INDEX worker_heartbeats_last_seen_idx
    ON worker_heartbeats (last_seen_at);
