# LLM Onboarding

Короткий канонический обзор проекта для LLM и человека.

## Что это за проект

`base-api-template` — это backend template на PHP 8.4 с маленьким runtime core, `protobuf-first` transport contracts и набором простых reusable-блоков.

Это не готовый продукт и не framework. Репозиторий должен восприниматься так:

- `Platform` — runtime core;
- `Capabilities` — reusable blocks;
- `Examples` — демонстрационные сценарии поверх core.

## Цель проекта

- production-ready backend template;
- чистый PHP без framework coupling;
- минимальная зависимость от внешних библиотек как архитектурного центра;
- несколько простых building blocks вместо большой архитектурной системы.

## Главные точки входа

- `public/index.php` — HTTP entrypoint
- `src/Platform/Runtime/App.php` — bootstrap и pipeline
- `config/container.php` — DI wiring
- `config/routes.php` — core runtime routes
- `protos/proto/app/v1/*.proto` — core API contracts
- `protos/proto/examples/v1/*.proto` — example API contracts
- `taskfile.yaml` — команды разработки
- `docs/reusable-blocks.md` — каноническая карта reusable-блоков проекта

## Текущая структура

### `src/Platform`

Runtime core:

- DI
- HTTP abstractions and middleware pipeline
- routing runtime and route generation support
- storage, cache, logging, console support
- hydration and data mapping

### `src/Capabilities`

Reusable blocks:

- `Session` — сессии, fingerprinting, client detection, session middleware, geolocation integration
- `ApiStats` — маленький add-on для записи request log

### `src/Examples`

Demo code:

- `Home` — минимальный smoke-test endpoint
- `Auth` — example `email/password` flow поверх session capability

Examples не должны участвовать в default runtime bootstrap.

## Что реально работает

- runtime без product endpoints по умолчанию
- анонимные и восстановленные сессии
- fingerprint matching клиента
- геолокация по IP
- request log для API-вызовов
- protobuf/codegen pipeline
- unit и integration profiles

## Что важно считать инвариантами

- не редактировать вручную `protos/gen/*`
- не редактировать вручную `config/routes.php`
- для нового публичного API сначала менять `.proto`, потом запускать генерацию
- `Platform` не должен содержать продуктовую политику
- reusable block должен оставаться полезным вне конкретного продукта
- example code должен быть явно вторичен по отношению к runtime
- default `task verify` не должен требовать поднятых внешних сервисов
- проект живёт в long-running процессе RoadRunner, значит надо следить за памятью и накоплением состояния

## Команды

```bash
task install
task proto:gen:all
task lint
task phpstan
task test
task test:integration
task verify
task verify:full
task services:start
task services:stop
task run
```

Практически:

- `task verify` — основной локальный gate
- `task test:integration` и `task verify:full` используют PostgreSQL profile
- storage/runtime изменения нельзя проверять только unit-тестами

## Минимальный порядок чтения

1. `docs/architecture/request-lifecycle.md`
2. `docs/reusable-blocks.md`
3. `src/Platform/Runtime/App.php`
4. `config/container.php`
5. `src/Capabilities/*`
6. `src/Examples/*`
7. `src/Platform/Storage/*`
8. `protos/proto/app/v1/*`

## Текущие зоны риска

- `Auth` всё ещё не разделён на reusable primitives и example policy
- storage/integration adapters всё ещё требуют аккуратного упрощения
- hydration/codegen layer всё ещё сложнее, чем остальная структура проекта

## Как оценивать изменения

Изменение хорошее, если оно:

- делает проект проще для чтения
- уменьшает library/framework gravity
- не превращает examples в обязательную часть core runtime
- усиливает reusable blocks, а не раздувает архитектурный словарь
