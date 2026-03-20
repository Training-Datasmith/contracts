# Architecture: contracts

## Purpose

The Symfony Contracts library: a set of PHP interfaces and traits that define stable, version-safe contracts for common Symfony subsystems (cache, event dispatcher, HTTP client, service locator, translation, deprecation). Third-party packages implement or consume these interfaces without depending on concrete Symfony components.

## Directory Structure

```
Cache/
  CacheInterface.php              — PSR-6-compatible cache pool with callback-based get (stampede prevention)
  CacheTrait.php                  — Default implementation of CacheInterface::get() with beta/early expiry
  CallbackInterface.php           — Callable that receives a CacheItem and returns the computed value
  ItemInterface.php               — Extends PSR-6 CacheItemInterface with tag support and metadata
  NamespacedPoolInterface.php     — Cache pool with namespace isolation
  TagAwareCacheInterface.php      — Cache pool that supports tag-based invalidation

Deprecation/
  function.php                    — trigger_deprecation() helper: wraps trigger_error with @-silenceable semantics

EventDispatcher/
  Event.php                       — Base event class with stopPropagation / isPropagationStopped
  EventDispatcherInterface.php    — dispatch(Event, name): minimal dispatcher contract

HttpClient/
  HttpClientInterface.php         — request(method, url, options): async HTTP client contract
  ResponseInterface.php           — Lazy response: status, headers, content (blocks on access)
  ChunkInterface.php              — Streaming chunk from a response body
  ResponseStreamInterface.php     — Iterable stream of ChunkInterface objects
  Exception/                      — Typed HTTP exceptions (client 4xx, server 5xx, timeout, transport)
  Test/
    HttpClientTestCase.php        — Abstract PHPUnit test suite for HttpClient implementations

Service/
  ContainerAwareInterface.php     — Setter injection: setContainer(ContainerInterface)
  ResetInterface.php              — reset(): clear internal state between requests (for long-running processes)
  ServiceSubscriberInterface.php  — getSubscribedServices(): declare which services a class needs
  ServiceSubscriberTrait.php      — Default implementation of ServiceSubscriberInterface via __call
  ServiceLocatorTrait.php         — PSR-11 locator backed by a keyed service map
  ServiceProviderInterface.php    — Extends PSR-11: provides() lists available service ids
  ServiceCollectionInterface.php  — Iterable service collection with count()
  Attribute/Required.php          — #[Required] attribute: marks setter for mandatory DI injection
  Attribute/SubscribedService.php — #[SubscribedService] attribute: declares a subscribed service inline

Translation/
  TranslatorInterface.php         — trans(id, parameters, domain, locale): translator contract
  TranslatorTrait.php             — Default trans() implementation (no-op / passthrough)
  LocaleAwareInterface.php        — getLocale() / setLocale() contract
  TranslatableInterface.php       — trans(TranslatorInterface): object that knows how to translate itself
```

## Key Design Decisions

- **Interface-only contracts** — the package ships primarily interfaces and traits; no concrete service implementations. This allows framework components and third-party packages to depend on the same stable API surface.
- **Semantic versioning stability** — interfaces in this package follow stricter backward-compatibility guarantees than the rest of Symfony; they change only on major versions.
- **Cache stampede prevention** — `CacheTrait::get()` uses a probabilistic early-expiry algorithm (beta parameter) so multiple concurrent requests do not all miss the cache and recompute simultaneously.
- **Lazy HTTP responses** — `ResponseInterface` defers I/O; status/headers/content are only read when accessed, enabling concurrent request multiplexing in implementations.
- **`trigger_deprecation()`** — standardises deprecation notices with package, version, and message metadata; silenceable with `@` for tests.

## Extension Points

- Implement `HttpClientInterface` to provide a custom HTTP transport.
- Implement `CacheInterface` + `TagAwareCacheInterface` to integrate a custom cache backend.
- Implement `EventDispatcherInterface` to build a custom event system.
- Consume `ServiceSubscriberInterface` to declare lazy service dependencies without tight coupling to a DI container.

## Dependency Flow

```
Consumer code
  └── uses CacheInterface / HttpClientInterface / EventDispatcherInterface / TranslatorInterface
        └── Symfony component (symfony/cache, symfony/http-client, etc.) provides concrete implementation
              └── Contracts interfaces remain stable across minor Symfony versions
```
