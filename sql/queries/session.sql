-- name: FindSessionById :one
SELECT id, user_id, payload, expires_at, created_at, updated_at
FROM sessions
WHERE id = :id;

-- name: FindSessionsByUserId :many
SELECT id, user_id, payload, expires_at, created_at, updated_at
FROM sessions
WHERE user_id = :user_id
ORDER BY created_at DESC;

-- name: FindAllSessions :many
SELECT id, user_id, payload, expires_at, created_at, updated_at
FROM sessions
ORDER BY created_at DESC;

-- name: DeleteExpiredSessions :exec
DELETE FROM sessions
WHERE expires_at < :now;

-- name: UpsertSession :exec
INSERT INTO sessions (
    id,
    user_id,
    payload,
    expires_at,
    created_at,
    updated_at
)
VALUES (
    :id,
    :user_id,
    :payload,
    :expires_at,
    :created_at,
    :updated_at
)
ON CONFLICT (id) DO UPDATE SET
    user_id = EXCLUDED.user_id,
    payload = EXCLUDED.payload,
    expires_at = EXCLUDED.expires_at,
    created_at = EXCLUDED.created_at,
    updated_at = EXCLUDED.updated_at;

-- name: DeleteSessionById :exec
DELETE FROM sessions
WHERE id = :id;
