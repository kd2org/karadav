ALTER TABLE users ADD COLUMN session_id TEXT NULL;

CREATE UNIQUE INDEX users_session_id ON users (session_id);
