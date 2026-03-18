# Protobuf и генерация кода

## Почему это важно

В этом проекте `protobuf` — не декоративный артефакт, а основной источник истины для публичного API. Именно из `.proto` строятся:

- PHP protobuf classes;
- OpenAPI/Swagger;
- runtime route configuration;
- часть mapper/hydrator инфраструктуры.

Любая реструктуризация должна сохранять этот поток или осознанно заменить его чем-то другим.

## Где лежат proto-файлы

### Core API

`protos/proto/app/v1`

Содержит:

- `api.proto` — общие transport structures;
- `session.proto` — session-related transport models;
- `users.proto` — user transport model.

### Example API

`protos/proto/examples/v1`

Содержит:

- `home.proto` — smoke-test endpoint и его модели;
- `auth.proto` — example auth flow и его модели.

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

### 4. Mapper/hydrator generation

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
2. `proto:gen:docs`
3. `proto:gen:routes`
4. `proto:gen:mappers`

## Как устроена генерация маршрутов

Файл `bin/generate-routes.php` создаёт `ProtoRouteProvider`, который:

- читает core `.proto` файлы;
- извлекает `service`, `rpc`, `option (google.api.http)`;
- строит массив route definitions;
- пишет `config/routes.php` для core runtime.

Поскольку core surface сейчас не содержит HTTP services с `google.api.http`, результатом генерации является пустой `config/routes.php`.

## Custom mapping через атрибуты

Некоторые capability/example классы размечены атрибутами:

- `ProtoMapping`
- `ProtoField`

Примеры:

- `src/Capabilities/Session/Domain/Session.php`
- `src/Capabilities/Session/Application/GeoLocationData.php`
- `src/Examples/Auth/Domain/AuthUser.php`

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

1. Править `.proto` в нужной зоне: `protos/proto/app/v1` для core или `protos/proto/examples/v1` для examples.
2. Перегенерировать артефакты.
3. Обновить handler/mapper/runtime implementation.
4. Проверить, что core артефакты согласованы с кодом.

### Если меняется внутренняя доменная модель, связанная с protobuf mapping

1. Проверить `ProtoMapping`/`ProtoField`.
2. Проверить `app/domain/*.proto`, если сущность участвует в генерации.
3. Перегенерировать mapper/hydrator код.
4. Прогнать тесты и статический анализ.

## Текущие проблемы codegen-потока

- Публичные proto-контракты и runtime не всегда согласованы.
- Есть два направления генерации: protobuf SDK и внутренний hydrator generator.
- Генерация маршрутов использует naming convention, а не проверку существования handler implementation.
- В проекте уже есть новый `CodeGeneratingHydrator`, но DI всё ещё регистрирует `ReflectionHydrator` как основной.

## Что важно сохранить при реструктуризации

- Один явный источник истины для API.
- Явную границу между generated code и handwritten code.
- Предсказуемый pipeline генерации.
- Возможность LLM определить: "что нужно регенерировать после этого изменения".
