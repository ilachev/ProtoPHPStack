# Protobuf и генерация кода

## Почему это важно

В этом проекте `protobuf` — не декоративный артефакт, а основной источник истины для публичного API. Именно из `.proto` строятся:

- PHP protobuf classes;
- OpenAPI/Swagger;
- runtime route configuration;
- generated server-side transport handlers and endpoint contracts;
- часть mapper/hydrator инфраструктуры.

Любая реструктуризация должна сохранять этот поток или осознанно заменить его чем-то другим.

## Где лежат proto-файлы

### Core API

`protos/proto/app/v1`

Содержит:

- `api.proto` — общие transport structures;
- `health.proto` — neutral health-check endpoint for the core runtime;
- `session.proto` — session-related transport models;
- `users.proto` — user transport model.

### Доменные proto-описания

`protos/proto/app/domain`

Содержит:

- `options.proto` — custom protobuf options для entity/field metadata;
- `session.proto` — описание доменной сущности session как proto entity.

Это не публичные API-контракты, а технический слой для генерации.

## Что генерируется

### 1. PHP protobuf classes

Команда:

```bash
task proto:gen:sdk
```

Результат:

- `protos/gen/App/...`

Эти файлы нельзя редактировать вручную.

### 2. Core OpenAPI

Команда:

```bash
task proto:gen:docs
```

Результат:

- `docs/api.swagger.json`

### 3. Routes config

Команда:

```bash
task proto:gen:routes
```

Результат:

- `config/routes.php`

Генерация routes основана на `google.api.http` annotations в core `.proto`.

### 4. Server-side transport generation

Команда:

```bash
task proto:gen:transport
```

Результат:

- `gen/Generated/Transport`

Генератор создаёт:

- endpoint interfaces для каждого `service/rpc`;
- generic HTTP handlers поверх `AbstractProtobufRpcHandler`.

Это handwritten business logic не заменяет. Разработчик по-прежнему пишет endpoint implementation, а runtime резолвит её по соглашению namespace.

### 5. Mapper/hydrator generation

Команда:

```bash
task proto:gen:mappers
```

Результат:

- код в `gen/ProtoMapper`

Эта часть основана не только на `.proto`, но и на PHP attributes в capability/example/platform моделях.

## Текущий flow генерации

Полный цикл:

```bash
task proto:gen:all
```

Состав:

1. `proto:gen:sdk`
2. `proto:gen:transport`
3. `proto:gen:mappers`
4. `proto:gen:docs`
5. `proto:gen:routes`

## Как устроена генерация маршрутов

Файл `bin/generate-routes.php` создаёт `ProtoRouteProvider`, который:

- читает generated metadata из `protos/gen/App/Api/V1/Metadata`;
- извлекает `service`, `rpc`, `option (google.api.http)` из protobuf descriptors;
- фильтрует descriptors по source prefix `app/v1/`, чтобы default route surface оставался core-only;
- по `php_namespace` вычисляет generated handler class в `App\Generated\Transport\...`;
- строит массив route definitions;
- пишет `config/routes.php` для core runtime.

Сейчас core surface уже содержит `HealthService.Check`, поэтому default route generation создаёт непустой `config/routes.php` и `docs/api.swagger.json`.

## Custom mapping через атрибуты

Некоторые capability/example классы размечены атрибутами:
Некоторые capability/platform классы размечены атрибутами:

- `ProtoMapping`
- `ProtoField`

Примеры:

- `src/Capabilities/Session/Domain/Session.php`
- `src/Capabilities/Session/Application/GeoLocationData.php`

Эти атрибуты нужны для генераторов и маппинга между внутренними моделями и protobuf message classes.

## `tools/protoc-php-gen`

Это локальный генератор проекта. Он не является временным скриптом; это часть архитектуры toolchain.

Его роль:

- читать custom options и/или PHP attributes;
- строить descriptors и field mapping;
- генерировать код для hydrator/mapper сценариев.

При реструктуризации нельзя просто "спрятать" этот каталог. Нужно решить:

- остаётся ли генератор внутренним инструментом репозитория;
- выносится ли он в отдельный пакет;
- становится ли он обязательной частью build pipeline.

## Практические правила для изменений

### Если меняется публичный API

1. Править `.proto` в `protos/proto/app/v1`.
2. Перегенерировать артефакты.
3. Добавить endpoint implementation в `App\Platform\Http\Endpoint\...` с тем же относительным путём, что и у generated interface.
4. Проверить, что core артефакты согласованы с кодом.

### Если меняется внутренняя доменная модель, связанная с protobuf mapping

1. Проверить `ProtoMapping`/`ProtoField`.
2. Проверить `app/domain/*.proto`, если сущность участвует в генерации.
3. Перегенерировать mapper/hydrator код.
4. Прогнать тесты и статический анализ.

## Текущие проблемы codegen-потока

- endpoint implementation резолвится по namespace convention, а не проверяется генератором заранее;
- есть два направления генерации: protobuf transport и внутренний hydrator generator;
- transport generation пока не проверяет наличие endpoint implementation автоматически;
- в проекте уже есть новый `CodeGeneratingHydrator`, но DI всё ещё регистрирует `ReflectionHydrator` как основной.

## Что важно сохранить при реструктуризации

- Один явный источник истины для API.
- Явную границу между generated code и handwritten code.
- Предсказуемый pipeline генерации.
- Возможность LLM определить: "что нужно регенерировать после этого изменения".
