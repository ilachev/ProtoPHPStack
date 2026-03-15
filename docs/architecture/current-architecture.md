# Текущая архитектура

## Архитектурный стиль

Проект задуман как Clean Architecture с разделением на три слоя:

- `Domain` — бизнес-модели и доменные сервисы.
- `Application` — orchestration, handlers, middleware, mappers.
- `Infrastructure` — HTTP runtime, storage, DI, routing, cache, logging, code generation.

На практике архитектура ближе к "чистому ядру + плотный infrastructure shell", потому что большая часть текущей функциональности относится к инфраструктуре API-шаблона.

## Слой Domain

Содержит простые readonly-сущности и сервисы.

### `Domain/Home`

Минимальный демонстрационный use case: вернуть приветственное сообщение.

### `Domain/Session`

Самая зрелая бизнес-подсистема проекта.

Содержит:

- `Session` — доменную модель сессии;
- `SessionConfig` — конфигурацию сессионной подсистемы;
- `SessionRepository` — контракт доступа к данным;
- `SessionService` — создание, валидация, refresh, cleanup.

### `Domain/Stats`

Хранит метрики HTTP-вызовов:

- `ApiStat`;
- `ApiStatRepository`;
- `ApiStatService`.

### `Domain/User`

Минимальная заготовка пользователя:

- `User`;
- `UserRepository`;
- `UserService`.

Слой ещё не интегрирован в полноценный auth flow.

## Слой Application

Этот слой связывает HTTP-мир, доменные сервисы и response models.

### Handlers

На текущий момент полноценно реализован только `HomeHandler`.

Назначение handler:

- получить уже подготовленный request context;
- вызвать domain service;
- отдать response через mapper и `JsonResponse`.

Handler не должен:

- сам управлять DI;
- напрямую работать с БД;
- принимать инфраструктурные решения;
- содержать сложную бизнес-логику.

### Middleware pipeline

Пайплайн собирается в `App::createPipeline()` и выполняется в таком порядке:

1. `ErrorHandlerMiddleware`
2. `RequestMetricsMiddleware`
3. `SessionMiddleware`
4. `ApiStatsMiddleware`
5. `RoutingMiddleware`
6. `HttpLoggingMiddleware`

Это означает:

- сессия создаётся или восстанавливается до routing/handler;
- статистика может использовать session context;
- маршрутизация определяет handler уже после подготовки request context;
- логирование фактически оборачивает dispatch handler-а в конце цепочки.

### Client subsystem

В `src/Application/Client` сосредоточена логика, связанная с "отпечатком" запроса:

- извлечение признаков клиента из request headers;
- построение payload для сессии;
- определение похожих клиентов;
- геолокация по IP через abstraction `GeoLocationService`.

Это важная подсистема, но она смешивает прикладные и технические concerns. При реструктуризации её стоит оформить как отдельный bounded module.

### Mappers

`DataTransferObjectMapper` и специализированные mapper-ы используются как официальная точка преобразования domain data в transport model.

Это соответствует целям проекта:

- не смешивать domain и transport;
- не пробрасывать protobuf message classes в domain;
- держать conversion logic централизованно.

## Слой Infrastructure

### `Infrastructure/App`

Главный runtime bootstrap:

- создаёт контейнер;
- загружает service providers;
- создаёт RoadRunner worker;
- собирает pipeline;
- запускает request loop.

### DI

Проект использует собственный контейнер `DIContainer`:

- поддерживает `bind(interface, implementation)`;
- поддерживает factory definitions через `set`;
- умеет рекурсивно резолвить зависимости через reflection;
- кеширует singleton-like инстансы;
- проверяет циклические зависимости.

Контейнер небольшой, но критический: почти вся сборка runtime завязана на него.

### Routing

Routing разделён на два этапа:

1. генерация `config/routes.php` из `.proto`;
2. runtime dispatch через `Infrastructure\Routing\Router`.

Это не "авто-discovery" контроллеров. Источник маршрутов — proto annotations.

### Storage

Storage слой включает:

- абстракцию `Storage`;
- реализации `PostgreSQLStorage` и `SQLiteStorage`;
- query factories/builders;
- repositories;
- migrations.

Фактическая стратегия проекта — PostgreSQL-first. SQLite остаётся как legacy/compatibility слой и потенциальный кандидат на удаление при реструктуризации.

### Cache

Кеш реализован через RoadRunner KV и Redis:

- runtime configuration в `.rr.yaml`;
- приложение использует `CacheService`;
- сессии и геолокация используют кеш как технический ускоритель.

### Hydrator

В проекте одновременно присутствуют:

- reflection-based hydrator, который сейчас зарегистрирован как основной;
- заготовка для code-generating hydrator.

Это важный маркер незавершённой эволюции проекта. Перед реструктуризацией надо определить, какой hydrator остаётся основным и зачем.

## Реальные зависимости между слоями

Желаемая зависимость:

`Infrastructure -> Application -> Domain`

Фактическая картина сложнее:

- Domain размечен атрибутами `ProtoMapping`/`ProtoField`, то есть знает о генераторе;
- Application зависит от transport-ориентированных mapper-ов и protobuf адаптации;
- Infrastructure содержит часть логики, которая влияет на прикладное поведение.

Это не катастрофа, но это уже сигнал, что проекту нужна явная модульная перегруппировка.

## Главные архитектурные противоречия

### 1. Шаблон и демо-приложение смешаны

Репозиторий одновременно хочет быть:

- reusable API template;
- конкретным сервисом с home/auth/session/statistics.

Эти цели нужно разделить.

### 2. Контракты и реализация расходятся

`AuthService` описан в proto/OpenAPI/routes, но runtime-реализация не завершена.

### 3. Генераторы и runtime тесно соседствуют

`tools/protoc-php-gen`, proto domain mapping и hydrator generation уже влияют на архитектуру, но не оформлены как отдельный "tooling layer".

### 4. PostgreSQL-first стратегия не доведена до конца

В документах и инструкциях PostgreSQL объявлен основным вариантом, но SQLite слой и миграции всё ещё существуют.
