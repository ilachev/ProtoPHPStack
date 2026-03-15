# Целевая архитектура

Этот документ описывает target state проекта: `production-ready` backend template на чистом PHP с vertical slices архитектурой.

## Главный принцип

Код организуется по feature slices, а не по техническим слоям верхнего уровня.

Вместо:

- `src/Domain`
- `src/Application`
- `src/Infrastructure`

целевая структура должна двигаться к виду:

```text
src/
  Modules/
    Home/
    Session/
    Auth/
    User/
  Platform/
    Http/
    Routing/
    Storage/
    Cache/
    Logging/
    Bootstrap/
    Testing/
  Shared/
    Contract/
    Support/
```

Это не догма по названиям каталогов, а модель разделения ответственности.

## Что такое slice

Slice — это автономная feature-область, которая содержит всё нужное для своей логики:

- входные сценарии;
- доменные модели;
- application services/use cases;
- transport adapters;
- repository contracts;
- infrastructure adapters, если они специфичны именно для этой feature.

## Пример целевой структуры slice

```text
src/Modules/Session/
  Domain/
    Session.php
    SessionRepository.php
    SessionService.php
  Application/
    CreateSession.php
    RefreshSession.php
    ValidateSession.php
  Transport/
    Http/
      SessionMiddleware.php
      SessionCookieWriter.php
    Mapping/
      SessionResponseMapper.php
  Infrastructure/
    Persistence/
      PostgreSqlSessionRepository.php
```

Важно: slice не обязан иметь все подкаталоги. Он должен иметь только те, которые реально нужны.

## Что остаётся в `Platform`

В `Platform` должно жить только то, что общее для всех slices и не выражает продуктовую логику:

- bootstrap приложения;
- request/response abstractions;
- pipeline executor;
- router runtime;
- low-level storage abstractions;
- connection factories;
- logging;
- cache;
- error normalization;
- console runtime;
- code generation runtime support, если он нужен приложению.

Если код можно назвать "feature-specific", ему не место в `Platform`.

## Что остаётся в `Shared`

`Shared` допустим только для двух случаев:

- действительно общие контракты;
- небольшие support utilities, не принадлежащие одной feature.

`Shared` не должен становиться новой свалкой.

## HTTP модель

Целевой шаблон должен использовать простой HTTP pipeline без framework coupling.

Подход:

- минимальные interfaces для request handler и middleware;
- router как платформенный сервис;
- feature handlers/use cases вызываются через thin transport adapter;
- нормализация ошибок и логирование выполняются platform middleware.

## Storage модель

Целевая стратегия:

- PostgreSQL — основной и единственный production storage backend;
- repository contracts определяются внутри slice;
- конкретные PostgreSQL repositories реализуются либо внутри slice, либо в `Platform/Storage`, если это truly generic механизм;
- SQLite слой должен быть либо явно признан dev-only fallback, либо удалён.

## Routing модель

Есть два допустимых направления:

### Вариант A. protobuf-first остаётся

Тогда:

- `.proto` остаются источником истины для публичного API;
- route generation сохраняется;
- transport contracts и runtime маршруты связаны формально.

### Вариант B. runtime routes становятся handwritten

Тогда:

- protobuf остаётся только как transport/schema layer, если вообще остаётся;
- routing перестаёт зависеть от code generation.

На текущем этапе предпочтительнее сохранить `protobuf-first`, но привести его в согласованное состояние.

## Code generation и tooling

Tooling должно быть отделено от runtime.

Целевое разделение:

- `tools/*` — генераторы, build-time scripts, proto tooling;
- `src/Platform/*` — только runtime code, который реально участвует в обработке запросов.

Если generated code нужен runtime, это должно быть явно отражено в документации и структуре.

## Как должен выглядеть новый slice для LLM

LLM должна иметь возможность добавить новую feature примерно по такой схеме:

1. создать `src/Modules/<FeatureName>`;
2. добавить transport contract;
3. добавить use case;
4. добавить handler/HTTP adapter;
5. добавить repository contract и implementation;
6. зарегистрировать slice в bootstrap;
7. добавить tests этого slice.

Если для добавления одной feature нужно трогать весь репозиторий, шаблон ещё не достиг целевого состояния.

## Архитектурные решения, которые стоит принять заранее

### 1. Session — это slice, а не глобальная инфраструктура

Сессии влияют на весь HTTP runtime, но их поведение — это feature policy, а не purely technical detail.

### 2. Auth должен быть отдельным slice

Сейчас auth размазан между proto, routes и заготовками. В target architecture auth должен стать полноценным модулем.

### 3. Home должен остаться только демонстрационным slice

Он полезен как reference implementation, но не должен диктовать структуру всего проекта.

### 4. DI не должна диктовать форму приложения

Контейнер — это platform utility. Архитектура должна оставаться понятной даже без чтения реализации контейнера.

## Целевой bootstrap flow

```text
Bootstrap
  -> load config
  -> create platform services
  -> register modules
  -> assemble router/pipeline
  -> start runtime
```

Идея в том, чтобы модуль регистрировал себя как slice, а не вручную протаскивался через десятки разрозненных service providers.

## Definition of Ready для начала переноса кода

Перед активной миграцией кода нужно, чтобы были согласованы:

- названия верхнеуровневых каталогов;
- роль `Modules`, `Platform`, `Shared`;
- судьба protobuf-first pipeline;
- судьба SQLite;
- судьба текущего hydrator/tooling слоя.
