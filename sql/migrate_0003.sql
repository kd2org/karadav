ALTER TABLE users ADD COLUMN session_id TEXT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS users_session_id ON users (session_id);
