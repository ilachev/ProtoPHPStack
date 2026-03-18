# LLM Onboarding

Короткий канонический обзор проекта для LLM и человека.

## Что это за проект

`base-api-template` — это backend template на PHP 8.4 с маленьким runtime core, `protobuf-first` transport contracts и набором простых reusable-блоков.

Это не готовый продукт и не framework. Репозиторий должен восприниматься так:

- `Platform` — runtime core;
- `Capabilities` — reusable blocks;
- `HealthCheck` и `SystemDescribe` — минимальные нейтральные примеры protobuf-first transport flow внутри core.

## Цель проекта

- production-ready backend template;
- чистый PHP без framework coupling;
- минимальная зависимость от внешних библиотек как архитектурного центра;
- несколько простых building blocks вместо большой архитектурной системы.

## Главные точки входа

- `public/index.php` — HTTP entrypoint
- `src/Platform/Runtime/App.php` — bootstrap и pipeline
- `config/container.php` — DI wiring
- `protos/proto/app/v1/*.proto` — core API contracts
- `taskfile.yaml` — команды разработки
- `docs/reusable-blocks.md` — каноническая карта reusable-блоков проекта
- `docs/design/protoc-php-gen-product.md` — целевая модель protobuf codegen-tooling

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

## Что реально работает

- runtime без product endpoints по умолчанию
- анонимные и восстановленные сессии
- fingerprint matching клиента
- геолокация по IP
- request log для API-вызовов
- protobuf/codegen pipeline
- generated server-side transport handlers from protobuf `service/rpc`
- unit и integration profiles

## Какие generated артефакты считать основными

- `protos/gen/*` — основной protobuf SDK и metadata
- `gen/Generated/Transport/*` — generated transport contracts и handlers
- `gen/Generated/OperationManifest/*` — generated operation metadata for each RPC
- legacy `gen/Infrastructure/Hydrator/*` больше не является валидным generated output

## Что важно считать инвариантами

- не редактировать вручную `protos/gen/*`
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
6. `src/Platform/Storage/*`
7. `protos/proto/app/v1/*`

## Текущие зоны риска

- storage/integration adapters всё ещё требуют аккуратного упрощения
- hydration/data mapping layer всё ещё чувствителен к усложнению
- endpoint implementations всё ещё пишутся вручную, а generator пока валидирует только наличие файла и корректное объявление класса
- consistency между operation manifests, generated handlers и endpoint implementations теперь проверяется в default `verify`, но это всё ещё verify-time guard, а не отдельный generator module
- `protoc-php-gen` сейчас стабилен только в transport-контуре; дальнейшее расширение допустимо только как modular codegen, а не как возврат к старой смешанной генерации

## Как оценивать изменения

Изменение хорошее, если оно:

- делает проект проще для чтения
- уменьшает library/framework gravity
- не вводит продуктовые demo-flow в основной runtime
- усиливает reusable blocks, а не раздувает архитектурный словарь
