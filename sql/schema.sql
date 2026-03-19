CREATE TABLE sessions (
    id TEXT PRIMARY KEY,
    user_id INTEGER,
    payload JSONB NOT NULL,
    expires_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL
);

CREATE INDEX idx_sessions_user_id ON sessions(user_id);
CREATE INDEX idx_sessions_expires_at ON sessions(expires_at);
CREATE INDEX idx_sessions_ip ON sessions((payload->>'ip'));
CREATE INDEX idx_sessions_fingerprint ON sessions((payload->>'fingerprint'));

CREATE TABLE api_stats (
    id BIGSERIAL PRIMARY KEY,
    session_id TEXT NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
    route TEXT NOT NULL,
    method TEXT NOT NULL,
    status_code INTEGER NOT NULL,
    execution_time REAL NOT NULL,
    request_time BIGINT NOT NULL
);

CREATE INDEX idx_api_stats_session_id ON api_stats(session_id);
CREATE INDEX idx_api_stats_route ON api_stats(route);
CREATE INDEX idx_api_stats_request_time ON api_stats(request_time);

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL
);
