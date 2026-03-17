# LLM Onboarding

Этот документ предназначен для быстрой загрузки контекста в LLM перед анализом, разработкой и реструктуризацией проекта.

## Что это за проект

`base-api-template` — это шаблон для backend API на PHP 8.4 с упором на:

- `protobuf-first` описание API-контрактов;
- генерацию SDK-классов, OpenAPI и маршрутов из `.proto`;
- запуск на RoadRunner вместо классического PHP-FPM;
- PostgreSQL как основной storage adapter;
- Redis/RoadRunner KV для кеша;
- маленький framework-free runtime core;
- разделение на runtime blocks, reusable blocks и examples.

Проект не должен трактоваться как готовый продукт. Это шаблон плюс небольшой набор examples, которые показывают, как собирать backend из runtime primitives и reusable blocks.

## Какая теперь официальная цель

Официальная цель проекта зафиксирована так:

- `production-ready` backend template;
- чистый PHP без framework-зависимости;
- минимальная архитектурная зависимость от внешних библиотек;
- набор базовых reusable-блоков вместо тяжёлой фреймворкоподобной архитектуры.

Подробно это описано в `docs/restructure/template-vision.md` и `docs/architecture/target-architecture.md`.

## Главные точки входа

- HTTP entrypoint: `public/index.php`
- Runtime bootstrap: `src/Platform/Runtime/App.php`
- Default DI configuration: `config/container.php`
- Default runtime routes: `config/routes.php`
- Core API contracts: `protos/proto/app/v1/*.proto`
- Example API contracts: `protos/proto/examples/v1/*.proto`
- Команды разработки: `taskfile.yaml`

## Быстрая карта каталогов

### `src/Platform`

Runtime core:

- DI container and service providers;
- storage abstractions, queries and migrations;
- HTTP abstractions;
- middleware pipeline;
- route resolving;
- application bootstrap.

### `src/Capabilities`

Reusable building blocks:

- `Session` — сессии, fingerprinting, client detection, session middleware, geolocation integration;
- `ApiStats` — небольшой add-on для записи request log.

### `src/Examples`

Example implementations:

- `Home` — минимальный smoke-test endpoint;
- `Auth` — example `email/password` flow поверх session capability.

Examples не должны считаться частью default runtime. Они остаются как примерный слой и не участвуют в основном bootstrap по умолчанию.

### `protos`

- `protos/proto/app/v1` — core API-контракты шаблона.
- `protos/proto/examples/v1` — example API-контракты примеров.
- `protos/proto/app/domain` — proto-описания доменных сущностей и custom options.
- `protos/gen` — сгенерированные PHP-классы protobuf.

### `tools/protoc-php-gen`

Локальный генератор, который использует атрибуты и/или proto-описания для генерации маппинга/hydrator-кода. Это отдельная важная подсистема проекта, а не случайная утилита.

## Что реально работает сейчас

- default runtime без product endpoints;
- создание и обновление анонимных сессий;
- восстановление сессии по cookie/bearer;
- fingerprint matching клиента;
- геолокация по IP;
- запись request log для API-вызовов;
- миграции storage adapters;
- unit и integration tests.

## Что ещё не доведено до целевого состояния

- `Auth` всё ещё не разделён на reusable primitives и example policy, хотя demo-flow уже явно отделён от session runtime;
- терминология проекта всё ещё местами звучит тяжелее, чем реально нужно для набора reusable-блоков;
- hydration/codegen слой ещё не до конца вычищен и документирован.

## Основные инварианты для безопасной разработки

- Не редактировать вручную `protos/gen/*`.
- Не редактировать вручную `config/routes.php`; core routes генерируются из `protos/proto/app/v1`.
- Для нового публичного API сначала правится `.proto`, затем запускается генерация.
- `Platform` не должен содержать продуктовую политику.
- Reusable блок должен быть полезен вне конкретного продукта.
- Example должен быть явно вторичен по отношению к platform/capability-коду.
- Default runtime не должен зависеть от example handlers или example routes.
- Handler должен быть тонким адаптером.
- Преобразования между слоями должны делаться через mapper/hydrator, а не через ad-hoc массивы по всему коду.
- Default quality gate не должен зависеть от поднятой БД, даже если основной runtime adapter — PostgreSQL.
- Проект работает в long-running процессе RoadRunner, поэтому надо учитывать память, кеши и накопление состояния между запросами.

## Команды, которые нужно знать LLM

```bash
task install
task proto:gen:all
task lint
task phpstan
task test
task test:integration
task test:full
task verify
task verify:integration
task verify:full
task services:start
task services:stop
task run
```

Интерпретация:

- `task test` и `task verify` не должны требовать внешние сервисы;
- `task test:integration` и `task verify:full` используют PostgreSQL profile;
- для изменений в storage/runtime adapters недостаточно только unit-контура.

## Минимальная стратегия чтения кода

Если нужно быстро понять поведение проекта, читать в таком порядке:

1. `docs/architecture/request-lifecycle.md`
2. `src/Platform/Runtime/App.php`
3. `config/container.php`
4. `src/Capabilities/*`
5. `src/Examples/*`
6. `src/Platform/DI/*`
7. `src/Platform/Storage/*`
8. `src/Platform/Cache`, `src/Platform/Logging`, `src/Platform/Console`, `src/Platform/Routing/Generator` когда нужен runtime/support код
9. `protos/proto/app/v1/*` и `protos/proto/examples/v1/*`

## Главные текущие зоны риска

- `Auth` пока не разделён на capability primitives и example flow.
- Storage и integration adapters всё ещё дочищаются от vendor-specific предположений.
- Code-generating hydration остаётся вторичным механизмом по отношению к reflection-based runtime hydration.

## Как теперь интерпретировать изменения

Если LLM вносит изменения в проект, она должна оценивать их не только относительно текущего кода, но и относительно target state:

- приближает ли изменение структуру к набору простых reusable-блоков;
- делает ли границы `Platform / Capabilities / Examples` проще и понятнее, а не сложнее;
- уменьшает ли framework/library coupling;
- делает ли platform слой более маленьким и ясным;
- помогает ли это прийти к production-ready шаблону, а не просто чинит локальный симптом.
