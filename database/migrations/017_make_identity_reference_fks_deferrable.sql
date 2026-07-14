-- Lets bin/migrate-realm-legacy-identities rewrite identities.id, persons.identity_id
-- and user_accounts.person_id consistently within one transaction (via
-- SET CONSTRAINTS ... DEFERRED at the top of that transaction only).
--
-- INITIALLY IMMEDIATE (not DEFERRED) keeps every other transaction's FK
-- checking exactly as strict as it is today — only a transaction that
-- explicitly opts in with SET CONSTRAINTS gets deferred checking.
ALTER TABLE persons
    ALTER CONSTRAINT persons_identity_id_fkey DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE user_accounts
    ALTER CONSTRAINT user_accounts_person_id_fkey DEFERRABLE INITIALLY IMMEDIATE;
