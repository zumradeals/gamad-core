CREATE TABLE operational_metrics (
    name VARCHAR(128) NOT NULL,
    metric_type VARCHAR(16) NOT NULL,
    labels JSONB NOT NULL DEFAULT '{}'::JSONB,
    value DOUBLE PRECISION NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ NOT NULL,
    PRIMARY KEY (name, metric_type, labels),
    CONSTRAINT operational_metrics_type_chk CHECK (metric_type IN ('counter', 'gauge'))
);

CREATE INDEX operational_metrics_updated_idx
    ON operational_metrics (updated_at);
