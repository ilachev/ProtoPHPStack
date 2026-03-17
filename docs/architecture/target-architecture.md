# Целевая архитектура

Этот документ описывает target state проекта как набора простых reusable backend-блоков.

## Главный принцип

Репозиторий не должен выглядеть как большой framework или полуготовый продукт. Он должен быть читаем как набор блоков:

- `Platform` — runtime core;
- `Capabilities` — reusable building blocks;
- `Examples` — примеры использования.

Текущая структура каталогов может оставаться такой, если она помогает держать границы простыми:

```text
src/
  Platform/
    Http/
    Routing/
    Runtime/
    Storage/
    Cache/
    Logging/
    Console/
    Config/
  Capabilities/
    Session/
    Auth/
    Observability/
  Examples/
    Home/
    Auth/
  Shared/
    Contract/
    Support/
```

## Что такое `Platform`

`Platform` — это код, без которого не работает runtime, но который не выражает продуктовую политику.

Там должно жить только общее:

- bootstrap приложения;
- request/response abstractions;
- middleware pipeline executor;
- router runtime;
- container wiring;
- low-level storage abstractions;
- cache;
- logging;
- error normalization;
- console runtime;
- runtime support для generated code.

Если код звучит как "конкретный сценарий продукта", ему не место в `Platform`.

## Что такое `Capabilities`

`Capabilities` — это просто папка для reusable-подсистем.

Они допускают свою внутреннюю вертикальную структуру и могут содержать:

- domain contracts;
- use cases;
- HTTP adapters;
- persistence adapters;
- capability-specific middleware;
- tests и примеры использования.

Такой блок отличается от product feature тем, что его можно использовать в разных продуктах без переписывания смысла.

Хорошие кандидаты:

- `Session`
- `Auth primitives`
- `Observability`
- `Rate limiting`
- `Idempotency`

Плохие кандидаты:

- `Home`
- `User profile page`
- `Billing portal`
- `Login by email/password` как единственный смысл auth-подсистемы

## Что такое `Examples`

`Examples` нужны как минимальная исполняемая документация.

Там допустимы:

- demo endpoints;
- example auth flow;
- минимальный sample API;
- integration examples, показывающие сборку capabilities в продукт.

Но examples не должны маскироваться под core runtime.

## Что остаётся в `Shared`

`Shared` допустим только для:

- действительно общих контрактов;
- маленьких support utilities;
- value objects, не принадлежащих одной capability.

`Shared` не должен превращаться в новую свалку legacy-кода.

## Внутренняя структура reusable-блока

Внутри блока можно оставлять удобную вертикальную структуру, если она помогает чтению кода.

Пример:

```text
src/Capabilities/Session/
  Domain/
  Application/
  Transport/
    Http/
  Infrastructure/
    Persistence/
```

Это означает: структура допустима любая, если она остаётся простой и обслуживает reusable-блок, а не продуктовую предметную область шаблона.

## HTTP модель

Целевой шаблон по-прежнему должен использовать простой HTTP pipeline без framework coupling.

Подход:

- минимальные interfaces для request handler и middleware;
- router как platform service;
- capability handlers подключаются как thin transport adapters;
- platform middleware занимается только runtime concerns;
- capability middleware занимается только своей reusable capability.

## Storage модель

Целевая стратегия:

- `PostgreSQL` остаётся основным runtime adapter;
- generic storage abstractions живут в `Platform`;
- block-specific repositories живут рядом со своим блоком;
- unit и default verify не должны требовать поднятую БД;
- дополнительные storage profiles допустимы только как адаптеры, а не как смысл проекта.

## Routing модель

Предпочтительное направление на текущем этапе:

- сохранить `protobuf-first`;
- но трактовать protobuf как transport contract layer, а не как признак продуктового домена.

Иными словами:

- `.proto` описывают API surface;
- `Platform` исполняет маршрутизацию;
- `Capabilities` и `Examples` поставляют handlers.

## Code generation и tooling

Tooling должно быть отделено от runtime:

- `tools/*` — build-time/codegen;
- `src/Platform/*` — только runtime;
- generated code, нужный runtime, должен быть явно документирован.

## Как должна мыслить LLM

LLM должна быстро отвечать на вопросы:

1. это platform code, reusable block или example code;
2. это reusable primitive или продуктовая политика;
3. это надо упростить, вынести в example или удалить;
4. какие части нужно затронуть, чтобы добавить новый reusable-блок.

Если ответ требует чтения половины репозитория, структура ещё недостаточно ясна.

## Архитектурные решения, которые уже можно принять

### 1. `Session` — reusable block

Это reusable building block, а не домен продукта.

### 2. `Auth` должен быть разделён

В template можно оставить только auth primitives и example flow.
Конкретный login policy не должен быть смыслом core template.

### 3. `ApiStats` — candidate observability block

Если он выражается как generic request stats, он остаётся.
Если он завязан на продуктовые сценарии, его надо упростить.

### 4. `Home` — только example

`Home` полезен как smoke-test/example endpoint, но не как архитектурный центр.

### 5. `User` не должен быть top-level core domain

Если нужен `User`, то только как часть example или отдельного продукта поверх шаблона.

## Целевой bootstrap flow

```text
Bootstrap
  -> load config
  -> create platform services
  -> register reusable blocks
  -> optionally register examples
  -> assemble router/pipeline
  -> start runtime
```

Важно: examples не должны быть обязательной частью runtime-ядра, а структура не должна быть сложнее, чем требует реальная переиспользуемость.

## Definition of Ready для дальнейшей миграции

Перед активным переносом кода должны быть согласованы:

- целевые роли `Platform`, `Capabilities`, `Examples`, `Shared`;
- судьба protobuf-first pipeline;
- роль optional storage adapters;
- судьба hydrator/tooling слоя;
- что именно из текущего кода считается capability, а что example.

## Текущее состояние репозитория

Физическое разделение уже начато и должно считаться каноническим:

- `src/Platform/*` — runtime core;
- `src/Capabilities/Session` — reusable session block;
- `src/Capabilities/ApiStats` — candidate observability block;
- `src/Examples/Home` — smoke-test example endpoint;
- `src/Examples/Auth` — example auth flow поверх capability-слоя.

Следующий уровень работы теперь не "переименовать `Modules`", а дочистить границы:

- отделить auth primitives от example auth flow;
- удерживать support-код в узких runtime-каталогах, а не собирать его обратно в широкий `Infrastructure`;
- привести onboarding и текущую документацию к языку simple reusable blocks, а не к языку сверх-архитектуры.
