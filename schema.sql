CREATE TABLE users (
	login TEXT NOT NULL PRIMARY KEY,
	password TEXT NOT NULL,
	quota INTEGER NULL,
	is_admin INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE locks (
	user TEXT NOT NULL REFERENCES users(login) ON DELETE CASCADE,
	uri TEXT NOT NULL,
	token TEXT NOT NULL,
	scope TEXT NOT NULL,
	expiry TEXT NOT NULL
);

CREATE INDEX locks_uri ON locks (user, uri);

CREATE UNIQUE INDEX locks_unique ON locks (user, uri, token);

CREATE TABLE app_sessions (
	user TEXT NOT NULL REFERENCES users(login) ON DELETE CASCADE,
	token TEXT NULL, -- Temporary token, exchanged for an app password
	user_agent TEXT NULL,
	password TEXT NULL,
	expiry TEXT NOT NULL
);

CREATE INDEX app_sessions_idx ON app_sessions (user);
CREATE UNIQUE INDEX app_sessions_token ON app_sessions (token);

-- Files properties stored using PROPPATCH
-- We are not using this currently, this is just to get test coverage from litmus
CREATE TABLE properties (
	user TEXT NOT NULL REFERENCES users(login) ON DELETE CASCADE,
	uri TEXT NOT NULL,
	name TEXT NOT NULL,
	attributes TEXT NULL,
	xml TEXT NULL
);

CREATE UNIQUE INDEX properties_unique ON properties (user, uri, name);
