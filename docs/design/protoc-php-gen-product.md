# `protoc-php-gen` как продукт

Этот документ фиксирует `protoc-php-gen` не как случайный внутренний скрипт, а как отдельный codegen-product внутри репозитория.

## Что это такое

`tools/protoc-php-gen` — это локальный `protoc` plugin для генерации PHP-кода вокруг protobuf descriptors.

Его задача не в том, чтобы тащить protobuf во всю внутреннюю архитектуру проекта. Его задача — строить reusable PHP codegen blocks поверх protobuf surface там, где это реально полезно backend-у.

## Текущий поддерживаемый scope

Сейчас production-grade поддерживаются три generator module:

- `endpoints`
- `endpoint_validation`
- `operation_manifest`

Он отвечает за:

- генерацию endpoint interfaces из `service/rpc`;
- генерацию HTTP handlers поверх runtime adapter;
- compile-time проверку наличия handwritten endpoint implementations;
- генерацию operation registry classes как канонической metadata surface по каждому RPC;
- поддержку protobuf-first endpoint flow в основном шаблоне.

Это текущий канонический и поддерживаемый путь.

## Что это значит practically

Сегодня `protoc-php-gen` уже не притворяется универсальным mapper framework.

Для основного проекта это означает:

- `.proto` управляет endpoint surface;
- `protoc-php-gen` генерирует endpoints и operation registry classes;
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

- `endpoints`
- `endpoint_validation`
- `operation_manifest`

### Допустимые будущие направления

- `http_adapter_helpers` — общие transport-level adapters или metadata helpers;
- `client_helpers` — дополнительные PHP helpers вокруг generated SDK, если появится реальная потребность.

### Что не должно возвращаться бесконтрольно

- неструктурированная mapper generation;
- ORM-like codegen для внутренних моделей;
- hydrator framework внутри тулзы;
- смешение runtime abstractions и codegen responsibilities в одном generator module.

Если когда-нибудь снова появится mapping generator, он может существовать только как отдельный module с отдельным contract, а не как скрытая часть endpoint path.

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
- runtime orchestration вне endpoint/codegen слоя.

Это важная граница: protobuf-first не означает protobuf-everywhere.

## Как соотносится с шаблоном

Для `base-api-template` сейчас важно следующее:

- `protoc-php-gen` остаётся внутренним инструментом репозитория;
- основной template использует стабильные `endpoints`, `endpoint_validation` и `operation_manifest` modules;
- расширение тулзы допускается только без разрастания основного project path в набор legacy-веток.

## Текущие архитектурные ограничения

Базовый внутренний каркас уже стабилизирован:

- modular plugin core есть;
- typed descriptor model есть;
- type resolver выделен отдельно;
- runtime profile abstraction есть;
- binary end-to-end contour есть.

Это не означает, что тулза закончена как продукт. Это означает, что дальнейшее расширение теперь можно делать осознанно, не возвращаясь к старой mixed-responsibility реализации.

## Согласованный технический roadmap

### Завершённая внутренняя стабилизация

- `PluginOptions`, `CodeGeneratorModule`, `GeneratorRegistry`
- typed descriptors вместо сырого array-flow
- отдельный `TypeResolver`
- endpoint runtime profiles
- binary plugin protocol test contour

Эта часть была нужна, чтобы `endpoints`, `endpoint_validation` и `operation_manifest` стали честными production-grade модулями, а `operation_manifest` начал генерировать полноценные operation registry classes вместо array-based metadata files.

### Следующий этап развития

Следующая волна работы должна идти не в “ещё больше генераторов”, а в доведение protobuf-first flow верхнего уровня:

1. использовать generated operation registry classes как единственный endpoint metadata source of truth;
2. уменьшать ручную runtime-склейку между generated endpoints и handwritten endpoint implementations;
3. усиливать compile-time и verify-time проверки согласованности generated artifacts;
4. добавлять новые generator modules только под реальные reusable backend needs.

## Критерий хорошего развития

Хорошее развитие `protoc-php-gen`:

- усиливает protobuf-first backend flow;
- уменьшает ручной endpoint boilerplate;
- остаётся reusable вне одного конкретного API;
- развивается модулями, а не историческими слоями;
- не создаёт вторую архитектуру внутри проекта.
