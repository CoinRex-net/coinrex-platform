USE koinrex;

ALTER TABLE users
    ADD COLUMN language VARCHAR(10) NULL AFTER level;
