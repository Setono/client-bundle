# Metadata loads lazily in setono/client

In 2.0, `setono/client`'s `Metadata::lazy()` takes a loader that runs on the metadata's first use, and the bundle's metadata store returns such metadata from `load()`. `hasChanged()` never loads: metadata that was never used hasn't changed, so the store and any decorator of it can ask without querying the database. Before, the bundle's internal `LazyMetadata` was a lazy ghost made with Symfony's `LazyGhostTrait`. Symfony deprecated that trait in 7.3 and removed it in 8.0. Also, only the store could tell whether such metadata was used, because only it knew the internal class.

## Considered Options

- **PHP 8.4's native lazy objects, with `LazyGhostTrait` on PHP 8.1–8.3**: rejected. It needs two code paths behind a PHP version check. Infection runs on one PHP version, so the other path's mutants would escape the 100% MSI gate. A decorator still couldn't tell whether metadata was used without reflection.
- **Require PHP 8.4 and use only native lazy objects**: rejected. It's the simplest code, but the bundle's 2.0 would exclude shops on PHP 8.2 and 8.3.

As in ADR-0002, Setono owns `setono/client`. One minor release puts lazy loading next to every method of `Metadata`, covered by the library's own tests, on any PHP version the library supports.
