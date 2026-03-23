# `Platform/Http/Client` как продукт

## Цель

`Platform/Http/Client` должен стать маленьким reusable reliability block для outbound HTTP, а не просто удобной обёрткой над `curl`.

Его задача:

- давать typed request/response contracts;
- учитывать partial failures как нормальный сценарий;
- держать deadline/timeout budget на уровне всего запроса, а не только отдельной попытки;
- повторять только idempotent запросы и только для transient failure modes;
- делать retry/backoff/jitter частью generic policy layer, а не куском ad-hoc логики в каждом consumer-е;
- оставаться transport-agnostic на уровне core contracts.

## Что считать canonical model

Слой должен мыслиться так:

```text
HttpClient
  -> retry / backoff / deadline budget / error classification
  -> HttpTransport
  -> concrete transport adapter (currently cURL)
```

То есть:

- `HttpClient` отвечает за reliability policy;
- `HttpTransport` отвечает только за одну попытку отправки;
- concrete transport не должен сам принимать product-level решения о retries.

## Что уже считается supported core

Сейчас canonical runtime surface:

- `HttpRequest`
- `HttpResponse`
- `HttpRequestOptions`
- `RetryPolicy`
- `Deadline`
- `HttpClient`
- `HttpTransport`
- `ResilientHttpClient`
- `CurlTransport`

Их роли:

- `HttpRequest` — typed outbound request contract;
- `HttpResponse` — typed response contract без привязки к consumer logic;
- `HttpRequestOptions` — request-scoped policy knobs;
- `RetryPolicy` — retry/backoff policy;
- `Deadline` — единый timeout budget для всего запроса;
- `ResilientHttpClient` — orchestration layer;
- `CurlTransport` — low-level HTTP transport adapter.

## Текущий supported scope

На текущем этапе поддерживается:

- обычный request/response flow с полным body в памяти;
- cURL transport;
- request-level timeout budget;
- connect timeout;
- idempotent retries на transport failures;
- retries на retryable upstream statuses (`429`, `500`, `502`, `503`, `504`);
- exponential backoff with jitter;
- redirects;
- structured retry logging.

## Что пока осознанно не поддерживается

Пока не надо добавлять без сильного кейса:

- streaming response bodies;
- connection pooling abstraction как отдельный словарь;
- circuit breaker как первый-class слой;
- client-side rate limiting;
- hedged requests;
- HTTP/2/HTTP/3-specific policy surface;
- generic PSR-18 wrapper как архитектурный центр.

## Правила развития

### 1. Reliability logic не должна протекать в consumer code

Capability code должно описывать:

- URI;
- method;
- request body;
- timeout/retry intent;
- upstream name.

Но не должно руками реализовывать:

- retry loops;
- backoff sleeps;
- timeout budgeting;
- ad-hoc transport classification.

### 2. `HttpTransport` не должен становиться policy layer

Concrete transport adapter не должен:

- принимать решение, retry нужен или нет;
- скрыто запускать несколько попыток;
- содержать product-specific error handling.

### 3. Non-idempotent requests по умолчанию не должны retry-иться

Если запрос небезопасен для повторной отправки, это должно быть явно выражено в `HttpRequestOptions`.

### 4. Логирование должно быть diagnostic-friendly

Retry/failure logs должны содержать:

- `uri`
- `method`
- `upstream`
- `attempt`
- `status_code` или `error`

### 5. Consumer wrappers должны жить вне platform core

Клиенты уровня:

- `Ip2LocationDownloadClient`
- `TelegramBotClient`
- `WebhookClient`

должны жить в capability/infrastructure слоях, а не внутри `Platform/Http/Client`.

## Первый реальный consumer

Первым consumer-ом уже считается:

- `UpdateGeoIPCommand`

Это важно, потому что outbound client теперь проверяется не только в unit tests, но и на реальном capability use case.

## Следующий roadmap

Следующие сильные шаги:

1. richer error taxonomy:
   - DNS/TLS/connect/read timeout distinctions
2. typed upstream wrappers поверх generic client
3. request/response body codecs:
   - JSON helpers
   - binary download helpers
4. metrics hooks:
   - attempt count
   - final status/failure class
   - latency budget usage
5. bounded concurrency / per-upstream policy, только если появится реальный workload

## Чего не надо делать дальше

- не превращать этот слой в framework over PSR standards;
- не тащить тяжёлый external HTTP client как архитектурный центр;
- не смешивать transport layer с domain mapping;
- не строить generic “SDK factory” словарь раньше времени.
