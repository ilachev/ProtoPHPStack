# Инфраструктура и тестирование

## Runtime stack

Проект использует:

- PHP 8.5+
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

### Storage adapters

Основная конфигурация лежит в `config/storage.php`.

По умолчанию используется:

- `pgsql` как основной runtime adapter
- `sqlite` как лёгкий adapter для отдельных изолированных сценариев и тестов

Базовый quality gate шаблона не должен требовать поднятые внешние сервисы, но сам backend по умолчанию ориентирован на PostgreSQL.

### Redis

Используется как backend для RoadRunner KV.

Конфиг:

- `.rr.yaml`
- `config/cache.php`

## Docker services

`docker-compose.yml` поднимает:

- `db-postgres` для основного PostgreSQL runtime profile
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
- session payload в JSON-compatible field;
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

- `src/Platform/Storage/Migration/*`

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

Bootstrap:

- `vendor/autoload.php`

Свойства:

- не требуют внешнюю БД;
- не требуют RoadRunner KV;
- используются в default `task test` и `task verify`.

### Integration tests

Проверяют:

- реальное приложение через `App::handleRequest()`;
- PostgreSQL-backed flow;
- session creation/validation/fingerprinting;
- geolocation integration;
- api stats recording.

Bootstrap:

- `tests/Integration/bootstrap.php`

Свойства:

- требуют PostgreSQL profile;
- запускаются отдельным профилем через `task test:integration`.

## Как устроены integration tests

Bootstrap в `tests/Integration/bootstrap.php`:

- создаёт единый `App`;
- получает container;
- очищает `public` schema в PostgreSQL integration profile;
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
task sql:check
task sql:check:pg
task test:integration
task test:full
task verify
task verify:integration
task verify:full
```

`task verify` — основной локальный gate. Он запускает:

1. lint
2. phpstan
3. sql artifact consistency check
4. unit tests

`task verify:integration` запускает PostgreSQL-backed SQL validation и integration profile.

`task verify:full` объединяет оба контура.

## Что должна помнить LLM при изменениях

- Изменения в storage/repository/migrations нужно проверять через `task test:integration` или `task verify:full`.
- Изменения в `.proto` почти всегда требуют регенерации артефактов.
- Изменения в middleware могут затронуть integration tests, даже если unit tests зелёные.
- Из-за RoadRunner нельзя бездумно использовать растущие static caches и состояние в singleton-like сервисах.
- Любая реструктуризация должна сохранять быстрый автономный `task verify`.
