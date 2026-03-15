# Карта границ

Этот документ фиксирует, как следует трактовать текущий код проекта в новой infrastructure-first модели.

## Цель карты

Она нужна, чтобы LLM и человек не делали следующую ошибку:

- принимать demo/product-specific код за core template;
- развивать examples как будто это обязательная часть платформы.

## Новая шкала классификации

Каждый участок кода должен попадать в одну из категорий:

1. `Platform`
2. `Capability`
3. `Example`
4. `Legacy / remove`

## Что считать `Platform`

В `Platform` должно остаться:

- HTTP runtime;
- middleware pipeline;
- routing runtime;
- bootstrap;
- generic DI wiring;
- generic storage abstractions;
- cache/logging/config/console support.

### Текущие кандидаты

- `src/Platform/*`
- generic service providers в `src/Infrastructure/DI/ServiceProviders/*`
- generic storage/query abstractions
- RoadRunner bootstrap

## Что считать `Capability`

Capability — это reusable building block.

### Текущие кандидаты

- `Session`
- `ApiStats`, но только если он будет переосмыслен как generic request observability capability
- `Auth`, но только в части auth primitives и request/session integration

### Что должно быть убрано из capability

- жёсткая привязка к одному способу входа;
- продуктовые сущности без reusable смысла;
- demo-only endpoints.

## Что считать `Example`

Example — это reference implementation, показывающая использование платформы и capabilities.

### Текущие кандидаты

- `Home`
- текущий HTTP flow `Auth login/logout/refresh`
- demo user model, если он нужен только для примера

Examples допустимы, но должны быть явно вторичны по отношению к `Platform` и `Capabilities`.

## Что считать `Legacy / remove`

Сюда попадает код, который:

- дублирует уже вынесенный runtime;
- не имеет явной reusable ценности;
- тянет репозиторий обратно в product-first структуру;
- затрудняет понимание репозитория для LLM.

### Текущие зоны риска

- остатки top-level `src/Domain`, `src/Application`, `src/Infrastructure` как архитектурных центров;
- `User` как будто это core domain template;
- любые compatibility layers, существующие только ради старой структуры;
- unclear hydrator/tooling code без зафиксированной роли.

## Текущее физическое разделение

Сейчас репозиторий уже использует явные каталоги:

- reusable код живёт в `src/Capabilities/*`;
- reference code живёт в `src/Examples/*`;
- runtime core живёт в `src/Platform/*`.

Это нужно считать рабочей моделью проекта, а не только архитектурным намерением.

## Решения, которые уже можно применять

### `Session`

Трактовать как capability.

### `ApiStats`

Трактовать как candidate capability, если язык и интерфейсы будут сделаны generic.

### `Auth`

Разделять на:

- auth primitives -> capability;
- конкретный login flow -> example.

### `Home`

Трактовать только как example.

### `User`

Не трактовать как core template domain.

## Как использовать эту карту при разработке

Перед любой новой реализацией нужно проверить:

1. это core runtime, capability или example;
2. это reusable primitive или конкретная продуктовая policy;
3. это нужно обобщить, вынести в example или удалить;
4. не растаскивает ли изменение template обратно в продуктовую форму.
