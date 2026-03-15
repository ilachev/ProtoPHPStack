# Документация проекта

Этот каталог нужен как стартовая точка для человека или LLM, которым нужно быстро понять текущую систему, безопасно вносить изменения и готовить проект к реструктуризации.

## С чего начинать

1. [LLM Onboarding](./llm-onboarding.md) — краткая карта проекта, точки входа, инварианты и целевое направление.
2. [Vision шаблона](./restructure/template-vision.md) — официальная цель проекта и архитектурные принципы.
3. [Целевая архитектура](./architecture/target-architecture.md) — `Platform`, `Capabilities`, `Examples` и целевая структура каталогов.
4. [Текущая архитектура](./architecture/current-architecture.md) — слои, модули и зависимости между ними сегодня.
5. [Поток запроса](./architecture/request-lifecycle.md) — как HTTP-запрос проходит через RoadRunner, middleware, router и handler.
6. [Protobuf и генерация кода](./architecture/protobuf-and-codegen.md) — где источник истины для API и какие артефакты генерируются.
7. [Инфраструктура и тестирование](./development/infrastructure-and-testing.md) — PostgreSQL, Redis, RoadRunner, миграции и тестовая среда.
8. [Карта границ](./restructure/platform-capabilities-map.md) — что считать `Platform`, `Capability`, `Example` и `Legacy`.
9. [Базовая карта реструктуризации](./restructure/restructure-baseline.md) — текущие структурные проблемы и порядок безопасной переработки.
10. [Roadmap реализации](./restructure/implementation-roadmap.md) — этапы перехода от текущего состояния к целевому шаблону.
11. [Рекомендации по `protoc-php-gen`](./design/protoc-php-gen-guidelines.md) — правила для генерации и маппинга между Domain и Proto.

## Важные принципы

- Источник истины для публичного API — файлы в `protos/proto/app/v1`.
- Сгенерированные артефакты нельзя редактировать вручную.
- `Platform` должен оставаться маленьким и техническим.
- Reusable возможности должны оформляться как capabilities, а не как продуктовые модули шаблона.
- Demo-код должен быть явно отличим от core runtime.
- Целевое направление проекта — `production-ready` infrastructure-first backend template на чистом PHP.
- Перед серьёзной реструктуризацией сначала надо выровнять документацию, затем зафиксировать целевую модульную карту, и только после этого переносить код.
