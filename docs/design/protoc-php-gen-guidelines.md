# Рекомендации по `protoc-php-gen`

Этот документ фиксирует правила работы с внутренним генератором `tools/protoc-php-gen`.

Продуктовая модель и целевой scope инструмента отдельно описаны в [protoc-php-gen-product.md](./protoc-php-gen-product.md).

## Зачем существует генератор

В основном project path `protoc-php-gen` сейчас нужен не для общего mapper framework, а для двух связанных transport-задач:

- взять protobuf `service/rpc`;
- сгенерировать transport contracts;
- сгенерировать operation manifests для каждого RPC;
- сократить ручной HTTP boilerplate в runtime.

Это transport-oriented codegen path, а не универсальный генератор для внутренних моделей проекта.

## Что считается правильной областью ответственности

В генераторе допустимо:

- читать protobuf descriptors;
- генерировать endpoint interfaces;
- генерировать HTTP handlers поверх общего runtime adapter;
- валидировать handwritten endpoint implementations на этапе generation;
- генерировать operation manifests как явную metadata surface;
- генерировать endpoint binding manifests только как производный compatibility artifact;
- держать явный generated contract между transport-кодом и handwritten endpoint implementations.

В генераторе недопустимо:

- маппить внутренние domain-модели проекта;
- тащить внутрь бизнес-логику;
- превращаться в ORM-like или hydrator-like framework;
- плодить отдельную ветку generated runtime, которую основной проект не использует.

## Практические правила

### 1. Protobuf управляет transport surface

Если меняется публичный API, источник истины только один:

- `.proto` в `protos/proto/app/v1`

После этого надо:

1. перегенерировать артефакты;
2. добавить handwritten endpoint implementation;
3. прогнать `task verify`.

### 2. Generated code и handwritten code должны быть разделены

Generated transport code:

- `gen/Generated/Transport/...`
- `gen/Generated/EndpointBindings/...`
- `gen/Generated/OperationManifest/...`

Handwritten runtime implementation:

- `src/Platform/Http/Endpoint/...`

Генератор не должен подменять собой application logic.

### 3. Никаких project-specific special cases

Если для нового RPC нужна особая логика, она должна жить в handwritten endpoint implementation, а не в `protoc-php-gen`.

Генератор должен оставаться одинаково полезным для любого backend surface.

### 4. Генератор должен быть воспроизводимым

Любой разработчик или LLM должны понимать:

- из каких `.proto` идёт генерация;
- какая команда запускает pipeline;
- какие generated-файлы считаются каноническими.

## Что не считается частью канонического пути

Следующие идеи не являются поддерживаемым направлением проекта:

- attribute-based mapper generation;
- generated hydrator runtime;
- protobuf-driven mapping между внутренними PHP-моделями и domain-структурами.

Если такой код ещё когда-нибудь снова появится в тулзе, он не должен попадать в main project path без отдельного generator module, отдельного contract и отдельной поддержки.

## Критерий хорошего изменения

Хорошее изменение вокруг `protoc-php-gen`:

- усиливает protobuf-first transport flow;
- уменьшает ручной transport boilerplate;
- не тащит protobuf глубже, чем это нужно transport-слою;
- не создаёт вторую архитектуру внутри генератора.
