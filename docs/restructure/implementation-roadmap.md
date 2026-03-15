# Roadmap реализации шаблона

Этот документ описывает путь от текущего transitional состояния к infrastructure-first template.

## Общая стратегия

Цель не "добавить ещё несколько модулей", а убрать product bias из шаблона и оставить только:

- platform core;
- reusable capabilities;
- examples как исполняемую документацию.

Каждый этап должен уменьшать архитектурный шум, а не увеличивать его.

## Этап 1. Зафиксировать новую цель

Нужно:

- переписать vision под infrastructure-first модель;
- зафиксировать новую target architecture;
- описать карту `Platform / Capabilities / Examples`.

Результат:

- человек и LLM понимают, что репозиторий больше не трактуется как полуготовый продукт.

## Этап 2. Дать классификацию текущему коду

Для каждого крупного участка кода нужно принять одно из решений:

- оставить в `Platform`;
- оставить как reusable capability;
- понизить до example;
- удалить как архитектурный шум.

Это нужно сделать до дальнейших переносов директорий.

## Этап 3. Стабилизировать `Platform`

`Platform` должен стать маленьким и явно ограниченным.

Туда остаются только:

- bootstrap;
- runtime;
- HTTP abstractions;
- router;
- pipeline;
- config;
- generic storage/cache/logging/console support.

Критерий успеха:

- в `Platform` нет продуктовой политики;
- capability-код не зависит от product examples.

## Этап 4. Выделить reusable capabilities

Предварительная целевая классификация:

1. `Session` — capability;
2. `ApiStats` -> `Observability` capability, если останется generic;
3. `Auth` — capability primitives плюс reference flow;
4. всё, что не reusable, не должно оставаться capability.

Критерий успеха:

- capability можно перенести в другой backend без смены её смысла.

## Этап 5. Понизить examples до examples

Следующие вещи должны быть явно отнесены к reference code:

- `Home`;
- demo auth endpoint flow;
- demo user model;
- любой код, который нужен только для показа работы шаблона.

Examples не должны быть обязательной частью core runtime.

## Этап 6. Пересобрать bootstrap под platform + capabilities + examples

Целевая модель:

- platform регистрируется отдельно;
- capabilities регистрируются явно;
- examples подключаются поверх capability-слоя;
- приложение может запускаться как в "bare template", так и в "reference app" режиме.

Даже если физически это останется в одном runtime, границы должны быть очевидны в коде и документации.

## Этап 7. Упростить инфраструктурный хвост

Нужно принять решения по спорным зонам:

- SQLite остаётся только как вторичный dev/test инструмент или удаляется;
- `ReflectionHydrator` и `CodeGeneratingHydrator` либо получают чёткую роль, либо сокращаются;
- tooling окончательно отделяется от runtime;
- proto/codegen pipeline описывается как инфраструктурный механизм, а не как признак продуктового домена.

## Этап 8. Финализировать example layer

В репозитории должен остаться минимальный набор reference implementations:

- smoke-test endpoint;
- session example;
- auth example;
- observability example.

Этот слой должен быть маленьким и явно вторичным.

## Этап 9. Финализировать developer/LLM ergonomics

Итоговый шаблон должен быстро отвечать на вопросы:

- куда добавлять новую capability;
- как собрать из capabilities конкретный backend;
- что является example и может быть заменено;
- какие артефакты надо регенерировать;
- какой checklist нужен перед merge.

## Критерии завершённости шаблона

Шаблон можно считать достигшим целевого состояния, если:

- `Platform` мал и стабилен;
- `Capabilities` не несут продуктовой предметной области;
- examples отделены от core;
- runtime не зависит от framework;
- transport contracts и runtime согласованы;
- документация объясняет не только "как устроено сейчас", но и "что является core, а что reference";
- `task verify` остаётся основным quality gate.

## Практический следующий шаг

После обновления документации следующий правильный шаг — составить и реализовать карту переноса:

1. зафиксировать transition policy для `src/Modules`;
2. определить, какие текущие `Modules` становятся `Capabilities`;
3. определить, какие модули переедут в `examples`;
4. после этого уже продолжать физическую реструктуризацию каталогов.
