# Vision шаблона

Этот документ фиксирует официальную цель проекта после переопределения scope.

## Цель проекта

Проект должен стать `production-ready` инфраструктурным шаблоном для backend-разработки на чистом PHP.

Ключевое слово здесь не "backend", а "инфраструктурный".

Шаблон не должен поставлять готовый продуктовый домен. Он должен поставлять набор устойчивых инфраструктурных и полуинфраструктурных строительных блоков, из которых уже собирается конкретный продукт.

Шаблон должен давать:

- минимальное и понятное runtime-ядро;
- нейтральную архитектурную основу без framework coupling;
- набор reusable capabilities;
- reference implementations как исполняемую документацию;
- структуру, понятную человеку и LLM.

## Что это означает practically

Репозиторий должен отвечать на вопрос:

"как собрать production backend на чистом PHP?"

а не на вопрос:

"какой именно продукт уже реализован внутри этого репозитория?"

Поэтому в шаблоне должны жить:

- `Platform` primitives;
- reusable capabilities;
- маленькие examples/reference modules.

И не должны жить:

- жёстко зашитый продуктовый домен;
- произвольные бизнес-сценарии, не являющиеся reusable capability;
- ощущение, что template уже является полуготовым SaaS.

## Что означает "чистый PHP"

Под "чистым PHP" в этом проекте понимается:

- отсутствие фреймворка как архитектурного центра;
- отсутствие library-first структуры;
- опора на локальные interfaces, сервисы и адаптеры;
- использование внешних пакетов как технических деталей, а не как каркаса приложения.

## Что означает "production-ready"

Шаблон считается production-ready, если он из коробки задаёт:

- стабильный bootstrap flow;
- предсказуемый request lifecycle;
- ясные расширяемые границы между platform и capability-кодом;
- PostgreSQL-first storage baseline;
- logging, metrics и error-handling baseline;
- понятный quality gate;
- явную схему, по которой из шаблона собирается конкретный продукт.

Production-ready здесь не означает "внутри уже реализованы auth, users и все остальные продуктовые сценарии".

## Новая архитектурная цель

Основная архитектурная цель проекта теперь формулируется так:

- маленький `Platform` core;
- reusable `Capabilities`;
- минимальные `Examples`;
- tooling, отделённый от runtime;
- LLM-friendly структура, в которой каждый слой имеет ограниченную ответственность.

Vertical slices по-прежнему полезны, но не как "срезы продукта", а как "срезы возможностей шаблона".

## Предпочтительная модель

Целевой шаблон должен быть устроен так:

- `src/Platform/*` — HTTP kernel, routing, bootstrap, storage abstractions, cache, logging, console/runtime support;
- `src/Capabilities/*` — reusable возможности вроде `Session`, `Auth primitives`, `Observability`, `RateLimit`, `Idempotency`;
- `src/Examples/*` — reference implementations, демонстрирующие, как capabilities собираются в продукт;
- `src/Shared/*` — действительно небольшие общие контракты и support utilities;
- `protos/*` — transport contracts, если protobuf-first сохраняется;
- `tools/*` — build-time/codegen tooling.

## Не-цели

Проект не должен стремиться быть:

- готовым продуктом;
- micro-framework с бесконечным API surface;
- свалкой utility-классов;
- коллекцией случайных demo-сценариев;
- шаблоном, в котором reference code неотличим от core runtime.

## Принципы проектирования

### 1. Infrastructure-first

Сначала определяются общие runtime primitives, а не продуктовые сценарии.

### 2. Capability-first

Если подсистема reusable между продуктами, она может жить в template как capability.

### 3. Examples are not core

`Home`, demo auth flow и похожие вещи допустимы только как reference code, а не как смысл проекта.

### 4. Small platform core

`Platform` должен быть маленьким, стабильным и техническим.

### 5. No framework gravity

Пакеты допустимы только как адаптеры.

### 6. Explicit boundaries

LLM должна сразу понимать:

- что является core runtime;
- что является reusable capability;
- что является example;
- что можно удалить без разрушения шаблона.

### 7. Replace product bias with reusable primitives

Если подсистема описана слишком прикладным языком, её надо либо обобщить, либо вынести в examples.

## Стратегическое решение по текущему репозиторию

Текущий репозиторий уже содержит полезные части для новой цели:

- `Platform` runtime;
- `Session` как сильную capability;
- `ApiStats` как потенциальную observability capability;
- `Auth` и `Home` как candidate reference implementations.

Но репозиторий ещё надо дочистить от product bias:

1. согласовать документацию под infrastructure-first модель;
2. удерживать явное разделение `Platform / Capabilities / Examples`;
3. понижать demo/product-specific код до reference implementation;
4. дочищать legacy-слои, которые всё ещё тянут проект обратно в product-first модель.
