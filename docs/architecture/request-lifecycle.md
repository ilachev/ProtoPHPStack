# Поток запроса

Этот документ описывает фактический runtime flow HTTP-запроса.

## Краткая схема

```text
Client
  -> RoadRunner HTTP
  -> public/index.php
  -> App
  -> DI container
  -> Pipeline
  -> Middleware chain
  -> Router
  -> Handler
  -> Mapper / JsonResponse
  -> Response
```

## 1. Вход в приложение

RoadRunner запускает PHP worker через:

```yaml
server:
  command: "php public/index.php"
```

Файл `public/index.php` создаёт `App` и вызывает `run()`.

## 2. Инициализация `App`

`src/Infrastructure/App.php` делает следующее:

1. загружает `config/container.php`;
2. создаёт `DIContainer`;
3. регистрирует сервисы через service providers;
4. получает `PSR7Worker`;
5. собирает middleware pipeline.

При старте приложения также вызывается полная очистка кеша через `CacheService`.

Это поведение влияет на bootstrap semantics и должно быть отдельно пересмотрено при реструктуризации: полная очистка кеша при запуске может быть приемлема для template/demo режима, но не всегда подходит для production-like сценария.

## 3. Получение запроса

Внутри `App::run()` работает бесконечный цикл:

- `waitRequest()`
- `handleRequest($request)`
- `respond($response)`

Это long-running модель, поэтому любой singleton-like объект живёт дольше одного запроса.

## 4. Выполнение middleware pipeline

Pipeline в проекте рекурсивный. Каждый middleware получает:

- текущий `ServerRequestInterface`;
- следующий `RequestHandler`.

Порядок выполнения:

1. `ErrorHandlerMiddleware`
2. `RequestMetricsMiddleware`
3. `SessionMiddleware`
4. `ApiStatsMiddleware`
5. `RoutingMiddleware`
6. `HttpLoggingMiddleware`
7. конечный `RouteHandlerResolver`

## 5. `SessionMiddleware`

Это ключевой этап, потому что он формирует request context.

Основные действия:

- ищет session ID в `Authorization: Bearer ...` или cookie;
- валидирует существующую сессию;
- если сессии нет, формирует `SessionPayload`;
- при необходимости создаёт новую анонимную сессию;
- для небраузерных клиентов может попытаться восстановить сессию по fingerprint;
- добавляет объект `session` в request attributes;
- после успешного ответа refresh-ит TTL и выставляет `Set-Cookie`.

В результате downstream код может считать, что `session` уже присутствует в request.

## 6. `ApiStatsMiddleware`

Измеряет время выполнения запроса и сохраняет запись в `api_stats`.

Что он использует:

- `session` из request attributes;
- route information после dispatch;
- HTTP method, status code, execution time.

Это cross-cutting concern, но он зависит от фактической модели сессий.

## 7. `RoutingMiddleware`

Вызывает router и определяет:

- найден ли маршрут;
- какой handler должен выполнить запрос;
- какие route params надо записать в request.

Если маршрут не найден, middleware сразу возвращает JSON error response.

Если маршрут найден, в request attributes кладутся:

- `routeParams`
- `handler`

## 8. `RouteHandlerResolver`

Финальный request handler в pipeline.

Его задача:

- взять имя handler из request attributes;
- разрешить handler через DI;
- вызвать `handle($request)`.

Таким образом, middleware не знает, как именно создаётся handler, а runtime resolution централизован.

## 9. Handler и mapper

На примере `HomeHandler` flow выглядит так:

1. handler вызывает `HomeService`;
2. получает domain result;
3. передаёт данные в `HomeMapper`;
4. mapper строит protobuf response model;
5. `JsonResponse` отдаёт JSON.

Именно здесь должен заканчиваться HTTP-адаптер и начинаться domain/application orchestration.

## 10. Возврат ответа

Response возвращается обратно через цепочку middleware.

На обратном пути возможны побочные эффекты:

- `SessionMiddleware` refresh-ит сессию и выставляет cookie;
- `ApiStatsMiddleware` сохраняет метрику;
- `HttpLoggingMiddleware` пишет лог;
- `ErrorHandlerMiddleware` перехватывает исключения и нормализует ошибку.

## Что должна помнить LLM

- Сессия создаётся до routing и handler.
- Route selection происходит не по контроллерам, а по сгенерированному route config.
- Не every `.proto` endpoint реально имеет законченный handler.
- Long-running runtime требует осторожности с кешами, статикой и состоянием сервисов.
