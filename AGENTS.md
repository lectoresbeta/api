# AGENTS.md

## Project overview

Lectores Beta is a backend application built with **PHP**, **Symfony** and **PostgreSQL**.

The codebase follows **Domain-Driven Design (DDD)** and is organized around **bounded contexts**. The architecture must preserve strong boundaries between business areas and strict separation between the **Domain**, **Application** and **Infrastructure** layers.

Main goals:

- keep business logic independent from frameworks and infrastructure;
- avoid coupling between bounded contexts;
- make dependencies explicit and controlled;
- keep each use case easy to understand, test and evolve;
- prevent Symfony, Doctrine or PostgreSQL concerns from leaking into the domain model;
- keep the API contract documented and stable;
- maintain enough automated quality controls without creating unnecessary complexity.

---

## Core principles

- Follow **Domain-Driven Design**.
- Apply **SOLID** principles throughout the codebase.
- Prefer explicit, simple and maintainable code over unnecessary abstraction.
- Respect the existing project structure and conventions.
- **All source code must be written in English.** This includes class names, method names, variable names, namespaces, enums, constants, exceptions, events, commands, queries, DTOs, database-related code, test names and code comments.
- Do not introduce Spanish names in code, even when the product terminology or documentation is written in Spanish. Translate the concept to a clear and consistent English name.
- Business rules belong in the **Domain** layer.
- Application orchestration belongs in the **Application** layer.
- Framework, persistence, transport and external integrations belong in the **Infrastructure** layer.
- Dependencies must always point inward.
- **Domain must never depend on Application or Infrastructure.**
- **Application may depend on Domain, but never on Infrastructure.**
- **Infrastructure may depend on Application and Domain.**
- A bounded context must never directly depend on implementation classes from another bounded context.
- Do not introduce direct entity, repository, service or infrastructure references across bounded contexts.
- Cross-context communication must happen through explicit contracts, messages, events or other integration mechanisms that preserve context independence.
- Avoid shared business models between bounded contexts.
- Do not move business logic into controllers, Doctrine repositories, subscribers or framework services.

---

# Architecture

## Bounded contexts

The first level under `src/` represents the application's **bounded contexts**.

Example:

```text
src/
    User/
    Work/
    Reading/
    Feedback/
    Community/
    Notification/
    Credits/
    Shared/
```

The concrete bounded contexts may evolve with the product. Do not create a new bounded context without a clear business boundary.

A bounded context:

- owns its business model;
- owns its persistence model and repository contracts;
- owns its use cases;
- exposes only explicit integration points;
- must remain independently understandable;
- must not rely on internal classes from another bounded context.

The same business concept may have different representations in different bounded contexts. Do not reuse another bounded context's entity merely because both concepts share a similar name.

---

## Credits bounded context

Everything related to the **credits system** must live in its own independent bounded context:

```text
src/
    Credits/
        <Concept>/
            Domain/
            Application/
            Infrastructure/
```

The Credits bounded context is intentionally isolated because the credits model is expected to evolve significantly over time.

This isolation is a hard architectural rule.

The Credits bounded context:

- owns all rules related to earning, spending, adjusting, reserving or otherwise modifying credits;
- owns the user's credit balance and the business rules that determine how it changes;
- owns the persistence required by the credits system;
- must not depend directly on entities, repositories, application services or infrastructure from other bounded contexts;
- must not be called directly by another bounded context to apply credit effects;
- must not require other bounded contexts to know how credits are calculated or stored.

Other bounded contexts must remain unaware of the internal rules of Credits.

When an action occurring elsewhere in the system has an effect on credits, the originating bounded context must publish an **integration event describing the business fact that occurred**.

Examples:

```text
FeedbackSubmitted
UsefulFeedbackReceived
ManuscriptRead
ReadingCompleted
ContributionAccepted
```

The event should describe what happened in the originating bounded context. It should not tell Credits how many credits to add or remove unless that value is genuinely part of the originating context's business fact.

The Credits bounded context subscribes to all integration events that may affect credits and independently determines the corresponding credit effect.

Example:

```text
Feedback bounded context
    FeedbackSubmitted
            |
            v
         RabbitMQ
            |
            v
Credits bounded context
    FeedbackSubmittedHandler
            |
            v
    Credit rules are evaluated
            |
            v
    Credit balance is updated
```

