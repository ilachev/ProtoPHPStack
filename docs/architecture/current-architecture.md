# Текущая архитектура

## Архитектурный стиль

Проект уже частично перестроен под infrastructure-first модель.

Фактическая верхнеуровневая раскладка сейчас такая:

- `src/Platform` — runtime core;
- `src/Capabilities` — reusable building blocks;
- `src/Examples` — reference implementations;
- top-level `src/Application`, `src/Domain`, `src/Infrastructure` — legacy-слой, который ещё не дочищен полностью.

То есть основная проблема больше не в отсутствии целевой структуры, а в сосуществовании новой и старой модели.

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

В `src/Examples` сейчас лежит reference code, который помогает понять сборку template.

### `src/Examples/Home`

Минимальный smoke-test endpoint:

- handler;
- domain service;
- response mapper;
- module registration.

Это демонстрационный код, а не часть core template.

### `src/Examples/Auth`

Текущий auth flow уже работает как reference implementation:

- login;
- refresh;
- logout;
- integration с session capability.

Но этот код пока не отделён на:

- reusable auth primitives;
- demo policy уровня "email/password login flow".

Именно поэтому `Auth` пока должен трактоваться как example-first код.

## Legacy-слой

В проекте всё ещё остаются старые каталоги, которые нужно интерпретировать осторожно.

### `src/Application`

Там живут:

- mapper-ы;
- часть client/geolocation abstractions;
- исторические application services, ещё не перенесённые в capabilities/examples/platform.

Этот каталог больше не должен рассматриваться как главный архитектурный слой проекта.

### `src/Domain`

Там остаются старые domain-модели, включая `User`.

Это уже не canonical structure template. Всё, что находится здесь, должно быть либо:

- перенесено в capability/example;
- оставлено как временный legacy;
- удалено, если не имеет ценности.

### `src/Infrastructure`

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
- legacy-кода до полной перегруппировки.

## Routing и protobuf

Routing остаётся двухфазным:

1. `.proto` и HTTP annotations задают transport surface;
2. `config/routes.php` генерируется из proto и используется runtime router-ом.

Источник истины для публичного API по-прежнему в `protos/proto/app/v1/*`.

При этом обработчики теперь физически находятся не в `Modules`, а в `Examples` и дальше могут появляться в `Capabilities`, если capability действительно экспортирует HTTP endpoints.

## Storage

Storage стратегия остаётся PostgreSQL-first.

В коде всё ещё существует SQLite-слой, но его нужно считать legacy/compatibility нагрузкой, а не опорой архитектуры.

Репозитории capability-уровня должны жить рядом со своей capability, а generic storage/runtime support — в platform/infrastructure support зоне.

## Главные текущие противоречия

### 1. Новая структура уже есть, но legacy ещё силён

Это главный факт текущего состояния.

Нельзя больше описывать проект только через `Domain/Application/Infrastructure`, но и полностью игнорировать эти папки пока нельзя.

### 2. `Auth` ещё не разделён на primitive и example policy

Из-за этого в example-слое всё ещё лежит код, который частично выглядит как reusable capability.

### 3. Tooling и runtime support ещё не разведены до конца

Hydrator/codegen/routing generation живут рядом с runtime support кодом и требуют дальнейшего упорядочивания.

### 4. PostgreSQL-first стратегия ещё не доведена до конца

SQLite всё ещё присутствует, а значит репозиторий пока не до конца последователен в своём storage baseline.
