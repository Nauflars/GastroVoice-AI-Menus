# Implementation Plan: GastroVoice AI — Integral Restaurant Management System

**Branch**: `feat/001-gastrovoice-ai-system` | **Date**: 2026-05-25 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-gastrovoice-ai-system/spec.md`

---

## Summary

GastroVoice AI is a full-stack restaurant management platform with three integrated layers:
(1) a Symfony 7 / PHP 8.3 backend following DDD + Hexagonal Architecture across six Bounded
Contexts; (2) a React 18 / TypeScript admin panel; and (3) an AI-powered voice assistant
integrated with Asterisk for automated phone handling. All AI capabilities (menu extraction,
intent detection, STT, TTS) are provided by OpenAI and abstracted behind Port interfaces,
making them swappable without touching Domain or Application layers.

---

## Technical Context

**Language/Version**: PHP 8.3 (backend) · TypeScript 5.x / Node 20 (frontend)

**Primary Dependencies**:
- Backend: Symfony 7.x, Doctrine ORM 3.x, LexikJWTAuthenticationBundle, Symfony Messenger,
  openai-php/client, PHPUnit 11, PHPStan 2, PHP CS Fixer
- Frontend: React 18, Vite 5, Zustand, TanStack Query v5, React Router v6,
  shadcn/ui + Tailwind CSS, Vitest, React Testing Library, Playwright

**Storage**: PostgreSQL 16 (primary); Redis 7 (cache, call sessions, Messenger transport)

**Testing**:
- Backend: PHPUnit 11 (Unit + Functional suites); PHPStan level 8; PHP CS Fixer
- Frontend: Vitest + React Testing Library; Playwright (E2E)
- Coverage gate: 80% for Domain + Application layers

**Target Platform**: Linux server (Docker); local dev on Docker Compose

**Project Type**: Web application (REST API + SPA admin panel) with telephony integration

**Performance Goals**:
- Reservation availability check < 500 ms p95
- Menu AI extraction preview < 30 s
- Voice intent detection < 2 s per turn

**Constraints**:
- Domain layer: zero Symfony/Doctrine dependencies (pure PHP)
- Application layer: depends only on Domain interfaces (Ports), no I/O
- All secrets via environment variables; nothing committed to VCS
- Single restaurant per deployment (v1)

**Scale/Scope**:
- 1 restaurant, ~100 concurrent admin users, voice assistant handles sequential calls
- Modular monolith; horizontal scaling deferred to v2

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Evidence |
|-----------|--------|---------|
| I. DDD | ✅ PASS | Six Bounded Contexts; Entities/VOs/Domain Services per context; no anemic models |
| II. Hexagonal Architecture | ✅ PASS | Domain ← Application ← Infrastructure; Symfony/Doctrine only in Infrastructure |
| III. Test Coverage | ✅ PASS | PHPUnit Unit+Functional; Vitest+RTL; Playwright E2E; 80% gate |
| IV. Technology Stack | ✅ PASS | PHP 8.3 + Symfony 7; React 18 + TypeScript; PostgreSQL + Redis; OpenAPI contract |
| V. Simplicity | ✅ PASS | Modular monolith; YAGNI applied; PHPStan 8; ESLint strict |

**Post-Design Re-check** (Phase 1 completed):

| Area | Decision | Compliant? |
|------|----------|------------|
| Cross-context references | UUID references, no DB foreign keys across contexts | ✅ |
| Asterisk integration | AGI → HTTP → Infrastructure Adapter (Port pattern) | ✅ |
| Redis session storage | Infrastructure concern; Port declared in Application layer | ✅ |
| OpenAI adapters | Outbound Ports in Application layer; Adapters in Infrastructure | ✅ |

**No violations to track.**

---

## Project Structure

### Documentation (this feature)

```text
specs/001-gastrovoice-ai-system/
├── plan.md              ← this file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
├── contracts/
│   └── api.openapi.yaml ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)

