# Документация проекта

Документация специально сведена к короткому набору канонических файлов, чтобы человек и LLM не тратили время на чтение пересекающихся описаний одной и той же идеи.

## Канонический набор

1. [LLM Onboarding](./llm-onboarding.md) — главный обзор проекта: цель, структура, инварианты, зоны риска.
2. [Поток запроса](./architecture/request-lifecycle.md) — как запрос проходит через runtime и middleware.
3. [Protobuf и генерация кода](./architecture/protobuf-and-codegen.md) — как устроен transport-contract слой и codegen.
4. [Инфраструктура и тестирование](./development/infrastructure-and-testing.md) — PostgreSQL, Redis, миграции, quality gates и test profiles.
5. [Рекомендации по `protoc-php-gen`](./design/protoc-php-gen-guidelines.md) — правила для генерации и маппинга между Domain и Proto.

## Как читать

- Если нужен общий контекст, начинать с `llm-onboarding.md`.
- Если нужно понять runtime, читать `architecture/request-lifecycle.md`.
- Если меняется API surface, читать `architecture/protobuf-and-codegen.md`.
- Если меняется storage, cache, миграции или тестовый контур, читать `development/infrastructure-and-testing.md`.

## Базовые правила

- Источник истины для core API template — `protos/proto/app/v1`.
- Example contracts живут отдельно в `protos/proto/examples/v1`.
- Сгенерированные артефакты нельзя редактировать вручную.
- `Platform` должен оставаться маленьким runtime core.
- `Capabilities` должны оставаться простыми reusable-блоками.
- `Examples` должны быть явно вторичны по отношению к core runtime.
