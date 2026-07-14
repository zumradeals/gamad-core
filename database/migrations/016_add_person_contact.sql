-- GENESIS-010 §A describes a Person as having "un ou plusieurs moyens de
-- contact", but §D's logical schema sketch (which migration 011 matches
-- exactly) never concretized it. This adds the minimum needed today: a
-- single contact value, nullable at the DB level. A genuine multi-contact
-- model (a child table, mirroring authentication_methods) is deferred until
-- a real need for more than one contact per person shows up (MASTERPLAN-001 §5).
ALTER TABLE persons ADD COLUMN contact VARCHAR(320);
