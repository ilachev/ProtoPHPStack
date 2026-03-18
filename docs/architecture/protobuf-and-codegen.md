# Protobuf и генерация кода

## Почему это важно

В этом проекте `protobuf` — не декоративный артефакт, а основной источник истины для публичного API. Именно из `.proto` строятся:

- PHP protobuf classes;
- OpenAPI/Swagger;
- runtime route configuration;
- generated server-side transport handlers and endpoint contracts;

Любая реструктуризация должна сохранять этот поток или осознанно заменить его чем-то другим.

## Где лежат proto-файлы

### Core API

`protos/proto/app/v1`

Содержит:

- `api.proto` — общие transport structures;
- `health.proto` — neutral health-check endpoint for the core runtime;
- `session.proto` — session-related transport models;
- `users.proto` — user transport model.

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

## Какие generated артефакты считаются каноническими

В default runtime path и в репозитории считаются нормальными только такие generated outputs:

- `protos/gen/App/...` — protobuf message classes и metadata для core API;
- `protos/gen/Google/...` и `protos/gen/GPBMetadata/Google/...` — runtime support для `google.api.http`;
- `gen/Generated/Transport/...` — generated server-side transport contracts и HTTP handlers.

## Текущий flow генерации

Полный цикл:

```bash
task proto:gen:all
```

Состав:

1. `proto:gen:sdk`
2. `proto:gen:transport`
3. `proto:gen:docs`
4. `proto:gen:routes`

## Как устроена генерация маршрутов

Файл `bin/generate-routes.php` создаёт `ProtoRouteProvider`, который:

- читает generated metadata из `protos/gen/App/Api/V1/Metadata`;
- извлекает `service`, `rpc`, `option (google.api.http)` из protobuf descriptors;
- фильтрует descriptors по source prefix `app/v1/`, чтобы default route surface оставался core-only;
- по `php_namespace` вычисляет generated handler class в `App\Generated\Transport\...`;
- строит массив route definitions;
- пишет `config/routes.php` для core runtime.

У route generation больше нет ручного handler mapping layer. Источник истины здесь только protobuf descriptors плюс namespace conventions generated transport-кода.

Сейчас core surface уже содержит `HealthService.Check`, поэтому default route generation создаёт непустой `config/routes.php` и `docs/api.swagger.json`.

## `tools/protoc-php-gen`

Это локальный генератор проекта. Он не является временным скриптом; это часть архитектуры toolchain.

Его роль:

- генерировать server-side transport contracts из protobuf `service/rpc`;
- поддерживать protobuf-first HTTP surface без ручного boilerplate в runtime.

При реструктуризации нельзя просто "спрятать" этот каталог. Нужно решить:

- остаётся ли генератор внутренним инструментом репозитория;
- выносится ли он в отдельный пакет;
- как держать его transport-oriented и не превращать обратно в общий mapper framework.

## Практические правила для изменений

### Если меняется публичный API

1. Править `.proto` в `protos/proto/app/v1`.
2. Перегенерировать артефакты.
3. Добавить endpoint implementation в `App\Platform\Http\Endpoint\...` с тем же относительным путём, что и у generated interface.
4. Проверить, что core артефакты согласованы с кодом.

## Текущие проблемы codegen-потока

- endpoint implementation резолвится по namespace convention, а не проверяется генератором заранее;
- verify уже проверяет наличие handwritten endpoint implementation для каждого generated `*Endpoint`, но сам generator пока не выдаёт такую ошибку на этапе generation.

## Что важно сохранить при реструктуризации

- Один явный источник истины для API.
- Явную границу между generated code и handwritten code.
- Предсказуемый pipeline генерации.
- Возможность LLM определить: "что нужно регенерировать после этого изменения".