```text
backend/                              # Symfony 7 application
├── src/
│   ├── RestaurantManagement/
│   │   ├── Domain/
│   │   │   ├── Entity/               # Restaurant, OpeningHour
│   │   │   ├── ValueObject/          # SeatCapacity, SlotDuration
│   │   │   └── Repository/           # RestaurantRepositoryInterface (Port)
│   │   ├── Application/
│   │   │   ├── Command/              # UpdateRestaurantCommand
│   │   │   ├── Handler/              # UpdateRestaurantHandler
│   │   │   └── Query/                # GetRestaurantQuery
│   │   └── Infrastructure/
│   │       ├── Persistence/          # DoctrineRestaurantRepository
│   │       └── Http/                 # RestaurantController
│   ├── Menu/
│   │   ├── Domain/
│   │   │   ├── Entity/               # MenuCategory, MenuItem
│   │   │   ├── ValueObject/          # Price, CategoryName
│   │   │   ├── Repository/           # MenuCategoryRepositoryInterface
│   │   │   ├── Service/              # MenuImportService
│   │   │   └── Event/                # MenuItemCreated, MenuImportCompleted
│   │   ├── Application/
│   │   │   ├── Command/              # CreateMenuItemCommand, ImportMenuCommand
│   │   │   ├── Handler/              # CreateMenuItemHandler, ImportMenuHandler
│   │   │   ├── Query/                # GetActiveMenuQuery
│   │   │   └── Port/                 # AIMenuExtractorPort
│   │   └── Infrastructure/
│   │       ├── Persistence/          # DoctrineMenuCategoryRepository
│   │       ├── AI/                   # OpenAIMenuExtractorAdapter
│   │       └── Http/                 # MenuController
│   ├── Ordering/
│   │   ├── Domain/
│   │   │   ├── Entity/               # Order, OrderLine
│   │   │   ├── ValueObject/          # OrderStatus, Money
│   │   │   ├── Repository/           # OrderRepositoryInterface
│   │   │   └── Event/                # OrderPlaced, OrderStatusChanged
│   │   ├── Application/
│   │   │   ├── Command/              # PlaceOrderCommand, UpdateOrderStatusCommand
│   │   │   ├── Handler/
│   │   │   └── Query/                # GetOrdersQuery
│   │   └── Infrastructure/
│   │       ├── Persistence/
│   │       └── Http/                 # OrderController
│   ├── Reservations/
│   │   ├── Domain/
│   │   │   ├── Entity/               # Reservation
│   │   │   ├── ValueObject/          # TimeSlot, ReservationStatus
│   │   │   ├── Repository/           # ReservationRepositoryInterface
│   │   │   ├── Service/              # ReservationAvailabilityChecker
│   │   │   └── Event/                # ReservationCreated, ReservationCancelled
│   │   ├── Application/
│   │   │   ├── Command/              # CreateReservationCommand, CancelReservationCommand
│   │   │   ├── Handler/
│   │   │   └── Query/                # CheckAvailabilityQuery, GetReservationsQuery
│   │   └── Infrastructure/
│   │       ├── Persistence/          # Doctrine + pessimistic write lock
│   │       └── Http/                 # ReservationController
│   ├── VoiceAssistant/
│   │   ├── Domain/
│   │   │   ├── Entity/               # CallSession
│   │   │   ├── ValueObject/          # Intent, ConversationTurn
│   │   │   ├── Repository/           # CallSessionRepositoryInterface
│   │   │   └── Service/              # ConversationOrchestrator
│   │   ├── Application/
│   │   │   ├── Command/              # ProcessVoiceTurnCommand
│   │   │   ├── Handler/              # ProcessVoiceTurnHandler
│   │   │   └── Port/                 # IntentDetectorPort, SpeechToTextPort, TextToSpeechPort
│   │   └── Infrastructure/
│   │       ├── Persistence/          # RedisCallSessionRepository
│   │       ├── AI/                   # OpenAIIntentDetectorAdapter, OpenAIWhisperAdapter, OpenAITTSAdapter
│   │       └── Http/                 # VoiceController (AGI + simulation endpoints)
│   └── Identity/
│       ├── Domain/
│       │   ├── Entity/               # AdminUser
│       │   └── Repository/           # AdminUserRepositoryInterface
│       ├── Application/
│       │   └── Command/              # CreateAdminUserCommand
│       └── Infrastructure/
│           ├── Persistence/          # DoctrineAdminUserRepository
│           └── Security/             # Symfony Security integration
├── tests/
│   ├── Unit/                         # Domain + Application (no I/O)
│   │   ├── Menu/
│   │   ├── Ordering/
│   │   ├── Reservations/
│   │   └── VoiceAssistant/
│   └── Functional/                   # HTTP + DB (WebTestCase)
│       ├── Menu/
│       ├── Ordering/
│       ├── Reservations/
│       └── VoiceAssistant/
├── config/
├── migrations/
└── docker/                           # Dockerfile, nginx.conf, asterisk/

frontend/                             # React 18 + TypeScript + Vite
├── src/
│   ├── features/
│   │   ├── menu/                     # Categories, items, image import
│   │   ├── orders/                   # Order list, status management
│   │   ├── reservations/             # Reservation calendar + form
│   │   ├── restaurant/               # Config, opening hours
│   │   └── voice/                    # HTTP simulation playground
│   ├── shared/
│   │   ├── components/               # Button, Input, Modal, DataTable…
│   │   ├── hooks/                    # useAuth, useApiClient
│   │   └── api/                      # Axios client, typed API functions
│   ├── pages/                        # Route-level components
│   └── app/                          # Router, providers, theme
├── tests/
│   ├── unit/
│   └── e2e/                          # Playwright
└── public/

contracts/                            # OpenAPI specs (source of truth)
│   └── api.openapi.yaml              # Already in specs/; symlinked or copied to here at build time

docker-compose.yml
.env.example
```

**Structure Decision**: Web application layout (backend + frontend). DDD bounded contexts
reflected in both backend directory structure and frontend feature slices.

---

## Complexity Tracking

> No constitution violations. No entries required.

| Area | Justified Complexity | Reason |
|------|---------------------|--------|
| 6 Bounded Contexts | Required by DDD | Each context has distinct business rules and lifecycle |
| Redis + Postgres dual storage | Required for call sessions | Sub-100ms session reads needed during live calls; Redis TTL handles cleanup |
| Asterisk Docker service | Required by spec | Voice assistant cannot be tested without a telephony layer |
