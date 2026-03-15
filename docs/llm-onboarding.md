# LLM Onboarding

Этот документ предназначен для быстрой загрузки контекста в LLM перед анализом, разработкой и реструктуризацией проекта.

## Что это за проект

`base-api-template` — это шаблон для REST API на PHP 8.4 с упором на:

- `protobuf-first` описание API-контрактов;
- генерацию SDK-классов, OpenAPI и маршрутов из `.proto`;
- запуск на RoadRunner вместо классического PHP-FPM;
- PostgreSQL как основном хранилище;
- Redis/RoadRunner KV для кеша;
- Clean Architecture как базовую организацию кода.

Проект одновременно является:

- инфраструктурным шаблоном для будущих API-сервисов;
- демонстрационной реализацией нескольких подсистем: home endpoint, sessions, fingerprinting, geolocation, api stats.

Это важно: репозиторий не является завершённым продуктом. В нём уже есть контракты для auth API, но полноценная реализация auth-сценариев в коде отсутствует.

## Какая теперь официальная цель

Официальная цель проекта зафиксирована так:

- `production-ready` backend template;
- чистый PHP без framework-зависимости;
- минимальная архитектурная зависимость от внешних библиотек;
- `vertical slices architecture` как целевая модель организации кода.

Подробно это описано в `docs/restructure/template-vision.md` и `docs/architecture/target-architecture.md`.

## Главные точки входа

- HTTP entrypoint: `public/index.php`
- Runtime bootstrap: `src/Infrastructure/App.php`
- DI configuration: `config/container.php`
- Маршруты runtime: `config/routes.php`
- Источник API-контрактов: `protos/proto/app/v1/*.proto`
- Команды разработки: `taskfile.yaml`

## Быстрая карта каталогов

### `src/Domain`

Чистые модели и доменные сервисы.

- `Home` — демонстрационный сервис домашней страницы.
- `Session` — модель сессии, конфиг, репозиторий и сервис управления сессиями.
- `Stats` — модель и сервис статистики API вызовов.
- `User` — минимальная модель пользователя и сервис создания/поиска.

### `src/Application`

Координация HTTP-сценариев и преобразование данных.

- `Handlers` — адаптеры HTTP -> application/domain.
- `Middleware` — pipeline из кросс-срезовых функций.
- `Mappers` — преобразование domain -> proto/response model.
- `Client` — сбор отпечатка клиента, геолокация, session payload.
- `Routing`, `Http`, `Error` — прикладные abstractions для HTTP runtime.

### `src/Infrastructure`

Техническое исполнение.

- `DI` — собственный контейнер зависимостей.
- `Routing` — runtime router и генерация `config/routes.php`.
- `Storage` — PostgreSQL/SQLite storage, query builder, migrations, repositories.
- `Hydrator` — hydrator слой и code generation для маппинга.
- `GeoLocation` — IP2Location интеграция.
- `Cache` — RoadRunner KV / Redis кеш.
- `Logger`, `Console` — логирование и консольные команды.

### `protos`

- `protos/proto/app/v1` — публичные API-контракты.
- `protos/proto/app/domain` — proto-описания доменных сущностей и custom options.
- `protos/gen` — сгенерированные PHP-классы protobuf.

### `tools/protoc-php-gen`

Локальный генератор, который использует атрибуты и/или proto-описания для генерации маппинга/hydrator-кода. Это отдельная важная подсистема проекта, а не случайная утилита.

## Что реально работает сейчас

- `GET /api/v1/home`
- создание и обновление анонимных сессий;
- восстановление сессии по cookie/bearer;
- fingerprint matching клиента;
- геолокация по IP;
- запись статистики API-вызовов;
- миграции PostgreSQL;
- unit и integration tests.

## Что задекларировано, но не доведено до полноценной реализации

- контракты `AuthService` в `.proto`;
- маршруты auth в `config/routes.php`;
- OpenAPI-описание auth эндпоинтов;
- базовая `User` модель и repository/service заготовки.

Следствие: при реструктуризации нельзя считать `.proto`, `OpenAPI` и текущий runtime полностью согласованными.

## Основные инварианты для безопасной разработки

- Не редактировать вручную `protos/gen/*`.
- Не редактировать вручную `config/routes.php`; файл генерируется из `.proto`.
- Для нового публичного API сначала правится `.proto`, затем запускается генерация.
- Domain не должен зависеть от Infrastructure и protobuf message classes.
- Handler должен быть тонким адаптером.
- Преобразования между слоями должны делаться через mapper/hydrator, а не через ad-hoc массивы по всему коду.
- Для storage по умолчанию используется PostgreSQL, даже если в кодовой базе ещё остались SQLite-артефакты.
- Проект работает в long-running процессе RoadRunner, поэтому надо учитывать память, кеши и накопление состояния между запросами.

## Команды, которые нужно знать LLM

```bash
task install
task proto:gen:all
task lint
task phpstan
task test
task verify
task services:start
task services:stop
task run
```

## Минимальная стратегия чтения кода

Если нужно быстро понять поведение проекта, читать в таком порядке:

1. `docs/architecture/request-lifecycle.md`
2. `src/Infrastructure/App.php`
3. `config/container.php`
4. `src/Infrastructure/DI/ServiceProviders/*`
5. `src/Application/Middleware/*`
6. `src/Application/Handlers/*`
7. `src/Domain/*`
8. `src/Infrastructure/Storage/*`
9. `protos/proto/app/v1/*`

## Главные текущие зоны риска

- Архитектурная граница между шаблонной инфраструктурой и демонстрационным приложением пока размыта.
- В проекте смешаны runtime-реализация, генераторы, протоколы и демонстрационный функционал.
- Часть кода ориентирована на PostgreSQL-only стратегию, но SQLite-слой ещё присутствует.
- Контракты API и фактическая реализация местами расходятся.
- В репозитории уже есть незавершённый переход к code-generating hydrator, но он пока не является основным runtime-механизмом.

## Как теперь интерпретировать изменения

Если LLM вносит изменения в проект, она должна оценивать их не только относительно текущего кода, но и относительно target state:

- приближает ли изменение структуру к vertical slices;
- уменьшает ли framework/library coupling;
- делает ли platform слой более маленьким и ясным;
- помогает ли это прийти к production-ready шаблону, а не просто чинит локальный симптом.
