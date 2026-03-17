# Текущая архитектура

## Архитектурный стиль

Проект уже частично перестроен в сторону набора reusable backend-блоков.

Фактическая верхнеуровневая раскладка сейчас такая:

- `src/Platform` — runtime core;
- `src/Capabilities` — reusable building blocks;
- `src/Examples` — example implementations;
Проблема сейчас не в отсутствии структуры как таковой, а в том, что проект всё ещё местами описан тяжёлым архитектурным языком.

## `Platform`

В `src/Platform` уже сосредоточено runtime-ядро:

- DI container и service providers;
- hydration и data mapping;
- HTTP abstractions и handler contracts;
- middleware pipeline;
- route handler resolving;
- runtime bootstrap в `Platform\Runtime\App`.

Pipeline собирается в `App::createPipeline()` и сейчас выполняется в таком порядке:

1. `ErrorHandlerMiddleware`
2. `RequestMetricsMiddleware`
3. `SessionMiddleware`
4. `ApiStatsMiddleware`
5. `RoutingMiddleware`
6. `HttpLoggingMiddleware`

Это означает:

- session capability подготавливает request context до handler;
- observability capability может использовать session context;
- routing и dispatch остаются concern-ами platform runtime.

## `Capabilities`

Сейчас в репозитории выделены две зоны reusable-блоков.

### `src/Capabilities/Session`

Это наиболее зрелый reusable блок.

Внутри уже есть собственная вертикаль:

- `Domain` — модель сессии, конфиг, repository contract, service;
- `Application` — client detection, geolocation contracts и payload factory contracts;
- `Infrastructure` — persistence adapters, fingerprint detector, geolocation implementation и payload factory implementation;
- `Transport/Http` — capability middleware и HTTP coordination.

### `src/Capabilities/ApiStats`

Это candidate observability block.

Сейчас он даёт:

- repository contract и service для записи статистики;
- SQL persistence adapter;
- `ApiStatsMiddleware`, который интегрируется в platform pipeline.

Смысл этой части нужно ещё дочистить: либо окончательно оформить как generic observability block, либо упростить.

## `Examples`

В `src/Examples` сейчас лежит example code, который помогает понять сборку template.

### `src/Examples/Home`

Минимальный smoke-test endpoint:

- handler;
- domain service;
- response mapper;
- module registration.

Это демонстрационный код, а не часть core template.

### `src/Examples/Auth`

Текущий auth flow уже работает как example implementation:

- login;
- refresh;
- logout;
- integration с session capability.

Но этот код пока не отделён на:

- reusable auth primitives;
- demo policy уровня "email/password login flow".

Именно поэтому `Auth` пока должен трактоваться как example-first код.

## Default runtime vs examples

Это теперь важно фиксировать явно:

- `config/container.php` и `config/routes.php` описывают основной runtime;
- `src/Examples/*` остаётся в репозитории как примерный слой, но не должен быть частью bootstrap по умолчанию.

Следствие: examples остаются в репозитории, но не должны быть обязательной частью runtime surface.

## Runtime support

Поддерживающие runtime-части теперь живут рядом с `Platform`:

- `src/Platform/Cache`
- `src/Platform/Logging`
- `src/Platform/Console`
- `src/Platform/Routing/Generator`

Это проще для чтения: нет отдельного широкого слоя, который снова начинает выглядеть как архитектурный центр.

## Routing и protobuf

Routing остаётся двухфазным:

1. `.proto` и HTTP annotations задают transport surface;
2. `config/routes.php` генерируется только из core proto и в текущем состоянии остаётся пустым.

Источник истины теперь разделён:

- `protos/proto/app/v1/*` — core template surface;
- `protos/proto/examples/v1/*` — example surface.

При этом default runtime использует пустой `config/routes.php`, а example handlers живут отдельно в `Examples`.

## Storage

`PostgreSQL` остаётся основным storage adapter рантайма.

При этом default quality gate не должен требовать поднятую БД, а database-specific код должен оставаться локализованным в storage adapter-слое.

Репозитории capability-уровня должны жить рядом со своей capability, а generic storage/runtime support — в `Platform`.

## Главные текущие противоречия

### 1. Support-код ещё не до конца упрощён

Хотя широкий `Infrastructure` уже убран, tooling и runtime support ещё не везде разведены достаточно ясно.

### 2. `Auth` ещё не разделён на primitive и example policy

Из-за этого в example-слое всё ещё лежит код, который частично выглядит как reusable capability.

### 3. Tooling и runtime support ещё не разведены до конца

Routing generation и часть support/tooling кода всё ещё живут рядом и требуют дальнейшего упорядочивания.

### 4. Storage adapters ещё не до конца упрощены

В репозитории всё ещё есть лишние качания между `PostgreSQL` как основным runtime adapter и попыткой сделать storage полностью автономным.
