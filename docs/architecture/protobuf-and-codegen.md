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

Маршруты больше не строятся отдельным descriptor-parser в runtime. Сначала `protoc-php-gen` генерирует route manifests, а затем `bin/generate-routes.php` собирает из них итоговый `config/routes.php`.

### 4. Server-side transport generation

Команда:

```bash
task proto:gen:transport
```

Результат:

- `gen/Generated/Transport`
- `gen/Generated/EndpointBindings`
- `gen/Generated/RouteManifest`

Генератор создаёт:

- endpoint interfaces для каждого `service/rpc`;
- generic HTTP handlers поверх `AbstractProtobufRpcHandler`;
- endpoint binding manifests для handwritten runtime implementations;
- route manifests из `google.api.http` bindings.

Это handwritten business logic не заменяет. Разработчик по-прежнему пишет endpoint implementation, а runtime резолвит её через generated endpoint binding manifests.

## Какие generated артефакты считаются каноническими

В default runtime path и в репозитории считаются нормальными только такие generated outputs:

- `protos/gen/App/...` — protobuf message classes и metadata для core API;
- `protos/gen/Google/...` и `protos/gen/GPBMetadata/Google/...` — runtime support для `google.api.http`;
- `gen/Generated/Transport/...` — generated server-side transport contracts и HTTP handlers.
- `gen/Generated/EndpointBindings/...` — generated endpoint binding manifests for handwritten implementations.
- `gen/Generated/RouteManifest/...` — generated route manifests for the core runtime.

## Текущий flow генерации

Полный цикл:

```bash
task proto:gen:all
```

Состав:

1. `proto:gen:sdk`
2. `proto:gen:docs`
3. `proto:gen:routes`

## Как устроена генерация маршрутов

Теперь generation path для маршрутов двухшаговый:

1. `task proto:gen:transport` через `protoc-php-gen` генерирует endpoint binding manifests и route manifests в `gen/Generated/...`
2. runtime использует endpoint binding manifests для резолва handwritten endpoint implementations
3. `task proto:gen:routes` через `bin/generate-routes.php` читает route manifests и пишет `config/routes.php`

Файл `bin/generate-routes.php` больше не интерпретирует protobuf descriptors сам. Он только собирает итоговый routes config из generated manifests через `GeneratedRouteManifestProvider`.

Это важно: `service/rpc + google.api.http` теперь проходят через тот же основной toolchain, что и transport contracts, а не через отдельную runtime-ветку parsing logic.

Сейчас core surface уже содержит `HealthService.Check`, поэтому default route generation создаёт непустой `config/routes.php` и `docs/api.swagger.json`.

## `tools/protoc-php-gen`

Это локальный генератор проекта. Он не является временным скриптом; это часть архитектуры toolchain.

Его текущая production-grade роль:

- генерировать server-side transport contracts из protobuf `service/rpc`;
- валидировать наличие и объявление handwritten endpoint implementations;
- генерировать route manifests из `google.api.http` bindings;
- поддерживать protobuf-first HTTP surface без ручного boilerplate в runtime.

При этом инструмент надо понимать шире, чем один текущий generator module: `protoc-php-gen` рассматривается как отдельная modular codegen platform, а основной шаблон сейчас использует три стабильных модуля: `transport_contracts`, `endpoint_validation` и `route_manifest`. Отдельно это зафиксировано в `docs/design/protoc-php-gen-product.md`.

При реструктуризации нельзя просто "спрятать" этот каталог. Нужно решить:

- остаётся ли генератор внутренним инструментом репозитория;
- выносится ли он в отдельный пакет;
- как держать его transport-oriented и не превращать обратно в общий mapper framework.

## Практические правила для изменений

### Если меняется публичный API

1. Править `.proto` в `protos/proto/app/v1`.
2. Перегенерировать артефакты.
3. Добавить endpoint implementation в `App\Platform\Http\Endpoint\...` с тем же относительным путём, который ожидает generated endpoint binding manifest.
4. Проверить, что core артефакты согласованы с кодом.

## Текущие проблемы codegen-потока

- endpoint implementation теперь валидируется на этапе generation, но generator пока не проверяет полноту реализации интерфейса глубже, чем наличие файла и корректное объявление класса;
- verify по-прежнему остаётся второй линией контроля для generated `*Endpoint`.

## Что важно сохранить при реструктуризации

- Один явный источник истины для API.
- Явную границу между generated code и handwritten code.
- Предсказуемый pipeline генерации.
- Возможность LLM определить: "что нужно регенерировать после этого изменения".
