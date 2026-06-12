# Architecture

## Application Layers

- `app/Filament`: Admin panel resources, pages, widgets, and clusters.
- `app/Actions`: Single-purpose application operations.
- `app/Services`: Business workflows and orchestration.
- `app/Repositories`: Persistence/query abstractions when a query becomes reusable.
- `app/DTOs`: Immutable request/response data carriers.
- `app/Enums`: Domain constants with type safety.
- `app/Policies`: Authorization rules.
- `app/Helpers` and `app/Traits`: Shared utilities, kept small and explicit.

## Admin Panel

Filament v4 is registered at `/admin` with Filament authentication, password reset, profile editing, dark mode, a collapsible sidebar, Curator media management, and Shield role management.

## Persistence

MySQL is the primary database. Sessions, cache, jobs, failed jobs, permissions, activity logs, media, and Telescope entries are all database-backed through migrations.

## Standards

New PHP files should declare strict types, use constructor property promotion where dependencies are injected, and keep controllers/Filament resources thin by delegating workflows to actions and services.
