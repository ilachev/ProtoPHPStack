# `protoc-php-gen` как продукт

Этот документ фиксирует `protoc-php-gen` не как случайный внутренний скрипт, а как отдельный codegen-product внутри репозитория.

## Что это такое

`tools/protoc-php-gen` — это локальный `protoc` plugin для генерации PHP-кода вокруг protobuf descriptors.

Его задача не в том, чтобы тащить protobuf во всю внутреннюю архитектуру проекта. Его задача — строить reusable PHP codegen blocks поверх protobuf surface там, где это реально полезно backend-у.

## Текущий поддерживаемый scope

Сейчас production-grade поддерживается только один generator module:

- `transport_contracts`

Он отвечает за:

- генерацию endpoint interfaces из `service/rpc`;
- генерацию HTTP handlers поверх runtime adapter;
- поддержку protobuf-first transport flow в основном шаблоне.

Это текущий канонический и поддерживаемый путь.

## Что это значит practically

Сегодня `protoc-php-gen` уже не притворяется универсальным mapper framework.

Для основного проекта это означает:

- `.proto` управляет transport surface;
- `protoc-php-gen` генерирует transport contracts;
- handwritten endpoint implementation остаётся в runtime-коде;
- generator не подменяет собой business logic.

## Целевая модель инструмента

Долгосрочно `protoc-php-gen` должен развиваться как modular codegen platform, а не как один монолитный generator с разнородной ответственностью.

Правильная модель:

- один plugin;
- несколько независимых generator modules;
- каждый module имеет свою зону ответственности;
- каждый module включается явно параметром генерации;
- каждый module имеет отдельные тесты и свои output contracts.

## Как должен выглядеть modular scope

### Stable now

- `transport_contracts`

### Допустимые будущие направления

- `endpoint_validation` — compile-time проверки полноты handwritten endpoint implementations;
- `route_manifest` — генерация нормализованного route manifest поверх descriptors;
- `http_adapter_helpers` — общие transport-level adapters или metadata helpers;
- `client_helpers` — дополнительные PHP helpers вокруг generated SDK, если появится реальная потребность.

### Что не должно возвращаться бесконтрольно

- неструктурированная mapper generation;
- ORM-like codegen для внутренних моделей;
- hydrator framework внутри тулзы;
- смешение runtime abstractions и codegen responsibilities в одном generator module.

Если когда-нибудь снова появится mapping generator, он может существовать только как отдельный module с отдельным contract, а не как скрытая часть transport path.

## Правила добавления нового generator module

Новый generator допустим только если одновременно выполняются все условия:

- у него есть ясная reusable backend value;
- он не навязывает продуктовую политику;
- он не дублирует существующий runtime responsibility;
- он включается явным флагом plugin parameters;
- у него есть unit и integration tests;
- у него есть понятный output tree;
- его артефакты либо реально используются проектом, либо он не входит в main path.

## Граница ответственности

`protoc-php-gen` отвечает за code generation вокруг protobuf descriptors.

`protoc-php-gen` не отвечает за:

- внутреннюю доменную модель приложения;
- business workflows;
- persistence policy;
- runtime orchestration вне transport/codegen слоя.

Это важная граница: protobuf-first не означает protobuf-everywhere.

## Как соотносится с шаблоном

Для `base-api-template` сейчас важно следующее:

- `protoc-php-gen` остаётся внутренним инструментом репозитория;
- основной template использует только стабильный `transport_contracts` module;
- расширение тулзы допускается только без разрастания основного project path в набор legacy-веток.

## Текущие архитектурные ограничения

Сейчас внутри тулзы уже есть рабочий production-grade path, но до настоящей modular codegen platform она ещё не доведена.

Главные ограничения на текущем этапе:

- plugin core всё ещё жёстко знает про один конкретный generator module;
- transport generator всё ещё напрямую знает о runtime-классах текущего шаблона;
- descriptor flow держится в основном на `array<string, mixed>`, а не на typed internal model;
- type resolution пока ориентирован на простой transport path, а не на полноценную descriptor model;
- тесты хорошо прикрывают текущий happy path, но ещё не тянут на полноценный product-grade end-to-end contour.

Это не означает, что текущая реализация неверна. Это означает, что дальнейшее расширение надо делать через стабилизацию внутренних границ, а не через добавление новых generator modules поверх текущих связей.

## Согласованный технический roadmap

### 1. Modular plugin core

Сначала у тулзы должен появиться отдельный внутренний каркас для generator modules.

Целевое состояние:

- `PluginOptions` вместо current ad-hoc config parsing;
- `CodeGeneratorModule` как единый contract для generator modules;
- `GeneratorRegistry`, который собирает активные модули и запускает их.

Это нужно для того, чтобы новый generator module добавлялся регистрацией, а не переписыванием центрального plugin-класса.

### 2. Typed descriptor model

Следующий шаг — заменить сырой array-based descriptor flow на простую typed internal model.

Целевое состояние:

- `ProtoFileDescriptor`;
- `MessageDescriptor`;
- `ServiceDescriptor`;
- `MethodDescriptor`;
- `TypeRegistry`.

Это нужно для того, чтобы generators работали не с произвольными массивами, а с предсказуемой descriptor model.

### 3. Type resolver as a separate subsystem

Type resolution нельзя держать как локальную эвристику transport generator-а.

Целевое состояние:

- отдельный `TypeResolver`;
- работа по fully-qualified protobuf names;
- корректная обработка nested types, cross-file references и name collisions.

Это нужно для correctness и для будущих generator modules.

### 4. Runtime profile abstraction

Transport generator не должен навсегда быть зашит в runtime именно этого шаблона.

Целевое состояние:

- transport-level profile abstraction;
- текущий `base-api-template` profile как одна конкретная реализация;
- generator знает про transport profile, а не про жёстко вшитые `App\\Platform\\...` классы.

Это нужно для того, чтобы `protoc-php-gen` оставался reusable codegen tool, даже если основной шаблон продолжит быть его главным consumer.

### 5. Product-grade test contour

Только после стабилизации внутренних границ можно считать тулзу готовой к дальнейшему росту.

Целевое состояние:

- binary end-to-end tests от raw `CodeGeneratorRequest` до `CodeGeneratorResponse`;
- fixture-based integration tests на real `.proto`;
- contract tests на каждый generator module;
- edge-case tests на type resolution.

### 6. Only then new generator modules

Новые modules допустимо добавлять только после шагов выше.

Иначе тулза снова распухнет вокруг специальных случаев текущей реализации.

## Порядок реализации

Рабочий порядок зафиксирован такой:

1. modular plugin core;
2. typed descriptor model;
3. type resolver;
4. runtime profile abstraction;
5. product-grade test contour;
6. только после этого новые generator modules.

## Критерий хорошего развития

Хорошее развитие `protoc-php-gen`:

- усиливает protobuf-first backend flow;
- уменьшает ручной transport boilerplate;
- остаётся reusable вне одного конкретного API;
- развивается модулями, а не историческими слоями;
- не создаёт вторую архитектуру внутри проекта.
