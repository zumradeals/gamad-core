ALTER TABLE administrative_audit
    ADD COLUMN previous_hash CHAR(64) NOT NULL DEFAULT repeat('0', 64),
    ADD COLUMN record_hash CHAR(64) NOT NULL DEFAULT repeat('0', 64);

CREATE UNIQUE INDEX administrative_audit_record_hash_idx
    ON administrative_audit (record_hash);

CREATE OR REPLACE FUNCTION prevent_administrative_audit_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'administrative_audit is append-only';
END;
$$;

CREATE TRIGGER administrative_audit_no_update
BEFORE UPDATE ON administrative_audit
FOR EACH ROW EXECUTE FUNCTION prevent_administrative_audit_mutation();

CREATE TRIGGER administrative_audit_no_delete
BEFORE DELETE ON administrative_audit
FOR EACH ROW EXECUTE FUNCTION prevent_administrative_audit_mutation();
