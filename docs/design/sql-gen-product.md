# `sql-gen` как продукт

Этот документ фиксирует целевую модель `sqlc-like` слоя для проекта.

## Зачем это нужно

Проект уже использует code generation как основную идею для transport слоя:

- `.proto` является источником истины для endpoint contracts;
- генератор производит PHP-код вокруг protobuf descriptors;
- runtime остаётся тонким.

Для persistence слой должен развиваться по похожему принципу:

- SQL остаётся источником истины;
- генератор производит typed PHP-код вокруг SQL queries;
- runtime остаётся тонким;
- проект не скатывается в ORM-heavy модель.

## Главный принцип

`sql-gen` не должен строить архитектуру поверх SQL.

Он должен делать только одно: брать явный SQL-запрос и генерировать вокруг него удобный typed PHP-контур.

То есть правильная модель здесь такая:

- не query builder как основной путь;
- не ORM как основной путь;
- не попытка описывать бизнес-сценарии абстракциями;
- а прямой SQL `1 в 1`, как в `sqlc`.

Если проекту нужен один запрос, значит в кодовой базе должен существовать один явный SQL-запрос.
Если проекту нужен миллион запросов, значит в кодовой базе будет миллион запросов. Это нормально и честно для сложного backend-а.

## Что считается хорошим результатом

Хороший `sql-gen` путь:

- сохраняет SQL читаемым и явным;
- не скрывает SQL за fluent API;
- проверяет запросы against PostgreSQL;
- генерирует typed params и, когда это уже реализовано, typed rows;
- оставляет runtime исполнение предельно простым.

## Что не нужно делать

- не строить ORM;
- не строить Active Record;
- не строить generic multi-database abstraction как главную цель;
- не пытаться описывать persistence policy через сложные PHP builders;
- не строить “SQL architecture” поверх реального SQL.

## Целевой input

Канонический input должен жить отдельно от runtime-кода, например:

- `sql/schema.sql`
- `sql/queries/session.sql`
- `sql/queries/api_stats.sql`

Формат должен быть близок к `sqlc`:

```sql
-- name: FindSessionById :one
SELECT id, user_id, payload, expires_at, created_at, updated_at
FROM sessions
WHERE id = :id;

-- name: FindSessionsByUserId :many
SELECT id, user_id, payload, expires_at, created_at, updated_at
FROM sessions
WHERE user_id = :user_id
ORDER BY created_at DESC;

-- name: DeleteExpiredSessions :exec
DELETE FROM sessions
WHERE expires_at < :now;
```

Для проекта предпочтительны именованные параметры `:param`, а не позиционные placeholders.

Типы для generated row classes должны резолвиться из SQL schema source, а не дублироваться в ручных PHP metadata files.

## Целевой output

Генератор должен создавать typed PHP-код, а не runtime arrays.

Целевая модель output:

- `*Params`
- `*Row` для `:one` и `:many`
- `*Query`
- `*Queries` facade для группы запросов из одного SQL-файла

Пример дерева:

- `gen/Generated/Sql/Session/FindSessionByIdParams.php`
- `gen/Generated/Sql/Session/SessionRow.php`
- `gen/Generated/Sql/Session/FindSessionByIdQuery.php`
- `gen/Generated/Sql/Session/SessionQueries.php`

### MVP первого этапа

На первом этапе допускается более узкий generated output:

- `*Params`
- `*Query`
- `*Queries`

То есть initial MVP не обязан сразу генерировать `*Row`.

Главное требование первого этапа:

- SQL уже становится source of truth;
- generated PHP перестаёт быть shape-массивами;
- runtime получает executable typed query objects.

### Следующий этап

Следующий обязательный шаг после MVP:

- `sql/schema.sql` становится SQL source of truth для column types;
- генератор начинает резолвить row fields из `SELECT` + schema SQL;
- `:one` и `:many` queries получают generated `*Row`.

## Runtime слой

Runtime должен оставаться тонким.

Достаточно небольшого набора reusable blocks:

- `SqlExecutor`
- опционально `TransactionManager`
- опционально маленький helper для row mapping

Runtime не должен заново интерпретировать SQL.
Он должен только исполнять generated query classes.

## Проверка запросов

Проект PostgreSQL-first, поэтому `sql-gen` должен ориентироваться именно на PostgreSQL.

Правильная модель валидации:

- `task sql:gen` — генерация PHP-кода из SQL;
- `task sql:check` — compile-time/integration-time проверка запросов against real PostgreSQL;
- default `task verify` не обязан требовать поднятую БД;
- более строгий full-gate может включать `sql:check`.

Это согласуется с текущим разделением между обычным локальным gate и PostgreSQL-dependent профилями.

## Как это соотносится с текущим storage слоем

Текущий `QueryBuilder` и repository support не обязаны исчезнуть сразу.

Но их стоит считать transitional path, а не идеальным конечным состоянием.

Целевое recommended направление:

- `sql/schema.sql` как SQL source of truth для column types;
- raw SQL в `sql/queries/*.sql`;
- generated typed query classes;
- тонкий executor;
- repository как тонкий адаптер над generated queries.

## Первый практический slice

Первым кандидатом должен быть `Session`.

Почему:

- это reusable capability, а не продуктовая фича;
- у него уже есть понятные SQL-сценарии;
- он хорошо подходит как эталонный migration path.

Минимальный MVP должен уметь покрыть:

- `findById`
- `findByUserId`
- `findAll`
- `deleteExpired`

## Критерий качества

`sql-gen` развивается правильно, если:

- SQL остаётся основным языком persistence;
- generated PHP уменьшает boilerplate, а не прячет запрос;
- PostgreSQL проверяется как реальный target;
- developer и LLM видят один явный source of truth: SQL файл.