This ensures that:

- the originating bounded context does not know the credit rules;
- changing the credit model does not require modifying unrelated bounded contexts;
- new credit rules can be introduced by subscribing to existing events;
- the Credits bounded context can evolve independently;
- credit-related persistence remains isolated;
- the rest of the application remains decoupled from the credits implementation.

Do not introduce synchronous calls such as:

```text
Feedback -> CreditsService::addCredits(...)
Reading  -> CreditRepository
Work     -> Credits\Application\...
```

Those dependencies are forbidden.

If another bounded context needs to react to a change that occurred inside Credits, Credits should publish its own integration event rather than exposing its internal model.

Examples may include:

```text
CreditsAdded
CreditsSpent
CreditBalanceChanged
```

Consumers must depend only on the event contract, never on Credits entities or repositories.

---

## Structure inside each bounded context

Inside each bounded context, code is grouped first by **business concept or capability**.

Only below that second level is the code divided into DDD layers.

```text
src/
    Work/
        Manuscript/
            Domain/
            Application/
            Infrastructure/

        Chapter/
            Domain/
            Application/
            Infrastructure/

        AccessRequest/
            Domain/
            Application/
            Infrastructure/
```

Another example:

```text
src/
    Feedback/
        Comment/
            Domain/
            Application/
            Infrastructure/

        Review/
            Domain/
            Application/
            Infrastructure/
```

The required structure is therefore:

```text
src/
    <BoundedContext>/
        <Concept>/
            Domain/
            Application/
            Infrastructure/
```

Do not organize the whole project globally by layer:

```text
src/
    Domain/
    Application/
    Infrastructure/
```

That structure is not allowed.

Do not place all code from a bounded context directly under its three layers when several independent concepts exist inside the bounded context. The business concept is the second organizational level.

---

# Dependency rules

The dependency direction is:

```text
Infrastructure
      ↓
Application
      ↓
Domain
```

Dependencies must never point in the opposite direction.

## Domain

May depend on:

- PHP language features;
- other Domain classes from the same concept;
- carefully selected domain abstractions from the same bounded context;
- minimal shared primitives from `Shared` when genuinely generic.

Must not depend on:

- Symfony;
- Doctrine;
- PostgreSQL;
- controllers;
- HTTP;
- serializers;
- framework events;
- Messenger implementation details;
- filesystem;
- external APIs;
- infrastructure services.

## Application

May depend on:

- Domain classes from the same bounded context;
- application DTOs;
- commands and queries;
- repository interfaces;
- ports/interfaces for infrastructure dependencies;
- application services.

Must not depend on:

- Doctrine repositories;
- Symfony controllers;
- concrete HTTP clients;
- concrete persistence implementations;
- database-specific code;
- infrastructure services.

## Infrastructure

May depend on:

- Application;
- Domain;
- Symfony;
- Doctrine;
- PostgreSQL integration;
- HTTP clients;
- messaging systems;
- filesystem;
- third-party SDKs;
- external services.

Infrastructure implements the contracts defined by inner layers.

---

# Bounded context isolation

Bounded contexts must not be coupled to each other's internals.

A bounded context must not:

- instantiate entities from another bounded context;
- inject another bounded context's repository;
- query another bounded context's Doctrine tables directly;
- depend on another bounded context's infrastructure services;
- modify another bounded context's aggregate;
- reuse another bounded context's internal application service.

When one bounded context needs information or behavior from another, use one of these approaches:

- a domain or integration event;
- Symfony Messenger;
- RabbitMQ as the asynchronous transport;
- an explicit integration interface when synchronous communication is genuinely required;
- a query contract;
- a dedicated read model;
- an application-level integration service;
- an anti-corruption layer when appropriate.

For the **Credits** bounded context, event-driven asynchronous communication is the default and direct synchronous invocation from other bounded contexts is forbidden.

The consuming bounded context must depend on a contract that it understands, not on the internal model of the provider.

Avoid synchronous cross-context dependencies when an asynchronous event provides a simpler and more decoupled solution.

---

# Shared code

`src/Shared/` is reserved for genuinely generic technical or cross-cutting concepts.

Examples:

- shared identifiers;
- generic pagination primitives;
- clock abstractions;
- common exception contracts;
- generic event infrastructure;
- generic database utilities;
- shared test utilities;
- framework-level abstractions independent from business concepts.

