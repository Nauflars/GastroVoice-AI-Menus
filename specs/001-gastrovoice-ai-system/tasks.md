# Tasks: GastroVoice AI — Integral Restaurant Management System

**Input**: Design documents from `specs/001-gastrovoice-ai-system/`

**Prerequisites**: plan.md ✅ · spec.md ✅ · research.md ✅ · data-model.md ✅ · contracts/api.openapi.yaml ✅ · quickstart.md ✅

**Tests**: Included — required by Constitution Principle III (NON-NEGOTIABLE).
Unit tests cover Domain + Application layers; Functional tests cover HTTP + DB adapters.

**Organization**: Tasks are grouped by user story to enable independent implementation
and testing of each story. Each phase delivers a working, testable increment.

## Format: `- [ ] [ID] [P?] [Story?] Description — file/path`

- **[P]**: Parallelizable (different files, no dependency on incomplete tasks in same phase)
- **[US#]**: User story label (phases 3–8 only)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project scaffolding, Docker environment, tooling configuration — prerequisite
for everything else.

- [x] T001 Scaffold Symfony 7 project in `backend/` with `composer create-project symfony/skeleton`
- [x] T002 [P] Scaffold React 18 + TypeScript + Vite project in `frontend/` with `npm create vite@latest`
- [x] T003 Install backend dependencies: Doctrine ORM, LexikJWTAuthenticationBundle, Symfony Messenger, openai-php/client, PHPUnit, PHPStan, PHP CS Fixer — `backend/composer.json`
- [x] T004 [P] Install frontend dependencies: Zustand, TanStack Query v5, React Router v6, Axios, shadcn/ui, Tailwind CSS, Vitest, React Testing Library, Playwright — `frontend/package.json`
- [x] T005 Create `docker/backend/Dockerfile` (php:8.3-fpm + Nginx), `docker/backend/nginx.conf`
- [x] T006 [P] Create `docker/asterisk/Dockerfile` and base Asterisk 20 configuration files in `docker/asterisk/`
- [x] T007 Create `docker-compose.yml` with services: backend (php-fpm + nginx), db (postgres:16), redis (redis:7), asterisk, frontend (node:20 dev), mailpit
- [x] T008 Create `.env.example` with all required variables: `OPENAI_API_KEY`, `POSTGRES_*`, `JWT_SECRET`, `REDIS_*`, `VITE_API_URL`
- [x] T009 Configure PHPUnit in `backend/phpunit.xml` with two test suites: `Unit` (`tests/Unit/`) and `Functional` (`tests/Functional/`)
- [x] T010 [P] Configure PHPStan in `backend/phpstan.neon` at level 8
- [x] T011 [P] Configure PHP CS Fixer in `backend/.php-cs-fixer.php` (PSR-12 + Symfony ruleset)
- [x] T012 [P] Configure Vitest in `frontend/vitest.config.ts` with jsdom environment and RTL setup file
- [x] T013 [P] Configure Playwright in `frontend/playwright.config.ts` targeting `http://localhost:5173`
- [x] T014 [P] Configure ESLint + TypeScript strict in `frontend/eslint.config.js`
- [x] T015 Create `backend/src/` directory structure for all 6 Bounded Contexts per plan.md: `RestaurantManagement/`, `Menu/`, `Ordering/`, `Reservations/`, `VoiceAssistant/`, `Identity/` — each with `Domain/`, `Application/`, `Infrastructure/` subdirectories

**Checkpoint**: `docker compose up -d` starts all services; `docker compose exec backend php bin/phpunit` and `docker compose exec frontend npm test` run without errors (empty suites).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Identity/auth context, shared kernel, Doctrine setup, Messenger bus configuration.
No user story work can begin until this phase is complete.

- [x] T016 Create Doctrine DBAL + ORM configuration in `backend/config/packages/doctrine.yaml` with PostgreSQL connection and UUID primary key strategy
- [x] T017 Create `backend/src/Identity/Domain/Entity/AdminUser.php` — UUID id, email, passwordHash, restaurantId, roles, createdAt
- [x] T018 Create `backend/src/Identity/Domain/Repository/AdminUserRepositoryInterface.php` — Port: `findByEmail()`, `save()`
- [x] T019 Create Doctrine mapping `backend/src/Identity/Infrastructure/Persistence/Doctrine/AdminUser.orm.xml` and `backend/src/Identity/Infrastructure/Persistence/DoctrineAdminUserRepository.php`
- [x] T020 Create Doctrine migration for `admin_users` table in `backend/migrations/`
- [x] T021 Configure Symfony Security in `backend/config/packages/security.yaml` — AdminUser provider, bcrypt password hasher, JWT firewall for `/api/` routes
- [x] T022 Configure LexikJWTAuthenticationBundle in `backend/config/packages/lexik_jwt_authentication.yaml` — access token 3600s, refresh token 604800s
- [x] T023 Create `backend/src/Identity/Infrastructure/Http/AuthController.php` — `POST /api/auth/login`, `POST /api/auth/refresh`
- [x] T024 Create `backend/src/Identity/Application/Command/CreateAdminUserCommand.php` + `CreateAdminUserHandler.php`
- [x] T025 Create Symfony console command `backend/src/Identity/Infrastructure/Console/CreateAdminUserCommand.php` (`app:create-admin`)
- [x] T026 Configure Symfony Messenger in `backend/config/packages/messenger.yaml` — sync transport for domain events, Redis async transport for background tasks
- [x] T027 Create `backend/src/Shared/Domain/Bus/CommandBusInterface.php` and `QueryBusInterface.php` (Ports) + Messenger adapters in `backend/src/Shared/Infrastructure/Bus/`
- [x] T028 Create `backend/config/routes.yaml` base routing configuration; create `backend/config/packages/cors.yaml` (NelmioCorsBundle) allowing `http://localhost:5173`
- [x] T029 Write unit tests for AdminUser entity in `backend/tests/Unit/Identity/Domain/Entity/AdminUserTest.php`
- [x] T030 Write functional test for auth endpoints in `backend/tests/Functional/Identity/AuthControllerTest.php` — login success, login failure, protected route 401

**Checkpoint**: `POST /api/auth/login` returns JWT; protected endpoints return 401 without token; `php bin/phpunit` green.

---

## Phase 3: User Story 1 — Restaurant Administrator Sets Up the System (Priority: P1) 🎯 MVP

**Goal**: Admin can log in, configure restaurant details, opening hours, seating capacity,
and see the configuration persisted. Complete, independently testable system foundation.

**Independent Test**: Log in → update restaurant config → GET /api/restaurant returns updated values. All 2 test classes pass in isolation.

### Tests — User Story 1

- [x] T031 [P] [US1] Write unit tests for `Restaurant` entity and `OpeningHour` entity invariants in `backend/tests/Unit/RestaurantManagement/Domain/Entity/RestaurantTest.php`
- [x] T032 [P] [US1] Write unit tests for `SeatCapacity` and `SlotDuration` Value Objects in `backend/tests/Unit/RestaurantManagement/Domain/ValueObject/`
- [x] T033 [P] [US1] Write unit tests for `UpdateRestaurantHandler` with mocked repository in `backend/tests/Unit/RestaurantManagement/Application/Handler/UpdateRestaurantHandlerTest.php`
- [x] T034 [US1] Write functional tests for `GET /api/restaurant` and `PUT /api/restaurant` in `backend/tests/Functional/RestaurantManagement/RestaurantControllerTest.php`

### Implementation — User Story 1

- [x] T035 [P] [US1] Create `backend/src/RestaurantManagement/Domain/ValueObject/SeatCapacity.php` — wraps int, enforces ≥ 1
- [x] T036 [P] [US1] Create `backend/src/RestaurantManagement/Domain/ValueObject/SlotDuration.php` — wraps int (minutes), enforces ≥ 15
- [x] T037 [US1] Create `backend/src/RestaurantManagement/Domain/Entity/Restaurant.php` — UUID id, name, address, phone, SeatCapacity, SlotDuration, timezone, createdAt, updatedAt; with `update()` method enforcing invariants
- [x] T038 [US1] Create `backend/src/RestaurantManagement/Domain/Entity/OpeningHour.php` — dayOfWeek, openTime, closeTime, isClosed; belongs to Restaurant
- [x] T039 [US1] Create `backend/src/RestaurantManagement/Domain/Repository/RestaurantRepositoryInterface.php` — `findById()`, `findByAdminUser()`, `save()`
- [x] T040 [US1] Create Doctrine mappings and `backend/src/RestaurantManagement/Infrastructure/Persistence/DoctrineRestaurantRepository.php`
- [x] T041 [US1] Create Doctrine migration for `restaurants` and `opening_hours` tables in `backend/migrations/`
- [x] T042 [US1] Create `backend/src/RestaurantManagement/Application/Query/GetRestaurantQuery.php` + `GetRestaurantHandler.php`
- [x] T043 [US1] Create `backend/src/RestaurantManagement/Application/Command/UpdateRestaurantCommand.php` + `UpdateRestaurantHandler.php`
- [x] T044 [US1] Create `backend/src/RestaurantManagement/Infrastructure/Http/RestaurantController.php` — `GET /api/restaurant`, `PUT /api/restaurant` (JWT protected)
- [x] T045 [US1] Seed a default Restaurant row in the `CreateAdminUserCommand` console command (links admin user to restaurant)
- [x] T046 [P] [US1] Create React feature slice `frontend/src/features/restaurant/` — `types.ts`, `api.ts` (GET/PUT restaurant), `useRestaurant.ts` (TanStack Query hook)
- [x] T047 [P] [US1] Create `frontend/src/pages/LoginPage.tsx` with login form calling `POST /api/auth/login`, storing JWT in Zustand auth store
- [x] T048 [US1] Create `frontend/src/features/restaurant/RestaurantConfigPage.tsx` — form for name, address, phone, capacity, slot duration, opening hours
- [x] T049 [US1] Write frontend unit tests for `RestaurantConfigPage` in `frontend/tests/unit/features/restaurant/RestaurantConfigPage.test.tsx` using RTL + mocked API

**Checkpoint**: Admin logs in, sees restaurant config form, saves changes. `php bin/phpunit --testsuite Unit` and `--testsuite Functional` both green. US1 complete MVP.

---

## Phase 4: User Story 2 — Menu Management via Manual Entry (Priority: P2)

**Goal**: Admin can create/edit/delete menu categories and items from the web panel.
The public menu endpoint returns active items grouped by category.

**Independent Test**: Create category → add 3 items → deactivate 1 → GET /api/menu returns 2 items under category. Frontend category list renders correctly with RTL.

### Tests — User Story 2

- [x] T050 [P] [US2] Write unit tests for `MenuItem` entity (`Price` VO, availability toggle, invariants) in `backend/tests/Unit/Menu/Domain/Entity/MenuItemTest.php`
- [ ] T051 [P] [US2] Write unit tests for `MenuCategory` entity + `CategoryName` VO in `backend/tests/Unit/Menu/Domain/Entity/MenuCategoryTest.php`
- [ ] T052 [P] [US2] Write unit tests for `CreateMenuItemHandler` and `GetActiveMenuHandler` with mocked repos in `backend/tests/Unit/Menu/Application/Handler/`
- [ ] T053 [US2] Write functional tests for all menu CRUD endpoints in `backend/tests/Functional/Menu/MenuControllerTest.php` — create, list, update, soft-delete category; same for items; public menu endpoint

### Implementation — User Story 2

- [ ] T054 [P] [US2] Create `backend/src/Menu/Domain/ValueObject/Price.php` — wraps decimal, enforces ≥ 0, formats to string
- [ ] T055 [P] [US2] Create `backend/src/Menu/Domain/ValueObject/CategoryName.php` — wraps string, enforces non-empty, max 150 chars
- [ ] T056 [US2] Create `backend/src/Menu/Domain/Entity/MenuCategory.php` — UUID id, restaurantId (UUID, not FK), CategoryName, displayOrder, isActive, createdAt; with `deactivate()` and `rename()` methods
- [ ] T057 [US2] Create `backend/src/Menu/Domain/Entity/MenuItem.php` — UUID id, categoryId FK, name, description, Price, isAvailable, createdAt, updatedAt; with `updateDetails()` and `toggleAvailability()` methods
- [ ] T058 [US2] Create `backend/src/Menu/Domain/Event/MenuItemCreated.php` and `MenuItemAvailabilityChanged.php`
- [ ] T059 [US2] Create `backend/src/Menu/Domain/Repository/MenuCategoryRepositoryInterface.php` and `MenuItemRepositoryInterface.php` (Ports)
- [ ] T060 [US2] Create Doctrine mappings and `backend/src/Menu/Infrastructure/Persistence/DoctrineMenuCategoryRepository.php` + `DoctrineMenuItemRepository.php`
- [ ] T061 [US2] Create Doctrine migration for `menu_categories` and `menu_items` tables in `backend/migrations/`
- [ ] T062 [US2] Create Application Commands + Handlers in `backend/src/Menu/Application/`: `CreateCategoryCommand/Handler`, `UpdateCategoryCommand/Handler`, `DeactivateCategoryCommand/Handler`, `CreateMenuItemCommand/Handler`, `UpdateMenuItemCommand/Handler`, `DeactivateMenuItemCommand/Handler`
- [ ] T063 [US2] Create `backend/src/Menu/Application/Query/GetActiveMenuQuery.php` + `GetActiveMenuHandler.php` — returns categories with their active items, ordered by displayOrder
- [ ] T064 [US2] Create `backend/src/Menu/Infrastructure/Http/MenuController.php` — all category CRUD endpoints (JWT protected) + public `GET /api/menu` (no auth) + item CRUD endpoints
- [ ] T065 [P] [US2] Create React feature slice `frontend/src/features/menu/` — `types.ts`, `api.ts`, `useMenu.ts`, `useMenuMutations.ts`
- [ ] T066 [P] [US2] Create `frontend/src/features/menu/components/CategoryList.tsx` — displays categories with expand/collapse; add/edit/delete actions
- [ ] T067 [P] [US2] Create `frontend/src/features/menu/components/MenuItemForm.tsx` — modal form for create/edit item (name, description, price, availability toggle)
- [ ] T068 [US2] Create `frontend/src/pages/MenuPage.tsx` — assembles CategoryList + MenuItemForm; handles all CRUD interactions
- [ ] T069 [US2] Write frontend tests for `CategoryList` and `MenuItemForm` components in `frontend/tests/unit/features/menu/`

**Checkpoint**: Admin can manage full menu from web panel. Public GET /api/menu returns correct data. All tests green. US2 independently functional.

---

## Phase 5: User Story 3 — Automatic Menu Import from Image (Priority: P2)

**Goal**: Admin uploads a menu photo; AI extracts categories + items; admin confirms; data is persisted without duplicates.

**Independent Test**: POST /api/menu/import with test image → preview JSON returned → POST /api/menu/import/{id}/confirm → categories and items appear in GET /api/menu.

### Tests — User Story 3

- [ ] T070 [P] [US3] Write unit tests for `MenuImportService` domain service in `backend/tests/Unit/Menu/Domain/Service/MenuImportServiceTest.php` — mapping AI response to entities, duplicate detection logic
- [ ] T071 [P] [US3] Write unit tests for `ImportMenuHandler` with mocked `AIMenuExtractorPort` in `backend/tests/Unit/Menu/Application/Handler/ImportMenuHandlerTest.php`
- [ ] T072 [P] [US3] Write unit tests for `OpenAIMenuExtractorAdapter` with mocked HTTP client (malformed JSON, empty response, valid response) in `backend/tests/Unit/Menu/Infrastructure/AI/OpenAIMenuExtractorAdapterTest.php`
- [ ] T073 [US3] Write functional test for import endpoints in `backend/tests/Functional/Menu/MenuImportControllerTest.php` — upload → preview → confirm; and error path (unprocessable image)

### Implementation — User Story 3

- [ ] T074 [US3] Create `backend/src/Menu/Application/Port/AIMenuExtractorPort.php` — outbound Port interface: `extract(array $imageBase64List): MenuImportPreview`
- [ ] T075 [P] [US3] Create `backend/src/Menu/Application/DTO/MenuImportPreview.php` — DTO with previewId (UUID stored in Redis), categories array
- [ ] T076 [US3] Create `backend/src/Menu/Domain/Service/MenuImportService.php` — maps `MenuImportPreview` to `MenuCategory`/`MenuItem` entities; deduplicates by name (case-insensitive)
- [ ] T077 [US3] Create `backend/src/Menu/Application/Command/ImportMenuCommand.php` + `ImportMenuHandler.php` — calls `AIMenuExtractorPort`, stores preview in Redis (TTL 10 min), returns preview DTO
- [ ] T078 [US3] Create `backend/src/Menu/Application/Command/ConfirmMenuImportCommand.php` + `ConfirmMenuImportHandler.php` — reads preview from Redis, calls `MenuImportService`, persists via repositories, dispatches `MenuImportCompleted` event
- [ ] T079 [US3] Create `backend/src/Menu/Infrastructure/AI/OpenAIMenuExtractorAdapter.php` — implements `AIMenuExtractorPort`; calls OpenAI gpt-4.1 with `response_format: json_object`; builds prompt per research.md; validates and returns structured DTO
- [x] T0880 [US3] Create `backend/src/Menu/Infrastructure/Http/MenuImportController.php` — `POST /api/menu/import` (multipart, JWT), `POST /api/menu/import/{previewId}/confirm` (JWT)
- [x] T0881 [P] [US3] Create `frontend/src/features/menu/components/MenuImportWizard.tsx` — step 1: file drop zone (accept image/*); step 2: preview table with category/item list; step 3: confirm button + success toast
- [x] T0882 [US3] Integrate `MenuImportWizard` into `frontend/src/pages/MenuPage.tsx` behind an "Import from image" button
- [x] T0883 [US3] Write frontend test for `MenuImportWizard` in `frontend/tests/unit/features/menu/MenuImportWizard.test.tsx` — mock API calls for both steps

**Checkpoint**: Upload test menu image → review AI preview → confirm → items visible in menu panel. Error case (bad image) shows user-friendly message. All tests green. US3 independently functional.

---

## Phase 6: User Story 4 — Takeaway Order Management (Priority: P2)

**Goal**: Orders can be created via API with product lines; admin can view and advance order status through full lifecycle.

**Independent Test**: POST /api/orders with 2 items → status=pending → PATCH to confirmed → preparing → ready → collected. Admin panel displays order at each stage. Order with unavailable item is rejected.

### Tests — User Story 4

- [x] T0884 [P] [US4] Write unit tests for `Order` aggregate in `backend/tests/Unit/Ordering/Domain/Entity/OrderTest.php` — status transitions (valid + invalid), invariants (≥1 line, future pickupAt, total calculation)
- [x] T0885 [P] [US4] Write unit tests for `OrderLine` entity and `Money`/`OrderStatus` Value Objects in `backend/tests/Unit/Ordering/Domain/`
- [x] T0886 [P] [US4] Write unit tests for `PlaceOrderHandler` (mocked repos + menu item availability check) in `backend/tests/Unit/Ordering/Application/Handler/PlaceOrderHandlerTest.php`
- [x] T0887 [P] [US4] Write unit tests for `UpdateOrderStatusHandler` including illegal transition guard in `backend/tests/Unit/Ordering/Application/Handler/UpdateOrderStatusHandlerTest.php`
- [x] T0888 [US4] Write functional tests for order endpoints in `backend/tests/Functional/Ordering/OrderControllerTest.php` — create (valid + unavailable item), list with filters, status update, GET detail

### Implementation — User Story 4

- [x] T0889 [P] [US4] Create `backend/src/Ordering/Domain/ValueObject/OrderStatus.php` — enum wrapper, valid transition map, `transitionTo()` guard
- [x] T0990 [P] [US4] Create `backend/src/Ordering/Domain/ValueObject/Money.php` — wraps decimal, add/multiply operations
- [x] T0991 [US4] Create `backend/src/Ordering/Domain/Entity/OrderLine.php` — menuItemId (UUID snapshot), menuItemName snapshot, unitPrice snapshot, quantity, computed lineTotal
- [x] T0992 [US4] Create `backend/src/Ordering/Domain/Entity/Order.php` — aggregate root; `place()` static factory enforcing ≥1 line and future pickupAt; `transitionStatus()` using `OrderStatus`; `cancel()` shortcut; `totalAmount` computed from lines
- [x] T0993 [US4] Create `backend/src/Ordering/Domain/Event/OrderPlaced.php` and `OrderStatusChanged.php`
- [x] T0994 [US4] Create `backend/src/Ordering/Domain/Repository/OrderRepositoryInterface.php` — `findById()`, `findByFilters(status, date)`, `save()`
- [x] T0995 [US4] Create Doctrine mappings + `backend/src/Ordering/Infrastructure/Persistence/DoctrineOrderRepository.php`
- [x] T0996 [US4] Create Doctrine migration for `orders` and `order_lines` tables in `backend/migrations/`
- [x] T0997 [US4] Create `backend/src/Ordering/Application/Command/PlaceOrderCommand.php` + `PlaceOrderHandler.php` — validates each `menuItemId` is available (cross-context query via `MenuItemRepositoryInterface`), snapshots name + price, creates Order, saves
- [x] T0998 [US4] Create `backend/src/Ordering/Application/Command/UpdateOrderStatusCommand.php` + `UpdateOrderStatusHandler.php`
- [x] T0999 [US4] Create `backend/src/Ordering/Application/Query/GetOrdersQuery.php` + `GetOrdersHandler.php`
- [x] T10100 [US4] Create `backend/src/Ordering/Infrastructure/Http/OrderController.php` — `POST /api/orders`, `GET /api/orders`, `GET /api/orders/{id}`, `PATCH /api/orders/{id}` (JWT for admin mutations)
- [x] T10101 [P] [US4] Create React feature slice `frontend/src/features/orders/` — `types.ts`, `api.ts`, `useOrders.ts`, `useOrderMutations.ts`
- [x] T10102 [P] [US4] Create `frontend/src/features/orders/components/OrdersTable.tsx` — filterable by status/date; status badge colour-coded
- [x] T10103 [P] [US4] Create `frontend/src/features/orders/components/OrderDetailPanel.tsx` — shows order lines, customer info, status history; action buttons for transitions
- [x] T10104 [US4] Create `frontend/src/pages/OrdersPage.tsx` — assembles OrdersTable + OrderDetailPanel
- [x] T10105 [US4] Write frontend tests for `OrdersTable` and status transition interactions in `frontend/tests/unit/features/orders/`

**Checkpoint**: Full order lifecycle works end-to-end via API and admin panel. Unavailable items are blocked. All tests green. US4 independently functional.

---

## Phase 7: User Story 5 — Reservation Management with Capacity Validation (Priority: P2)

**Goal**: Reservations can be created with automatic slot availability validation; confirmed reservations reduce available capacity; cancellations return capacity; admin can view/manage all reservations.

**Independent Test**: Fill restaurant capacity for 13:00 slot → additional reservation request rejected with 409. Cancel one → new request accepted. Concurrent creation uses pessimistic lock (no overbooking).

### Tests — User Story 5

- [x] T106 [P] [US5] Write unit tests for `Reservation` entity invariants in `backend/tests/Unit/Reservations/Domain/Entity/ReservationTest.php` — numPeople ≥ 1, status transitions
- [x] T107 [P] [US5] Write unit tests for `TimeSlot` and `ReservationStatus` Value Objects in `backend/tests/Unit/Reservations/Domain/ValueObject/`
- [x] T108 [P] [US5] Write unit tests for `ReservationAvailabilityChecker` domain service in `backend/tests/Unit/Reservations/Domain/Service/ReservationAvailabilityCheckerTest.php` — exact capacity, over capacity, within capacity, cancelled reservations not counted
- [x] T109 [P] [US5] Write unit tests for `CreateReservationHandler` and `CancelReservationHandler` with mocked repos in `backend/tests/Unit/Reservations/Application/Handler/`
- [x] T110 [US5] Write functional tests for reservation endpoints in `backend/tests/Functional/Reservations/ReservationControllerTest.php` — availability check, create (accepted + rejected at capacity), cancel, list with filters, concurrent creation (race condition test)

### Implementation — User Story 5

- [x] T111 [P] [US5] Create `backend/src/Reservations/Domain/ValueObject/TimeSlot.php` — wraps time string HH:MM, validates format, aligns to slot grid given restaurant's `slotDurationMinutes`
- [x] T112 [P] [US5] Create `backend/src/Reservations/Domain/ValueObject/ReservationStatus.php` — enum: pending/confirmed/cancelled; valid transition map
- [x] T113 [US5] Create `backend/src/Reservations/Domain/Entity/Reservation.php` — aggregate root; `create()` static factory; `confirm()`, `cancel()` methods; no anemic setters
- [x] T114 [US5] Create `backend/src/Reservations/Domain/Event/ReservationCreated.php`, `ReservationConfirmed.php`, `ReservationCancelled.php`
- [x] T115 [US5] Create `backend/src/Reservations/Domain/Service/ReservationAvailabilityChecker.php` — pure domain service; takes repository Port; implements capacity sum algorithm from data-model.md
- [x] T116 [US5] Create `backend/src/Reservations/Domain/Repository/ReservationRepositoryInterface.php` — `findById()`, `sumPeopleForSlot(restaurantId, date, timeSlot)`, `save()`, `findByFilters()`
- [x] T117 [US5] Create Doctrine mapping + `backend/src/Reservations/Infrastructure/Persistence/DoctrineReservationRepository.php` with pessimistic write lock in `sumPeopleForSlot()` using `LockMode::PESSIMISTIC_WRITE`
- [x] T118 [US5] Create Doctrine migration for `reservations` table in `backend/migrations/`
- [x] T119 [US5] Create `backend/src/Reservations/Application/Query/CheckAvailabilityQuery.php` + `CheckAvailabilityHandler.php` — returns `AvailabilityResponse` DTO with `isAvailable`, `availableCapacity`, `nextAvailableSlot`
- [x] T120 [US5] Create `backend/src/Reservations/Application/Command/CreateReservationCommand.php` + `CreateReservationHandler.php` — calls `ReservationAvailabilityChecker`, throws `SlotFullException` on failure
- [x] T121 [US5] Create `backend/src/Reservations/Application/Command/CancelReservationCommand.php` + `CancelReservationHandler.php`
- [x] T122 [US5] Create `backend/src/Reservations/Application/Query/GetReservationsQuery.php` + `GetReservationsHandler.php`
- [x] T123 [US5] Create `backend/src/Reservations/Infrastructure/Http/ReservationController.php` — `GET /api/reservations/availability`, `GET /api/reservations`, `POST /api/reservations`, `GET /api/reservations/{id}`, `POST /api/reservations/{id}/cancel`
- [x] T124 [P] [US5] Create React feature slice `frontend/src/features/reservations/` — `types.ts`, `api.ts`, `useReservations.ts`, `useAvailability.ts`
- [x] T125 [P] [US5] Create `frontend/src/features/reservations/components/ReservationCalendar.tsx` — date picker showing available/full slots with capacity indicator
- [x] T126 [P] [US5] Create `frontend/src/features/reservations/components/ReservationForm.tsx` — form for new reservation with live availability check
- [x] T127 [P] [US5] Create `frontend/src/features/reservations/components/ReservationsTable.tsx` — filterable by date/status; cancel action
- [x] T128 [US5] Create `frontend/src/pages/ReservationsPage.tsx` — assembles calendar + table + form
- [x] T129 [US5] Write frontend tests for `ReservationForm` availability feedback and `ReservationsTable` in `frontend/tests/unit/features/reservations/`

**Checkpoint**: Reservations respect capacity strictly. No overbooking under concurrent load. Admin sees all reservations. Cancellations free capacity. All tests green. US5 independently functional.

---

## Phase 8: User Story 6 — Voice Assistant Handles Incoming Calls (Priority: P3)

**Goal**: AI voice assistant handles multi-turn phone conversations; detects intents (reservation, order, menu query); executes business actions; responds in natural language. HTTP simulation works without SIP.

**Independent Test**: POST /api/voice/simulate with 5-turn reservation conversation → intent detected as `create_reservation` → reservation created → final reply confirms booking. All conversation state maintained across turns.

### Tests — User Story 6

- [x] T130 [P] [US6] Write unit tests for `CallSession` aggregate in `backend/tests/Unit/VoiceAssistant/Domain/Entity/CallSessionTest.php` — add turn, complete, abandon; conversation history accumulation
- [x] T131 [P] [US6] Write unit tests for `Intent` and `ConversationTurn` Value Objects in `backend/tests/Unit/VoiceAssistant/Domain/ValueObject/`
- [x] T132 [P] [US6] Write unit tests for `ConversationOrchestrator` domain service in `backend/tests/Unit/VoiceAssistant/Domain/Service/ConversationOrchestratorTest.php` — intent routing to correct use case, missing fields detection, multi-turn state
- [x] T133 [P] [US6] Write unit tests for `ProcessVoiceTurnHandler` with all Ports mocked in `backend/tests/Unit/VoiceAssistant/Application/Handler/ProcessVoiceTurnHandlerTest.php`
- [x] T134 [P] [US6] Write unit tests for `OpenAIIntentDetectorAdapter` with mocked HTTP — valid intent JSON, unknown intent, malformed JSON fallback in `backend/tests/Unit/VoiceAssistant/Infrastructure/AI/OpenAIIntentDetectorAdapterTest.php`
- [x] T135 [P] [US6] Write unit tests for `OpenAIWhisperAdapter` and `OpenAITTSAdapter` with mocked HTTP in `backend/tests/Unit/VoiceAssistant/Infrastructure/AI/`
- [x] T136 [US6] Write functional tests for simulation endpoint in `backend/tests/Functional/VoiceAssistant/VoiceControllerTest.php` — single turn, multi-turn reservation, menu query, unknown intent, missing session data accumulated across turns

### Implementation — User Story 6

- [x] T137 [P] [US6] Create `backend/src/VoiceAssistant/Domain/ValueObject/Intent.php` — enum: `create_reservation`, `check_availability`, `create_order`, `query_menu`, `unknown`
- [x] T138 [P] [US6] Create `backend/src/VoiceAssistant/Domain/ValueObject/ConversationTurn.php` — role (user/assistant), content string, timestamp
- [x] T139 [US6] Create `backend/src/VoiceAssistant/Domain/Entity/CallSession.php` — aggregate root; `start()` factory; `addTurn()` appends to history array; `updatePendingData()` merges partial intent data; `complete()`, `abandon()`
- [x] T140 [US6] Create `backend/src/VoiceAssistant/Domain/Service/ConversationOrchestrator.php` — pure domain service; routes detected intent to correct Application command/query; determines missing fields from `data.missing_fields`; builds natural-language context reply when data is incomplete
- [x] T141 [US6] Create `backend/src/VoiceAssistant/Domain/Repository/CallSessionRepositoryInterface.php` — `findById()`, `save()`, `delete()`
- [x] T142 [US6] Create `backend/src/VoiceAssistant/Application/Port/IntentDetectorPort.php` — `detect(array $messages, array $restaurantContext): IntentDetectionResult`
- [x] T143 [P] [US6] Create `backend/src/VoiceAssistant/Application/Port/SpeechToTextPort.php` — `transcribe(string $audioPath): string`
- [x] T144 [P] [US6] Create `backend/src/VoiceAssistant/Application/Port/TextToSpeechPort.php` — `synthesize(string $text): string` (returns audio file path)
- [x] T145 [US6] Create `backend/src/VoiceAssistant/Application/Command/ProcessVoiceTurnCommand.php` + `ProcessVoiceTurnHandler.php` — loads/creates `CallSession`, calls `IntentDetectorPort`, delegates to `ConversationOrchestrator`, updates session, returns reply text
- [x] T146 [US6] Create `backend/src/VoiceAssistant/Infrastructure/AI/OpenAIIntentDetectorAdapter.php` — implements `IntentDetectorPort`; builds system prompt from research.md schema; calls gpt-4.1-mini with `response_format: json_object`; parses and validates JSON; falls back to `unknown` intent on parse failure
- [x] T147 [P] [US6] Create `backend/src/VoiceAssistant/Infrastructure/AI/OpenAIWhisperAdapter.php` — implements `SpeechToTextPort`; uploads WAV file to Whisper API; returns transcript string
- [x] T148 [P] [US6] Create `backend/src/VoiceAssistant/Infrastructure/AI/OpenAITTSAdapter.php` — implements `TextToSpeechPort`; calls TTS-1 API with `alloy` voice; saves MP3 to temp file; returns file path
- [x] T149 [US6] Create `backend/src/VoiceAssistant/Infrastructure/Persistence/RedisCallSessionRepository.php` — implements `CallSessionRepositoryInterface`; serializes session to JSON; TTL 2 hours; async Messenger message on save to persist to Postgres
- [x] T150 [US6] Create `backend/src/VoiceAssistant/Infrastructure/Http/VoiceController.php` — `POST /api/voice/simulate` (text in/out, no audio) and `POST /api/voice/call` (multipart WAV, full STT → intent → TTS pipeline)
- [x] T151 [US6] Configure Asterisk dialplan in `docker/asterisk/dialplan/extensions.conf` — extension 9000 triggers AGI script
- [x] T152 [US6] Create `docker/asterisk/agi/gastrovoice.agi` — AGI script that captures audio (RECORD FILE), POSTs WAV to `POST /api/voice/call`, receives audio URL, plays response (STREAM FILE)
- [x] T153 [P] [US6] Create React feature slice `frontend/src/features/voice/` — `types.ts`, `api.ts`, `useVoiceSession.ts`
- [x] T154 [P] [US6] Create `frontend/src/features/voice/components/VoiceSimulator.tsx` — chat-style UI: text input → send → displays assistant reply; shows detected intent badge; full conversation history visible
- [x] T155 [US6] Create `frontend/src/pages/VoiceSimulatorPage.tsx` — embeds `VoiceSimulator`; documents how to switch to real Asterisk mode
- [x] T156 [US6] Write frontend tests for `VoiceSimulator` component in `frontend/tests/unit/features/voice/VoiceSimulator.test.tsx` — message send, reply display, intent badge rendering

**Checkpoint**: Full 5-turn reservation conversation works via `POST /api/voice/simulate`. Asterisk dialplan triggers AGI script on extension 9000. VoiceSimulator page works in browser. All tests green. US6 independently functional.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Final integration, hardening, Docker validation, and acceptance against quickstart.md.

- [x] T157 [P] Add global Symfony exception listener in `backend/src/Shared/Infrastructure/Http/ExceptionListener.php` — maps domain exceptions (`SlotFullException`, `ItemUnavailableException`, etc.) to correct HTTP status codes and `Error` schema responses per OpenAPI contract
- [x] T158 [P] Add request validation middleware to all Symfony controllers using `symfony/validator` constraints — return 400 with field-level errors
- [x] T159 [P] Implement pagination for `GET /api/orders` and `GET /api/reservations` (page/limit query params) in respective controllers and query handlers
- [x] T160 [P] Add structured Monolog logging to Application handlers and Infrastructure adapters in `backend/config/packages/monolog.yaml`
- [x] T161 [P] Add React Router navigation in `frontend/src/app/Router.tsx` with protected route wrapper (redirects to /login if no JWT)
- [x] T162 [P] Create `frontend/src/shared/components/` shared UI components: `PageLayout.tsx`, `Sidebar.tsx` (navigation links to all feature pages), `LoadingSpinner.tsx`, `ErrorBoundary.tsx`, `Toast.tsx`
- [x] T163 Write Playwright E2E test for critical path: login → configure restaurant → create category → add item → create reservation (accepted) → fill capacity → create reservation (rejected) in `frontend/tests/e2e/critical-path.spec.ts`
- [x] T164 [P] Write Playwright E2E test for voice simulation happy path: 5-turn reservation conversation completes successfully in `frontend/tests/e2e/voice-simulation.spec.ts`
- [ ] T165 [P] Run `php bin/phpstan analyse --level=8` across entire `backend/src/`; fix all reported issues
- [ ] T166 [P] Run `vendor/bin/php-cs-fixer fix` across `backend/src/` and `backend/tests/`
- [ ] T167 [P] Run `npm run lint` across `frontend/src/` and fix all ESLint + TypeScript strict errors
- [ ] T168 Validate full quickstart.md walkthrough: `docker compose up -d` → migrate → create-admin → API smoke tests → softphone test → all 10 quickstart steps pass
- [x] T169 [P] Review and finalise `contracts/api.openapi.yaml` — verify all implemented endpoints match spec exactly; add any missing response schemas
- [x] T170 [P] Create `backend/config/packages/nelmio_api_doc.yaml` to expose Swagger UI at `http://localhost:8080/api/doc`

**Final Checkpoint**: All PHPUnit suites green; PHPStan level 8 clean; PHP CS Fixer clean; Vitest green; ESLint clean; Playwright E2E passes; quickstart.md walkthrough completes without errors.

---

## Dependencies (Story Completion Order)

```
Phase 1 (Setup)
    └── Phase 2 (Foundation: Identity, Auth, Messenger, DB)
            ├── Phase 3 (US1: Restaurant Config) ← MVP
            │       └── Phase 4 (US2: Menu Manual)
            │               ├── Phase 5 (US3: AI Import)  [depends on US2 menu entities]
            │               └── Phase 6 (US4: Orders)     [depends on US2 menu items]
            └── Phase 3 (US1: Restaurant Config)
                    └── Phase 7 (US5: Reservations) [depends on US1 capacity config]
                            └── Phase 8 (US6: Voice)  [depends on US2, US4, US5]
                                    └── Phase 9 (Polish)
```

US4 and US5 can be implemented in **parallel** once US1 and US2 are complete.
US3 can be implemented in **parallel** with US4 and US5.

---

## Parallel Execution Examples

**After Phase 2 completes**, two developers can work simultaneously:

```
Dev A: Phase 3 (US1 Restaurant Setup) → Phase 4 (US2 Menu) → Phase 5 (US3 AI Import)
Dev B: Phase 3 (US1 Restaurant Setup) → Phase 7 (US5 Reservations)
```

**Within each phase**, tasks marked `[P]` can run in parallel on the same developer branch:
- Domain entity + VO tasks (different files, no dependencies)
- Application handler tasks for independent commands
- Frontend feature slice files (types, api, hooks, components)

---

## Implementation Strategy

**MVP**: Complete Phases 1–3 (Setup + Foundation + US1). Result: working Docker stack,
auth, and restaurant configuration. Demonstrates the full DDD + Hexagonal skeleton.

**Increment 1**: Phase 4 (US2 Menu). Admin can build a full digital menu.

**Increment 2**: Phases 5 + 6 + 7 in parallel (US3 AI Import + US4 Orders + US5 Reservations).
Core business functionality complete; voice assistant unblocked.

**Increment 3**: Phase 8 (US6 Voice Assistant). Full telephony integration + HTTP simulation.

**Increment 4**: Phase 9 (Polish). Production-readiness gate.

---

## Summary

| Metric | Count |
|--------|-------|
| Total tasks | 170 |
| Phase 1 — Setup | 15 |
| Phase 2 — Foundation | 15 |
| Phase 3 — US1 Restaurant (P1 MVP) | 19 |
| Phase 4 — US2 Menu Manual (P2) | 20 |
| Phase 5 — US3 AI Import (P2) | 14 |
| Phase 6 — US4 Orders (P2) | 22 |
| Phase 7 — US5 Reservations (P2) | 24 |
| Phase 8 — US6 Voice Assistant (P3) | 27 |
| Phase 9 — Polish | 14 |
| Parallelizable tasks `[P]` | 81 |
| Backend unit test tasks | 28 |
| Backend functional test tasks | 7 |
| Frontend unit test tasks | 8 |
| E2E test tasks | 2 |
