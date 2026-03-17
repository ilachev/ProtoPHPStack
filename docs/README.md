# Документация проекта

Этот каталог нужен как стартовая точка для человека или LLM, которым нужно быстро понять проект как набор переиспользуемых backend-блоков, безопасно вносить изменения и не раздувать лишнюю архитектурную сложность.

## С чего начинать

1. [LLM Onboarding](./llm-onboarding.md) — краткая карта проекта, точки входа, инварианты и целевое направление.
2. [Vision шаблона](./restructure/template-vision.md) — официальная цель проекта и принципы упрощения.
3. [Целевая архитектура](./architecture/target-architecture.md) — как текущие каталоги интерпретировать как набор простых reusable-блоков.
4. [Текущая архитектура](./architecture/current-architecture.md) — слои, модули и зависимости между ними сегодня.
5. [Поток запроса](./architecture/request-lifecycle.md) — как HTTP-запрос проходит через RoadRunner, middleware, router и handler.
6. [Protobuf и генерация кода](./architecture/protobuf-and-codegen.md) — где источник истины для API и какие артефакты генерируются.
7. [Инфраструктура и тестирование](./development/infrastructure-and-testing.md) — PostgreSQL, Redis, RoadRunner, миграции и тестовая среда.
8. [Карта границ](./restructure/platform-capabilities-map.md) — как отличать runtime-блоки, reusable-подсистемы, examples и legacy.
9. [Базовая карта реструктуризации](./restructure/restructure-baseline.md) — текущие структурные проблемы и порядок безопасной переработки.
10. [Roadmap реализации](./restructure/implementation-roadmap.md) — этапы перехода от текущего состояния к целевому шаблону.
11. [Рекомендации по `protoc-php-gen`](./design/protoc-php-gen-guidelines.md) — правила для генерации и маппинга между Domain и Proto.

## Важные принципы

- Источник истины для core API template — файлы в `protos/proto/app/v1`.
- Example transport contracts, если они нужны как образцы, живут отдельно в `protos/proto/examples/v1`.
- Сгенерированные артефакты нельзя редактировать вручную.
- `Platform` должен оставаться маленьким и техническим.
- Reusable возможности должны оформляться как простые блоки, а не как продуктовые модули шаблона.
- Demo-код должен быть явно отличим от core runtime.
- Целевое направление проекта — `production-ready` backend template на чистом PHP с простыми reusable building blocks.
- Документация должна описывать фактическую структуру репозитория, а не только план миграции.