Do not place business logic in `Shared`.

Do not use `Shared` as a shortcut to avoid deciding which bounded context owns a concept.

If a class has business meaning for one bounded context, it belongs in that bounded context.

---

# Domain layer

The Domain layer contains the business model and business rules.

Typical contents:

```text
Domain/
    Entity/
    Aggregate/
    ValueObject/
    Repository/
    Service/
    Event/
    Exception/
    Enum/
```

Use only the folders that are actually needed.

## Entities and aggregates

- Entities contain business behavior, not only getters and setters.
- Protect invariants inside the model.
- Avoid anemic models where business rules live entirely in application services.
- Aggregate boundaries must be explicit.
- External code should modify an aggregate through its public behavior.
- Do not expose mutable internal collections unnecessarily.
- Persistence concerns must not define the shape of the domain model.

## Value Objects

Use Value Objects for values that:

- have business meaning;
- require validation;
- have specific equality semantics;
- should not be represented as arbitrary primitives throughout the codebase.

Examples may include identifiers, email addresses, visibility values, ratings, statuses or names with domain constraints.

Do not create a Value Object for every scalar mechanically. Use them where they provide semantic value or protect invariants.

Value Objects should normally be immutable.

## Domain services

Use a Domain Service when business behavior:

- belongs to the domain;
- does not naturally belong to a single entity or Value Object;
- coordinates domain concepts without requiring infrastructure.

Do not use Domain Services as generic containers for unrelated business logic.

## Domain events

Domain events represent something meaningful that has happened in the domain.

They should:

- use business terminology;
- contain only the information needed to describe the event;
- remain independent from Symfony Messenger or any transport mechanism.

Mapping a Domain Event to Messenger is an Infrastructure concern.

## Domain exceptions

Use specific domain exceptions for business-rule violations.

Avoid throwing generic `\RuntimeException` or `\Exception` for expected business failures.

---

# Application layer

The Application layer implements the application's use cases.

Typical contents:

```text
Application/
    Command/
    Query/
    Handler/
    DTO/
    Service/
    Port/
```

Use the project conventions consistently.

## Use cases

A use case should:

1. receive explicit input;
2. obtain the required domain objects through interfaces;
3. execute domain behavior;
4. persist changes through repository contracts;
5. trigger the required integration mechanisms;
6. return an application result or DTO when necessary.

Application code coordinates the workflow. It should not contain business rules that belong in Domain.

## Commands and queries

Prefer clear command/query objects when they improve readability.

Examples:

```text
CreateManuscript
RequestAccessToWork
ApproveReaderAccess
AddFeedback
GetManuscript
ListReaderRequests
```

Do not pass Symfony `Request` objects into the Application layer.

Convert HTTP input into application input in Infrastructure.

## Repository interfaces

Repository contracts belong in an inner layer, normally Domain when they represent access to aggregates.

Concrete Doctrine implementations belong in Infrastructure.

Example:

```text
Domain/
    Repository/
        ManuscriptRepository.php

Infrastructure/
    Persistence/
        Doctrine/
            DoctrineManuscriptRepository.php
```

Application services depend on the interface, never the Doctrine implementation.

## Ports

When Application requires an external capability, define an interface representing what the application needs.

Examples:

```text
Mailer
FileStorage
PasswordHasher
EventPublisher
Clock
NotificationSender
```

The implementation belongs in Infrastructure.

---

# Infrastructure layer

Infrastructure contains technical implementation details.

Typical contents:

```text
Infrastructure/
    Controller/
    Persistence/
    Doctrine/
    Http/
    Symfony/
    Messenger/
    Serializer/
    Security/
    Console/
    ExternalApi/
```

Use only the folders required by the concept.

Infrastructure may contain:

- Symfony controllers;
- Doctrine repositories;
- Doctrine mappings;
- request/response adapters;
- Symfony Messenger handlers and transports;
- serializers;
- HTTP clients;
- PostgreSQL-specific implementation details;
- integrations with third-party services;
- console commands;
- framework configuration adapters.

Infrastructure must not become the place where business logic is implemented.

---

# Symfony

Symfony is the application framework but must remain an Infrastructure concern wherever possible.

