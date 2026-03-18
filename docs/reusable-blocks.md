# Карта reusable-блоков

Этот документ фиксирует главный ориентир проекта: какие блоки реально составляют шаблон, зачем они нужны и как их трактовать при дальнейшей разработке.

## Как понимать блок

Блок стоит оставлять в шаблоне, если он:

- нужен многим современным backend-проектам;
- может быть использован без привязки к одной бизнес-модели;
- не навязывает продуктовую политику;
- понятен как самостоятельный строительный элемент.

Если кусок кода этим критериям не соответствует, ему место либо в `Examples`, либо вне шаблона.

## Core runtime blocks

### `Platform/Runtime`

- Роль: bootstrap приложения и запуск pipeline.
- Обязательность: обязательный core.
- Что здесь считается core: создание `App`, сборка middleware pipeline, запуск worker loop.
- Что не должно попадать сюда: продуктовые сценарии и feature-specific policy.

### `Platform/Http`

- Роль: request/response abstractions, handler contracts, middleware interfaces.
- Обязательность: обязательный core.
- Что здесь считается core: универсальная HTTP-модель шаблона.
- Что не должно попадать сюда: конкретные auth/session/business правила.

### `Platform/Routing`

- Роль: runtime routing и support для route generation.
- Обязательность: обязательный core.
- Что здесь считается core: dispatch, route result, route definition, proto-based route generation support.
- Что не должно попадать сюда: feature-specific handler logic.

### `Platform/DI`

- Роль: wiring runtime и блоков.
- Обязательность: обязательный core.
- Что здесь считается core: container, service providers, registration rules.
- Что не должно попадать сюда: продуктовые зависимости и example policy.

## Support blocks

### `Platform/Storage`

- Роль: storage abstractions, repositories, query builders, migrations.
- Обязательность: обязательный reusable block для backend template.
- Что здесь считается core: generic persistence API, migrations, adapters.
- Optional-часть: дополнительные storage profiles и engine-specific tuning.

### `Platform/Cache`

- Роль: cache abstraction и runtime backend integration.
- Обязательность: optional reusable block.
- Что здесь считается core: единый cache contract и fallback-safe runtime behavior.
- Optional-часть: конкретный backend вроде RoadRunner KV.

### `Platform/Logging`

- Роль: минимальный logging contract и runtime logger implementations.
- Обязательность: обязательный support block.
- Что здесь считается core: простой logging API без framework coupling.
- Optional-часть: конкретные output formats и runtime integrations.

### `Platform/Console`

- Роль: маленькие operational commands вокруг runtime.
- Обязательность: optional support block.
- Что здесь считается core: команды, которые обслуживают reusable runtime pieces.
- Что не должно попадать сюда: product-only CLI сценарии.

### `Platform/Hydration` и `Platform/DataMapping`

- Роль: преобразование между storage/runtime structures и PHP objects.
- Обязательность: reusable support block.
- Что здесь считается core: reflection/codegen hydration support, DTO mapping.
- Зона внимания: не раздувать этот слой абстракциями без явной пользы.

## Capability blocks

### `Capabilities/Session`

- Роль: reusable session management block.
- Обязательность: сильный reusable block, нормальный кандидат для многих backend-ов.
- Что здесь считается core: session lifecycle, cookie/bearer restore, fingerprinting, client detection, geolocation hooks.
- Optional-часть: конкретные payload conventions и enrichments.

### `Capabilities/ApiStats`

- Роль: маленький add-on для записи request log.
- Обязательность: optional reusable block.
- Что здесь считается core: middleware, который пишет факт API-вызова в storage.
- Что не должно происходить: превращение в большую analytics/observability платформу внутри шаблона.

## Example blocks

### `Examples/Home`

- Роль: smoke-test endpoint.
- Обязательность: не core, только reference.
- Назначение: показать минимальную сборку handler/service/mapper.

### `Examples/Auth`

- Роль: demo `email/password` flow поверх session block.
- Обязательность: не core, только reference.
- Назначение: показать, как reusable session block собирается с конкретной login policy.
- Что не должно трактоваться как core: сам login flow, user model и auth policy.

## Как применять эту карту

Перед любым изменением нужно ответить на четыре вопроса:

1. Это core runtime block, support block, capability block или example?
2. Это reusable building block или конкретная product policy?
3. Это должно быть обязательной частью шаблона или optional add-on?
4. Улучшает ли изменение reuse-value блока, а не просто увеличивает архитектурный словарь?

## Практический критерий качества

Проект движется в правильную сторону, если новый backend можно собирать так:

- брать `Platform` как runtime base;
- подключать только нужные `Capabilities`;
- использовать `Examples` как reference, а не как обязательный слой;
- не переписывать смысл блоков под конкретный продукт.
