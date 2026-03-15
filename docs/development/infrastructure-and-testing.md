# Инфраструктура и тестирование

## Runtime stack

Проект использует:

- PHP 8.4
- RoadRunner HTTP worker
- PostgreSQL
- Redis через RoadRunner KV
- PHPUnit
- PHPStan
- PHP-CS-Fixer

## Конфигурация среды

### RoadRunner

Файл `.rr.yaml` определяет:

- запуск worker через `php public/index.php`;
- HTTP listener на `:8080`;
- RPC listener на `127.0.0.1:6001`;
- metrics/status endpoints;
- KV storage (`local-memory`, `redis`).

### PostgreSQL

Основная конфигурация лежит в `config/storage.php`.

По умолчанию используется:

- host: `localhost`
- port: `5432`
- database: `app`
- user: `app`
- password: `password`

Проектовая стратегия — использовать PostgreSQL во всех средах, включая тесты.

### Redis

Используется как backend для RoadRunner KV.

Конфиг:

- `.rr.yaml`
- `config/cache.php`

## Docker services

`docker-compose.yml` поднимает:

- `db-postgres`
- `redis`

Стандартный lifecycle:

```bash
task services:start
task services:stop
```

`services:start` также ждёт готовности БД и запускает миграции.

## Схема БД

### `sessions`

Назначение:

- хранение анонимных и пользовательских сессий;
- session payload в `JSONB`;
- индексы под поиск по `user_id`, `expires_at`, `payload->>'ip'`, `payload->>'fingerprint'`.

### `api_stats`

Назначение:

- запись вызовов API с привязкой к session;
- хранение route, method, status code, execution time, request timestamp.

### `users`

Сейчас это минимальная таблица для будущих auth/user сценариев.

## Миграции

Запуск:

```bash
task migrate
```

CLI wrapper:

- `bin/migrate.php`

Реализация миграций:

- `src/Infrastructure/Storage/Migration/PostgreSQL/*`

## Тесты

### Unit tests

Покрывают:

- domain services;
- middleware;
- router;
- DI container;
- hydrator;
- repositories;
- route generation.

### Integration tests

Проверяют:

- реальное приложение через `App::handleRequest()`;
- PostgreSQL-backed flow;
- session creation/validation/fingerprinting;
- geolocation integration;
- api stats recording.

## Как устроены integration tests

Bootstrap в `tests/Integration/bootstrap.php`:

- создаёт единый `App`;
- получает container;
- очищает public schema в PostgreSQL;
- заново прогоняет миграции;
- сохраняет shared app в `TestAppFactory`.

Следствие:

- интеграционные тесты не мокают runtime;
- tests ближе к реальному application flow;
- состояние базы подготавливается централизованно.

## Команды проверки качества

```bash
task lint
task phpstan
task test
task verify
```

`task verify` — основной gate. Он запускает:

1. lint
2. phpstan
3. cache clear
4. tests

## Что должна помнить LLM при изменениях

- Изменения в storage/repository/migrations нужно проверять на PostgreSQL.
- Изменения в `.proto` почти всегда требуют регенерации артефактов.
- Изменения в middleware могут затронуть integration tests, даже если unit tests зелёные.
- Из-за RoadRunner нельзя бездумно использовать растущие static caches и состояние в singleton-like сервисах.
- Любая реструктуризация должна сохранить или улучшить сценарий `task verify`.