- Keep controllers thin.
- Controllers should parse the HTTP request, validate transport-level input, call the appropriate Application use case and map the result to an HTTP response.
- Controllers must not contain business logic.
- Do not inject Doctrine repositories directly into controllers when an Application use case should own the workflow.
- Prefer constructor dependency injection.
- Avoid using the Symfony service container as a service locator.
- Avoid static access to services.
- Keep framework attributes and configuration out of Domain classes.
- Symfony-specific events must not replace Domain Events.
- Convert framework exceptions into appropriate API responses in a centralized and consistent way.

---

# Doctrine and PostgreSQL

PostgreSQL is the project's relational database.

Doctrine may be used as the persistence implementation, but the Domain must remain persistence-agnostic.

## Persistence rules

- Domain classes must not depend directly on Doctrine.
- Avoid Doctrine attributes or annotations in Domain entities when they couple the model to persistence.
- Prefer mappings located in Infrastructure when the project configuration supports it.
- Doctrine repositories belong in Infrastructure.
- Repository interfaces belong in Domain or Application depending on their architectural responsibility.
- Do not expose `EntityManagerInterface`, `QueryBuilder`, Doctrine collections or persistence-specific objects outside Infrastructure.
- Do not write SQL in Application or Domain.
- PostgreSQL-specific queries belong in Infrastructure.
- Transactions should normally be managed at the application/infrastructure boundary.
- Avoid transaction management inside entities or Domain Services.

## Database design

- Use migrations for every schema change.
- Never modify the production schema manually as part of normal development.
- Add indexes deliberately based on access patterns.
- Use database constraints when they reinforce invariants that must also be protected at persistence level.
- Keep foreign keys consistent with aggregate and bounded-context boundaries.
- Do not create cross-bounded-context database access as a substitute for proper integration.

A foreign key existing in the database does not authorize one bounded context to query or manipulate another bounded context's model directly.

---

# HTTP API

The public API must follow a consistent REST-oriented style unless a specific use case requires otherwise.

## Controllers

Controllers belong in Infrastructure.

Naming should make their intent clear.

Examples:

```text
CreateManuscriptController
GetManuscriptController
ListManuscriptsController
UpdateManuscriptController
DeleteManuscriptController
```

A controller should be small enough that its complete behavior is obvious at a glance.

## Request handling

- Validate syntax and transport-level input at the HTTP boundary.
- Convert primitive request data into application commands or DTOs.
- Do not pass Symfony `Request` objects beyond Infrastructure.
- Do not allow API payload shape to define the internal Domain model.

## Responses

API responses should have predictable structures.

- Use appropriate HTTP status codes.
- Return validation errors consistently.
- Do not expose stack traces or internal infrastructure details.
- Do not expose database-only fields unless they are part of the public API contract.
- Serialize application results deliberately rather than exposing Doctrine entities directly.

---

# OpenAPI

The API contract must be documented using **OpenAPI**.

The project must maintain an OpenAPI specification representing the externally visible API.

Preferred location:

```text
openapi.yaml
```

If the specification becomes too large, it may be split:

```text
openapi/
    openapi.yaml
    paths/
    schemas/
```

Use the structure already established by the repository.

Every API change must update OpenAPI in the same change.

This includes:

- new endpoints;
- removed endpoints;
- path changes;
- request payload changes;
- response changes;
- validation changes;
- new error responses;
- authentication requirements;
- pagination changes;
- enum changes.

The implementation and the OpenAPI specification must not intentionally diverge.

OpenAPI is the public contract of the API.

---

# Documentation

Technical and architectural documentation belongs in:

```text
docs/
```

Documentation should be created or updated when a change introduces:

- a new bounded context;
- a significant new business concept;
- an architectural decision;
- a new integration;
- a non-trivial asynchronous flow;
- a new security or authorization model;
- a new persistence strategy;
- a new external service;
- a relevant operational requirement;
- a developer setup requirement.

Structure:

```text
docs/
    README.md            entry point and index
    conventions.md       documentation conventions (language, statuses, IDs)
    glossary.md          ubiquitous language, Spanish product terms to English identifiers
    product/             vision, actors, journeys, high-level domain model
    features/            master feature registry and one spec per feature, with status
    architecture/        contexts, layers, messaging, persistence, security, operations
    bounded-contexts/    one sheet per bounded context
    api/                 API conventions and endpoint semantics
    events/              integration event catalogue
    integrations/        external services
    decisions/           ADRs
    ui/                  screen specifications derived from Figma
    _templates/          templates for new documents
    _sources/            original source material
    _tools/              documentation validation scripts
```

