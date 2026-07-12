CREATE TABLE rate_limit_buckets (
    bucket_key CHAR(64) NOT NULL,
    window_started_at TIMESTAMPTZ NOT NULL,
    hits INTEGER NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    PRIMARY KEY (bucket_key, window_started_at),
    CONSTRAINT rate_limit_hits_positive_chk CHECK (hits > 0)
);

CREATE INDEX rate_limit_buckets_expiry_idx
    ON rate_limit_buckets (expires_at);
