# LLM Onboarding

Этот документ предназначен для быстрой загрузки контекста в LLM перед анализом, разработкой и реструктуризацией проекта.

## Что это за проект

`base-api-template` — это infrastructure-first шаблон для backend API на PHP 8.4 с упором на:

- `protobuf-first` описание API-контрактов;
- генерацию SDK-классов, OpenAPI и маршрутов из `.proto`;
- запуск на RoadRunner вместо классического PHP-FPM;
- PostgreSQL как основном хранилище;
- Redis/RoadRunner KV для кеша;
- маленький framework-free runtime core;
- разделение на `Platform`, `Capabilities` и `Examples`.

Проект не должен трактоваться как готовый продукт. Это шаблон плюс небольшой набор reference implementations, которые показывают, как собирать backend из platform runtime и reusable capabilities.

## Какая теперь официальная цель

Официальная цель проекта зафиксирована так:

- `production-ready` backend template;
- чистый PHP без framework-зависимости;
- минимальная архитектурная зависимость от внешних библиотек;
- capability-oriented vertical slices как целевая модель организации кода.

Подробно это описано в `docs/restructure/template-vision.md` и `docs/architecture/target-architecture.md`.

## Главные точки входа

- HTTP entrypoint: `public/index.php`
- Reference entrypoint: `public/reference.php`
- Runtime bootstrap: `src/Platform/Runtime/App.php`
- Default DI configuration: `config/container.php`
- Reference DI configuration: `config/container.reference.php`
- Default runtime routes: `config/routes.php`
- Reference app routes: `config/routes.reference.php`
- Источник API-контрактов: `protos/proto/app/v1/*.proto`
- Команды разработки: `taskfile.yaml`

## Быстрая карта каталогов

### `src/Platform`

Runtime core:

- HTTP abstractions;
- middleware pipeline;
- route resolving;
- application bootstrap.

### `src/Capabilities`

Reusable building blocks:

- `Session` — сессии, fingerprinting, client detection, session middleware;
- `ApiStats` — request statistics / observability candidate.

### `src/Examples`

Reference implementations:

- `Home` — минимальный smoke-test endpoint;
- `Auth` — reference auth flow поверх session capability.

Examples не должны считаться частью default runtime. Они подключаются только через reference app config.

### Legacy зоны

- `src/Application`
- `src/Domain`
- `src/Infrastructure`

Эти каталоги всё ещё используются, но уже не являются правильной mental model для нового кода. Их нужно читать как переходный слой.

### `protos`

- `protos/proto/app/v1` — публичные API-контракты.
- `protos/proto/app/domain` — proto-описания доменных сущностей и custom options.
- `protos/gen` — сгенерированные PHP-классы protobuf.

### `tools/protoc-php-gen`

Локальный генератор, который использует атрибуты и/или proto-описания для генерации маппинга/hydrator-кода. Это отдельная важная подсистема проекта, а не случайная утилита.

## Что реально работает сейчас

- default runtime без product endpoints;
- reference app с `GET /api/v1/home`;
- reference app с `POST /api/v1/auth/login`;
- reference app с `POST /api/v1/auth/refresh`;
- reference app с `POST /api/v1/auth/logout`;
- создание и обновление анонимных сессий;
- восстановление сессии по cookie/bearer;
- fingerprint matching клиента;
- геолокация по IP;
- запись статистики API-вызовов;
- миграции PostgreSQL;
- unit и integration tests.

## Что ещё не доведено до целевого состояния

- `Auth` пока не разделён на reusable primitives и example policy;
- legacy top-level каталоги ещё не дочищены;
- SQLite слой всё ещё присутствует;
- hydrator/codegen слой ещё не получил окончательно согласованную роль.

## Основные инварианты для безопасной разработки

- Не редактировать вручную `protos/gen/*`.
- Не редактировать вручную `config/routes.reference.php`; reference routes генерируются из `.proto`.
- Для нового публичного API сначала правится `.proto`, затем запускается генерация.
- `Platform` не должен содержать продуктовую политику.
- Capability должна быть reusable вне конкретного продукта.
- Example должен быть явно вторичен по отношению к platform/capability-коду.
- Default runtime не должен зависеть от example handlers или example routes.
- Handler должен быть тонким адаптером.
- Преобразования между слоями должны делаться через mapper/hydrator, а не через ad-hoc массивы по всему коду.
- Для storage по умолчанию используется PostgreSQL, даже если в кодовой базе ещё остались SQLite-артефакты.
- Проект работает в long-running процессе RoadRunner, поэтому надо учитывать память, кеши и накопление состояния между запросами.

## Команды, которые нужно знать LLM

```bash
task install
task proto:gen:all
task lint
task phpstan
task test
task verify
task services:start
task services:stop
task run
```

## Минимальная стратегия чтения кода

Если нужно быстро понять поведение проекта, читать в таком порядке:

1. `docs/architecture/request-lifecycle.md`
2. `src/Platform/Runtime/App.php`
3. `config/container.php`
4. `config/container.reference.php`, если нужен reference app
5. `src/Capabilities/*`
6. `src/Examples/*`
7. `src/Infrastructure/DI/ServiceProviders/*`
8. `src/Infrastructure/Storage/*`
9. `src/Application/*` и `src/Domain/*` только если нужно понять legacy-хвост
10. `protos/proto/app/v1/*`

## Главные текущие зоны риска

- Legacy-каталоги всё ещё создают шум и могут сбить LLM с правильной mental model.
- `Auth` пока не разделён на capability primitives и example flow.
- Часть кода ориентирована на PostgreSQL-only стратегию, но SQLite-слой ещё присутствует.
- В репозитории уже есть незавершённый переход к code-generating hydrator, но он пока не является основным runtime-механизмом.

## Как теперь интерпретировать изменения

Если LLM вносит изменения в проект, она должна оценивать их не только относительно текущего кода, но и относительно target state:

- приближает ли изменение структуру к vertical slices;
- приближает ли изменение структуру к `Platform / Capabilities / Examples`;
- уменьшает ли framework/library coupling;
- делает ли platform слой более маленьким и ясным;
- помогает ли это прийти к production-ready шаблону, а не просто чинит локальный симптом.