`docs/` is the source of truth for the product (see ADR 0001). A feature is specified there
before it is implemented, including its API contract and its status.

Rules:

- documentation prose is written in Spanish; every technical identifier (bounded contexts,
  entities, events, endpoints, JSON fields, enums, tables) is written in English, exactly as
  it appears in the code;
- the glossary is normative: do not invent an alternative name for a concept it defines;
- a feature is never implemented while its `spec_status` is not `APPROVED`;
- `docs/api/` documents the semantics of each operation, `openapi/` defines its schemas.
  Do not duplicate information between them;
- run `python3 docs/_tools/check-docs.py` before considering a documentation change complete.

## Architecture Decision Records

For important architectural choices, prefer short ADR-style documents.

Example:

```text
docs/
    decisions/
        001-use-symfony-messenger-for-cross-context-events.md
```

An ADR should normally explain:

- context;
- decision;
- alternatives considered;
- consequences.

Documentation should explain **why** a decision exists, not duplicate code line by line.

---

# Cross-context communication

Direct dependencies between bounded contexts are forbidden.

When information must cross a boundary, prefer explicit integration mechanisms.

The default asynchronous communication mechanism is:

```text
Domain/Application event
        |
        v
Integration event
        |
        v
Symfony Messenger
        |
        v
RabbitMQ
        |
        v
Subscriber in another bounded context
```

**RabbitMQ is the queue/message broker used by the project.**

Do not couple Domain code to RabbitMQ or Symfony Messenger. Publishing, routing, serialization, retries and transport configuration belong to Infrastructure.

The Credits bounded context relies especially on this mechanism: it subscribes to any business event from other bounded contexts that may have an effect on credits and applies its own rules after receiving that event.

## Events

Use events when:

- the consuming context does not need an immediate synchronous response;
- the action represents something that has already happened;
- several contexts may react independently.

Example:

```text
Work
    ManuscriptPublished
            ↓
        Messenger
      ↙           ↘
Notification     Community
```

Each consumer interprets the event according to its own model.

## Queries between contexts

If a use case genuinely requires synchronous information from another bounded context:

- expose an explicit contract;
- return only the data required;
- avoid returning entities from the provider context;
- use DTOs or scalar data;
- consider a dedicated read model.

Do not share Doctrine repositories.

## Integration events

Do not serialize complete domain aggregates into integration events.

An integration event should contain only stable data required by consumers.

---

# Dependency control

Architectural boundaries must be enforceable, not merely documented.

Prefer automated dependency checks when practical.

The project should prevent rules such as:

```text
Domain -> Infrastructure
Domain -> Symfony
Application -> Infrastructure
BoundedContext A -> internal classes of BoundedContext B
```

Suitable tools may include:

- Deptrac;
- PHPStan architectural rules;
- custom static-analysis rules.

If dependency validation is configured in the repository, it must run as part of normal quality checks and CI.

Do not suppress an architectural violation merely to make CI pass. Fix the dependency direction.

---

# PHP

Use the PHP version defined by `composer.json`.

General rules:

- use `declare(strict_types=1);`;
- use explicit parameter and return types;
- use constructor property promotion when it improves clarity;
- prefer immutable DTOs and Value Objects where appropriate;
- prefer `readonly` when the object is conceptually immutable;
- avoid unnecessary inheritance;
- favor composition;
- avoid service classes with vague names such as `Helper`, `Manager` or `Utils` when a more precise responsibility exists;
- avoid global state;
- avoid static service access;
- avoid hidden side effects;
- do not use arrays as unstructured domain objects when a typed object communicates intent better.

---

# PHPStan

Static analysis is required.

Use a **reasonably strict PHPStan configuration**, with **level 7** as the expected baseline unless the repository currently defines another level.

Goals:

- no new PHPStan errors;
- avoid suppressions where a real type can be expressed;
- avoid broad `mixed` usage;
- type collections where practical;
- type DTOs, commands, query results and repository returns;
- avoid PHPDoc that contradicts native PHP types.

