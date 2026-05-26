<!--
SYNC IMPACT REPORT
Version change: 0.0.0 (template) → 1.0.0
Added sections:
  - Core Principles (I–V)
  - Technology Stack
  - Development Workflow
  - Governance
Modified principles: N/A (initial ratification)
Removed sections: N/A
Templates requiring updates:
  - .specify/templates/plan-template.md ⚠ pending manual review
  - .specify/templates/spec-template.md ⚠ pending manual review
  - .specify/templates/tasks-template.md ⚠ pending manual review
Follow-up TODOs: none
-->

# GastroVoice-AI Constitution

## Core Principles

### I. Domain-Driven Design (NON-NEGOTIABLE)
The entire codebase MUST be structured around the business domain.
- All business logic MUST live inside clearly bounded Domain layers: Entities, Value Objects,
  Domain Services, Domain Events, and Aggregates.
- Ubiquitous language MUST be used consistently in code, tests, and documentation.
- Bounded Contexts MUST be identified and respected; cross-context communication happens
  through well-defined contracts (events or Anti-Corruption Layers).
- Anemic domain models are FORBIDDEN; domain objects MUST encapsulate their own invariants.

### II. Hexagonal Architecture (Ports & Adapters) (NON-NEGOTIABLE)
The application MUST be built following the Hexagonal Architecture pattern.
- The Domain layer MUST have zero dependencies on infrastructure or framework code.
- All external interactions (HTTP, database, messaging, CLI) MUST go through Ports (interfaces)
  defined in the Domain or Application layer and implemented as Adapters.
- The Application layer orchestrates use cases via Application Services / Command & Query handlers
  (CQRS is encouraged).
- Symfony acts as an adapter; it MUST NOT leak into Domain or Application layers.

### III. Test Coverage (NON-NEGOTIABLE)
Every feature and business rule MUST be covered by automated tests before merging.
- **Unit tests** MUST cover all Domain Entities, Value Objects, Domain Services, and
  Application Services in isolation (no I/O).
- **Functional/integration tests** MUST cover Adapters, API endpoints, and persistence logic.
- TDD is the preferred workflow: write failing tests first, then implement.
- Minimum coverage gate: 80 % for Domain and Application layers.
- Backend tests use **PHPUnit**; frontend tests use **Vitest** + **React Testing Library**.
- End-to-end tests (Playwright or Cypress) are required for critical user journeys.

### IV. Technology Stack (FIXED)
The technology choices below are fixed and MUST NOT be substituted without a formal
constitution amendment.
- **Backend**: PHP 8.3+ with Symfony 7.x (latest stable).
  - Doctrine ORM for persistence adapters.
  - Messenger component for async domain events / CQRS buses.
- **Frontend**: React 18+ (TypeScript mandatory).
  - Vite as the build tool.
  - Axios or Fetch API for HTTP; no direct backend coupling in UI components.
- **API contract**: REST + JSON (OpenAPI 3.x spec required) or GraphQL if agreed per feature.
- **Database**: PostgreSQL (primary); Redis for cache/queue adapters.

### V. Simplicity & Continuous Quality
- YAGNI: no code is added until there is a concrete requirement.
- Each Pull Request MUST pass: linting (PHP CS Fixer / ESLint), static analysis
  (PHPStan level ≥ 8 / TypeScript strict), and all test suites.
- Dependencies MUST be justified; no library is added without team consensus.
- Infrastructure-as-Code changes follow the same review process as application code.

## Technology Stack Details

- **Backend directory**: `backend/` — Symfony application following DDD + Hexagonal layout:
  `src/{BoundedContext}/Domain/`, `src/{BoundedContext}/Application/`,
  `src/{BoundedContext}/Infrastructure/`.
- **Frontend directory**: `frontend/` — React + TypeScript application.
- **Shared contracts**: `contracts/` — OpenAPI specs and shared event schemas.
- Docker Compose MUST be provided for local development, covering all services.

## Development Environment

- **All terminal commands MUST be executed inside WSL2 (Ubuntu 20.04)**, never from Windows
  PowerShell or CMD. The workspace path is `/var/www/GastroVoice-AI`.
- When running Docker, PHP, npm, git, or any CLI tool, always use the WSL terminal directly.
- File ownership inside WSL MUST belong to the developer user (`nhaddouche`), not `root`.

## Development Workflow

- Feature branches created from `main`; branch name convention: `feat/<ticket>-<slug>`.
- Every PR requires at least one reviewer approval and green CI (tests + analysis + lint).
- Domain Events and API contract changes MUST be documented and versioned.
- Database migrations MUST be reviewed for reversibility.
- Secrets MUST NOT be committed; use environment variables and a `.env.example` file.

## Governance

This constitution supersedes all informal practices and ad-hoc decisions.
Amendments require:
1. A written proposal describing the change and rationale.
2. Approval from at least one other team member.
3. A migration plan for any existing code affected.
4. Version bump following semantic versioning rules.

All PRs and code reviews MUST verify compliance with the Core Principles above.
Any deviation MUST be explicitly documented as technical debt with a resolution timeline.

**Version**: 1.0.0 | **Ratified**: 2026-05-25 | **Last Amended**: 2026-05-25
