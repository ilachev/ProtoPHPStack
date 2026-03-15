# Текущая архитектура

## Архитектурный стиль

Проект уже частично перестроен под infrastructure-first модель.

Фактическая верхнеуровневая раскладка сейчас такая:

- `src/Platform` — runtime core;
- `src/Capabilities` — reusable building blocks;
- `src/Examples` — example implementations;
- `src/Infrastructure` — support/tooling слой.

То есть основная проблема больше не в отсутствии целевой структуры, а в том, что `Infrastructure` всё ещё совмещает несколько разных подролей.

## `Platform`

В `src/Platform` уже сосредоточено runtime-ядро:

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

Сейчас в репозитории выделены две capability-зоны.

### `src/Capabilities/Session`

Это наиболее зрелый reusable building block.

Внутри уже есть собственная вертикаль:

- `Domain` — модель сессии, конфиг, repository contract, service;
- `Application` — client detection и payload factory contracts;
- `Infrastructure` — persistence adapters, fingerprint detector, payload factory implementation;
- `Transport/Http` — capability middleware и HTTP coordination.

### `src/Capabilities/ApiStats`

Это candidate observability capability.

Сейчас он даёт:

- repository contract и service для записи статистики;
- PostgreSQL persistence adapter;
- `ApiStatsMiddleware`, который интегрируется в platform pipeline.

Смысл этой части нужно ещё дочистить: либо окончательно оформить как generic observability capability, либо упростить.

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

- `config/container.php` и `config/routes.php` описывают infrastructure-only runtime;
- `src/Examples/*` остаётся в репозитории как примерный слой, но не должен быть частью bootstrap по умолчанию.

Следствие: examples остаются в репозитории, но не должны быть обязательной частью runtime surface.

## `src/Infrastructure`

Этот каталог всё ещё содержит много технических частей:

- DI container и service providers;
- storage;
- migrations;
- cache;
- logger;
- routing generation;
- hydrator;
- geolocation adapters.

Сейчас это не один связный "слой инфраструктуры", а смесь:

- platform-support кода;
- tooling/runtime glue;
- support-кода, который ещё не до конца разведен между runtime и tooling.

## Routing и protobuf

Routing остаётся двухфазным:

1. `.proto` и HTTP annotations задают transport surface;
2. `config/routes.php` генерируется только из core proto и в текущем состоянии остаётся пустым.

Источник истины теперь разделён:

- `protos/proto/app/v1/*` — core template surface;
- `protos/proto/examples/v1/*` — example surface.

При этом default runtime использует пустой `config/routes.php`, а example handlers живут отдельно в `Examples`.

## Storage

Storage стратегия остаётся PostgreSQL-first.

В коде всё ещё существует SQLite-слой, но его нужно считать legacy/compatibility нагрузкой, а не опорой архитектуры.

Репозитории capability-уровня должны жить рядом со своей capability, а generic storage/runtime support — в platform/infrastructure support зоне.

## Главные текущие противоречия

### 1. `Infrastructure` ещё слишком широкий

Сейчас он совмещает storage, service providers, hydrator, routing generation и console support.

### 2. `Auth` ещё не разделён на primitive и example policy

Из-за этого в example-слое всё ещё лежит код, который частично выглядит как reusable capability.

### 3. Tooling и runtime support ещё не разведены до конца

Hydrator/codegen/routing generation живут рядом с runtime support кодом и требуют дальнейшего упорядочивания.

### 4. PostgreSQL-first стратегия ещё не доведена до конца

SQLite всё ещё присутствует, а значит репозиторий пока не до конца последователен в своём storage baseline.