New code should aim for stronger typing even if legacy code is less strict.

Do not introduce a PHPStan baseline entry for a new problem that can reasonably be fixed.

A future increase from level 7 to level 8 should remain feasible without requiring architectural changes.

---

# PHP-CS-Fixer

Code style is enforced using **PHP-CS-Fixer**.

Use Symfony-compatible conventions as the baseline.

A reasonable configuration should include:

- Symfony coding style;
- ordered imports;
- removal of unused imports;
- consistent whitespace;
- trailing commas where appropriate;
- consistent multiline formatting;
- strict comparison conventions where useful;
- predictable class/member ordering when configured by the project.

Do not manually format code differently from the configured fixer.

Before considering a task complete, run PHP-CS-Fixer or the repository command that wraps it.

Do not disable rules locally unless there is a concrete technical reason.

---

# Tests

Tests are required for meaningful behavior.

## Unit tests

Use unit tests for:

- Value Objects;
- entities;
- aggregates;
- Domain Services;
- business rules;
- Application use cases that can be tested without infrastructure.

Domain tests should not require Symfony, Doctrine or PostgreSQL.

## Integration tests

Use integration tests for:

- Doctrine repositories;
- PostgreSQL queries;
- Symfony configuration;
- Messenger configuration;
- serialization;
- security adapters;
- external-service adapters where practical.

## Functional tests

Use functional tests for:

- HTTP endpoints;
- authentication;
- authorization;
- request validation;
- API response structures;
- important end-to-end backend flows.

When fixing a bug, add a regression test when practical.

Do not test trivial getters only to increase coverage. Test business behavior and contracts.

---

# Security and authorization

Lectores Beta handles unpublished literary content and private interactions between users.

Authorization is therefore critical.

- Never rely only on frontend behavior for permissions.
- Every protected operation must verify authorization in the backend.
- Do not expose manuscript content, comments, feedback or private metadata to unauthorized users.
- Do not log manuscript text, passwords, tokens or sensitive personal data.
- Authentication determines identity.
- Authorization determines whether that identity may perform an action.
- Keep authorization rules close to the relevant application/domain policy.
- Do not scatter duplicated authorization logic across controllers.

Use Symfony security mechanisms where appropriate, while keeping business-specific authorization rules independent from framework details whenever possible.

---

# Exceptions and error handling

Use specific exceptions for expected failures.

Examples:

```text
ManuscriptNotFound
AccessRequestAlreadyExists
ReaderAlreadyHasAccess
UnauthorizedManuscriptAccess
InvalidFeedbackRating
```

Avoid generic exceptions for business cases.

Exception handling should translate internal failures into stable API responses.

Do not expose:

- stack traces;
- SQL;
- internal class names;
- file paths;
- tokens;
- infrastructure details.

Unexpected errors should be logged through the configured logging system.

---

# Messaging and asynchronous processes

Use **Symfony Messenger with RabbitMQ** for asynchronous processing and cross-bounded-context communication.

RabbitMQ is the project's message broker.

Examples:

- notifications;
- cross-context reactions;
- credit-related event processing;
- email delivery;
- background processing;
- expensive derived operations.

Domain code must not depend on Symfony Messenger or RabbitMQ.

The mapping is:

```text
Domain Event
    ↓
Application / Integration boundary
    ↓
Integration Event
    ↓
Infrastructure / Symfony Messenger
    ↓
RabbitMQ
    ↓
Subscriber / Handler
```

## Integration events

Integration events are the public asynchronous contract between bounded contexts.

They must:

- describe a business fact that has already happened;
- be stable and explicit;
- contain only the information required by consumers;
- avoid exposing aggregates or Doctrine entities;
- avoid leaking internal implementation details;
- be versioned or evolved carefully when their contract changes.

Publish business facts, not instructions for another bounded context.

Prefer:

```text
FeedbackSubmitted
ReadingCompleted
ManuscriptPublished
```

over:

```text
AddTenCreditsToUser
UpdateCommunityStatistics
SendNotificationNow
```

The consumer owns the decision about what the event means inside its own bounded context.

## Credits subscriptions

The **Credits** bounded context must subscribe to every integration event that can affect the credits system.

The originating bounded context is responsible only for publishing the relevant fact.

Credits is responsible for:

