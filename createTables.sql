SELECT 'CREATE DATABASE pushusers'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'pushusers')\gexec

\c pushusers;

CREATE TABLE IF NOT EXISTS usertokens (
    deviceToken VARCHAR(255) PRIMARY KEY,
    user_id uuid NOT NULL
);
