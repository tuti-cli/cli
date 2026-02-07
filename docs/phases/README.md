# Tuti CLI - Project Phases

**Last Updated:** 2026-02-07

This directory contains the development roadmap broken down into phases. Each phase has its own file with detailed scope, deliverables, and success criteria.

## Phase Overview

| Phase | Name | Focus | Timeline | Status |
|-------|------|-------|----------|--------|
| [Phase 1](phase-1-mvp-local-development.md) | MVP: Local Development | Polish existing features, tests, CI | 4-6 weeks | **In Progress** |
| [Phase 2](phase-2-deployment-foundations.md) | Deployment Foundations | SSH deployment, FTP upload, rollbacks | 4-6 weeks | Not Started |
| [Phase 3](phase-3-multi-project-and-dx.md) | Multi-Project & DX | Project registry commands, UX improvements | 3-4 weeks | Not Started |
| [Phase 4](phase-4-ecosystem-expansion.md) | Ecosystem Expansion | New stacks, CI/CD templates, database ops | 6-8 weeks | Not Started |
| [Phase 5](phase-5-scale-and-community.md) | Scale & Community | Cloud providers, monitoring, plugin system | Ongoing | Not Started |

## Guiding Principles

1. **Ship early, iterate fast** - Each phase delivers usable value
2. **Local-first** - Local development must be rock-solid before deployment
3. **Laravel ecosystem first** - Primary stack gets the most polish
4. **Test everything** - No phase ships without adequate test coverage
5. **Single binary** - Every feature must work when compiled to PHAR/binary

## Release Strategy

- **Patch releases (0.x.y):** Bug fixes, within any phase
- **Minor releases (0.x.0):** Phase completion milestones
- **v1.0.0:** After Phase 2 completion (local dev + basic deployment working)
- **v2.0.0:** After Phase 4 completion (full ecosystem)

## Dependencies Between Phases

```
Phase 1 (MVP: Local Dev)
  |
  v
Phase 2 (Deployment) -----> Phase 4 (Ecosystem: new stacks, CI/CD)
  |                                    |
  v                                    v
Phase 3 (Multi-Project & DX)   Phase 5 (Scale & Community)
```

Phase 1 must complete before Phase 2 or 3 can start. Phase 4 and 5 can partially overlap with Phase 3.
