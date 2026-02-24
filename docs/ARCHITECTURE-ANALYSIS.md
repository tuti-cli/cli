# Tuti CLI Architecture Analysis

> Generated: 2026-02-19

## Current Architecture: Clean + Layered

The project follows Clean Architecture principles:

```
┌─────────────────────────────────────────────────────────┐
│  Commands/ (Presentation)                                │
│  ┌─────────────────────────────────────────────────┐    │
│  │  Services/ (Application Layer)                   │    │
│  │  ┌─────────────────────────────────────────┐    │    │
│  │  │  Domain/ (Entities & Business Rules)    │    │    │
│  │  └─────────────────────────────────────────┘    │    │
│  └─────────────────────────────────────────────────┘    │
│  Infrastructure/ (implements interfaces)                 │
│  Contracts/ (interfaces for dependency inversion)        │
└─────────────────────────────────────────────────────────┘
```

## Layer Mapping

| Layer | Directory | Purpose |
|-------|-----------|---------|
| **Domain** | `app/Domain/` | `Project.php`, `ProjectConfigurationVO.php`, `ProjectStateEnum` |
| **Application** | `app/Services/` | Business logic services |
| **Infrastructure** | `app/Infrastructure/` | `DockerComposeOrchestrator` |
| **Contracts** | `app/Contracts/` | Interfaces (`StackInstallerInterface`, `OrchestratorInterface`) |
| **Presentation** | `app/Commands/` | CLI commands using `HasBrandedOutput` |

## Strengths

- **Dependency Inversion** — Commands depend on interfaces (`OrchestratorInterface`), not implementations
- **Final classes** — Composition over inheritance
- **Readonly services** — Immutable service objects
- **Domain isolation** — `Project` entity with value objects

## Potential Future Structure

```
Current:                              Suggested:
app/Services/Stack/                   app/Modules/Stack/
├── Installers/                       ├── Domain/
├── StackInitService.php              ├── Application/
└── ...                               └── Infrastructure/

                                      app/Modules/Project/
                                      ├── Domain/
                                      │   ├── Project.php
                                      │   └── ValueObjects/
                                      └── Application/
```

## Recommendation: Modular Monolith

The CLI tool is well-suited to a **Modular Monolith** approach:

| Factor | Case | Recommendation |
|--------|------|----------------|
| Team size | Small | Monolith |
| Domain complexity | Medium (stacks, projects, infra) | Modular |
| Scale requirements | Single binary | Monolith |
| Deploy independence | N/A (CLI tool) | Single deploy |

## Module Boundaries (Future)

```
app/
├── Commands/          # CLI entry points
├── Modules/           # Future: explicit module boundaries
│   ├── Stack/         # Stack installation domain
│   │   ├── Domain/
│   │   ├── Application/
│   │   └── Infrastructure/
│   ├── Project/       # Project lifecycle domain
│   └── Infrastructure/# Docker/infra domain
├── Contracts/         # Shared interfaces
└── Support/           # Cross-cutting concerns
```

---

## Want to explore specific patterns?

- `/architecture clean` — Deep dive into Clean Architecture
- `/architecture ddd` — Domain-Driven Design patterns
- `/architecture layers` — Layered architecture details

Or I can help you refactor a specific area of the codebase to better follow these patterns.