1. receiving the event;
2. determining whether it has a credit effect;
3. applying the current credit rules;
4. updating its own aggregates and persistence;
5. publishing a Credits integration event if other contexts need to react.

This pattern must be used even when the immediate implementation appears simpler with a direct service call.

## Reliability

Handlers must be idempotent where duplicate delivery is possible.

Assume messages may be delivered more than once.

Credit-affecting consumers in particular must prevent the same integration event from applying the same credit effect more than once.

Use a stable event identifier or equivalent deduplication mechanism when necessary.

Configure retries and failure transports in Infrastructure.

Do not assume exactly-once delivery unless the infrastructure explicitly guarantees it.

If reliable publication of database changes and events requires atomicity, consider the **Outbox Pattern**.

---

# Transactions

A transaction should normally represent one application use case affecting one consistency boundary.

Avoid distributed transactions across bounded contexts.

If several bounded contexts need to react to one operation:

1. commit the originating bounded context;
2. publish an integration event;
3. allow other bounded contexts to react independently.

Consider the Outbox Pattern if reliable event publication becomes necessary.

Do not keep a database transaction open while performing slow external HTTP calls.

---

# Naming

Use business terminology consistently.

Prefer:

```text
RequestManuscriptAccess
ApproveReaderAccess
PublishManuscript
SubmitFeedback
```

instead of vague names such as:

```text
ProcessData
HandleItem
ManageRequest
DoAction
```

Classes should communicate responsibility without requiring their implementation to be read first.

---

# Configuration

- Never commit secrets.
- Store environment-specific values in environment variables or the project's configuration mechanism.
- Keep development, test and production configuration clearly separated.
- Document new required environment variables.
- Do not read environment variables directly from Domain or Application code.
- Infrastructure is responsible for translating configuration into typed dependencies.

---

# Migrations

All schema changes require Doctrine migrations.

A migration must:

- be committed with the code that requires it;
- be reversible when reasonably possible;
- avoid unnecessary destructive changes;
- preserve existing data unless the task explicitly requires otherwise.

Large or risky data migrations should be documented in `docs/`.

---

# Working on an existing feature

Before changing code:

1. Identify the bounded context.
2. Identify the business concept inside that bounded context.
3. Identify the correct DDD layer.
4. Inspect existing implementations of similar behavior.
5. Check whether the change crosses a bounded-context boundary.
6. Define an explicit integration mechanism if it does.
7. Implement the smallest coherent change.
8. Add or update tests.
9. Update OpenAPI when the API contract changes.
10. Update `docs/` when the change introduces architectural or operational knowledge.
11. Run static analysis.
12. Run PHP-CS-Fixer.
13. Run the relevant tests.
14. Review dependency direction before finishing.

---

# Definition of done

A backend task is not complete until, when applicable:

- the business behavior is implemented in the correct layer;
- DDD boundaries are respected;
- bounded contexts remain decoupled;
- the Credits bounded context remains fully isolated from direct dependencies on other bounded contexts;
- credit-affecting behavior is integrated through events and RabbitMQ rather than direct service calls;
- no forbidden dependency has been introduced;
- tests cover the relevant behavior;
- PHPStan passes;
- PHP-CS-Fixer passes;
- database migrations are included;
- OpenAPI reflects API changes;
- technical documentation under `docs/` is updated;
- no secrets or sensitive content are exposed;
- the complete diff contains no unrelated changes.

---

# Forbidden shortcuts

Do not solve a task by:

- putting business logic in a controller;
- accessing Doctrine directly from Domain or Application;
- injecting one bounded context's repository into another;
- calling Credits directly from another bounded context to add, subtract or modify credits;
- making another bounded context responsible for calculating credit effects;
- consuming another bounded context's internal event instead of an explicit integration event;
- reusing another bounded context's entity as a shared model;
- querying another bounded context's tables directly;
- passing Symfony `Request` objects into Application;
- returning Doctrine entities directly from API controllers;
- coupling Domain classes to Symfony attributes;
- suppressing PHPStan errors without justification;
- disabling PHP-CS-Fixer rules to avoid fixing formatting;
- changing the API without updating OpenAPI;
- introducing non-obvious architectural decisions without documenting them.

---

Follow these rules to keep Lectores Beta modular, explicit, testable and maintainable as the backend grows.
