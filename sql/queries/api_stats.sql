-- name: InsertApiStat :exec
INSERT INTO api_stats (
    session_id,
    route,
    method,
    status_code,
    execution_time,
    request_time
)
VALUES (
    :session_id,
    :route,
    :method,
    :status_code,
    :execution_time,
    :request_time
);
