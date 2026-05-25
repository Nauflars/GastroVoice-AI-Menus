# Research: GastroVoice AI — Phase 0

**Feature**: 001-gastrovoice-ai-system
**Date**: 2026-05-25

---

## 1. DDD Bounded Contexts Layout for Symfony + Hexagonal Architecture

**Decision**: Four Bounded Contexts inside `backend/src/`:

| Bounded Context | Responsibility |
|-----------------|---------------|
| `RestaurantManagement` | Restaurant config, opening hours, capacity |
| `Menu` | Categories, menu items, AI import |
| `Ordering` | Takeaway orders, order lines, lifecycle |
| `Reservations` | Reservations, availability validation |
| `VoiceAssistant` | Call sessions, intent detection, STT/TTS orchestration |
| `Identity` | Authentication, JWT tokens (thin context) |

**Rationale**: Keeps domain rules isolated; cross-context communication via Domain Events
on the Symfony Messenger bus. Each context has `Domain/`, `Application/`, `Infrastructure/`
layers. Symfony controllers and Doctrine repositories live exclusively in `Infrastructure/`.

**Alternatives considered**:
- Single `App` namespace: rejected — violates DDD Bounded Context principle; would create
  tight coupling between unrelated business rules.
- Separate Symfony micro-services per context: rejected — over-engineering for v1; a modular
  monolith with clear boundaries is the right trade-off and can be split later.

---

## 2. Hexagonal Architecture Layer Dependencies

**Decision**:

```
Domain        ← no external dependencies (pure PHP objects)
Application   ← depends only on Domain interfaces (Ports)
Infrastructure ← depends on Application + Domain; implements Ports using Symfony/Doctrine
```

Port interfaces (e.g., `OrderRepositoryInterface`, `STTServiceInterface`) are declared
in the `Domain` or `Application` layer. Adapters implement them in `Infrastructure`.

**Rationale**: Enforces the "dependency inversion" rule from the constitution. Domain and
Application layers are fully testable in unit tests without any I/O.

**Alternatives considered**:
- Placing ports in `Infrastructure`: rejected — would couple Domain to Infrastructure.

---

## 3. Symfony Directory Structure (DDD + Hexagonal)

**Decision**:

```
backend/
├── src/
│   ├── Menu/
│   │   ├── Domain/
│   │   │   ├── Entity/          # MenuCategory, MenuItem
│   │   │   ├── ValueObject/     # Price, CategoryName
│   │   │   ├── Repository/      # MenuCategoryRepositoryInterface (Port)
│   │   │   ├── Service/         # MenuImportService (domain logic)
│   │   │   └── Event/           # MenuItemCreated, etc.
│   │   ├── Application/
│   │   │   ├── Command/         # CreateMenuItemCommand, ImportMenuCommand
│   │   │   ├── Handler/         # CreateMenuItemHandler, ImportMenuHandler
│   │   │   ├── Query/           # GetActiveMenuQuery
│   │   │   └── Port/            # AIMenuExtractorPort (outbound port)
│   │   └── Infrastructure/
│   │       ├── Persistence/     # DoctrineMenuCategoryRepository
│   │       ├── AI/              # OpenAIMenuExtractorAdapter
│   │       └── Http/            # MenuController (Symfony)
│   ├── Ordering/
│   │   └── [same structure]
│   ├── Reservations/
│   │   └── [same structure]
│   ├── VoiceAssistant/
│   │   └── [same structure]
│   ├── RestaurantManagement/
│   │   └── [same structure]
│   └── Identity/
│       └── [same structure]
├── tests/
│   ├── Unit/            # Pure domain + application tests (PHPUnit, no I/O)
│   ├── Functional/      # HTTP-level tests (Symfony WebTestCase / KernelTestCase)
│   └── E2E/             # (optional placeholder) Playwright/Cypress
├── config/
├── migrations/
└── docker/
```

**Rationale**: Mirrors the constitution's prescribed layout. Symfony bundle structure
is not used; instead explicit PSR-4 namespacing by bounded context.

---

## 4. OpenAI Integration — Menu Extraction

**Decision**: Use `gpt-4.1` (multimodal) via the OpenAI PHP SDK (`openai-php/client`).
The `AIMenuExtractorPort` outbound port in the `Menu/Application/Port/` layer abstracts
the provider. The `OpenAIMenuExtractorAdapter` in `Infrastructure/AI/` implements it.

**Prompt strategy**:
```
You are a menu data extractor. Analyse the provided menu image.
Return ONLY valid JSON with this exact schema:
{
  "categories": [
    {
      "name": "string",
      "items": [
        { "name": "string", "description": "string", "price": number }
      ]
    }
  ]
}
Do not include any explanation or text outside the JSON.
```

**Rationale**: Strict JSON-only output prompt minimises hallucination and parsing errors.
The Port pattern allows swapping to another provider (e.g., Claude, Gemini) without
touching application or domain code.

**Alternatives considered**:
- `gpt-4o`: equivalent capability; `gpt-4.1` is the latest at time of writing, preferred.
- Function calling / structured outputs API: use `response_format: json_object` as
  additional guardrail against non-JSON output.

---

## 5. OpenAI Integration — Voice Intent Detection

**Decision**: Use `gpt-4.1-mini` via the same OpenAI PHP client with a structured
system prompt. The `IntentDetectorPort` in `VoiceAssistant/Application/Port/` abstracts
the provider.

**Intent schema**:
```json
{
  "intent": "create_reservation | check_availability | create_order | query_menu | unknown",
  "data": {
    "date": "YYYY-MM-DD | null",
    "time": "HH:MM | null",
    "num_people": "integer | null",
    "customer_name": "string | null",
    "customer_phone": "string | null",
    "products": [{ "name": "string", "quantity": "integer" }],
    "missing_fields": ["field_name"]
  },
  "reply": "string (natural language response to speak to the user)"
}
```

The `missing_fields` array allows the system to know what to ask next without additional
logic, keeping conversation state minimal.

**Conversation state**: Stored in a `CallSession` entity (Redis-backed for speed, with
Doctrine fallback). Each turn appends to a `messages` array (OpenAI chat format).

**Alternatives considered**:
- Fine-tuned model: overkill for v1; prompt engineering is sufficient and cheaper.
- Rasa / Dialogflow: rejected — adds external service dependency; keeping everything
  in OpenAI simplifies infrastructure.

---

## 6. STT / TTS Strategy

**Decision**:
- **STT**: Whisper API (`openai-php/client` audio endpoint). Port: `SpeechToTextPort`.
  Adapter: `OpenAIWhisperAdapter`.
- **TTS**: OpenAI TTS API (`tts-1` model, voice: `alloy`). Port: `TextToSpeechPort`.
  Adapter: `OpenAITTSAdapter`.
- Audio format exchange with Asterisk: WAV (8kHz, 16-bit mono) for STT input;
  MP3 or WAV for TTS output piped back via AGI.

**Rationale**: Keeps the entire AI stack within one provider and one SDK. Both Ports are
swappable (e.g., Azure Cognitive Services, ElevenLabs) without touching domain logic.

**Alternatives considered**:
- Local Whisper (faster-whisper Docker service): viable for production to reduce latency
  and cost; documented as a v2 infrastructure option.
- Google Cloud TTS: equally viable; left as a named alternative adapter.

---

## 7. Asterisk Integration

**Decision**: Asterisk 20 (LTS) in Docker. Integration via **AGI (Asterisk Gateway Interface)**
scripts invoked from the dialplan. The AGI script calls the Symfony backend HTTP endpoint
with the audio payload (multipart/form-data) and receives the TTS audio URL or base64
audio in the JSON response. Asterisk then plays the audio.

**HTTP Simulation Endpoint**: `POST /api/voice/simulate` accepts `{ "session_id": "...",
"message": "..." }` and returns `{ "reply": "...", "intent": "..." }`. Bypasses STT/TTS.

**Rationale**: AGI gives full control of call flow from within the dialplan without
requiring a real-time WebSocket bridge. Simple to test with a softphone (Zoiper/Linphone).

**Alternatives considered**:
- AMI (Asterisk Manager Interface): more complex, event-driven; better for v2 when
  outbound calls or transfers are needed.
- ARI (Asterisk REST Interface): requires Stasis app; overkill for v1.

---

## 8. Frontend Architecture

**Decision**: React 18 + TypeScript + Vite. State management with **Zustand** (lightweight,
no boilerplate). Data fetching with **TanStack Query** (React Query). Routing with
**React Router v6**. UI component library: **shadcn/ui** (Tailwind CSS base).

**Folder structure**:
```
frontend/src/
├── features/           # Feature-sliced: menu/, orders/, reservations/, voice/
│   └── menu/
│       ├── components/
│       ├── hooks/
│       ├── api.ts
│       └── types.ts
├── shared/             # Shared UI components, utils, API client
├── pages/              # Route-level components
└── app/                # Router setup, global providers
```

**Rationale**: Feature-slice organisation mirrors DDD bounded contexts on the frontend.
TanStack Query handles cache, loading states and background refetch. Zustand for UI
state not owned by the server.

**Alternatives considered**:
- Redux Toolkit: heavier; not justified for this scope.
- Next.js: adds SSR complexity; the admin panel is auth-gated and doesn't need SEO.

---

## 9. Reservation Availability Algorithm

**Decision**: Time-slot based validation.

1. For a given `(date, time_slot)`, query `SUM(num_people)` from all `confirmed`
   reservations in that slot.
2. If `existing_sum + requested_num_people <= restaurant.capacity` → accept.
3. If not → reject with `SLOT_FULL` error and suggest the nearest available slot.

**Time slot granularity**: Configurable per restaurant (default 60 minutes). Two
reservations at 13:00 and 13:30 (with 30 min granularity) each consume a slot;
the system checks both slots if a reservation spans more than one.

**Race condition mitigation**: Doctrine pessimistic write lock
(`LockMode::PESSIMISTIC_WRITE`) on the reservation query during slot check + insert.
This is sufficient for single-node v1; distributed lock (Redis) documented for v2.

**Alternatives considered**:
- Application-level optimistic locking: simpler but allows overbooking under concurrent load.
- Redis atomic counters per slot: valid approach, documented as v2 upgrade path.

---

## 10. Authentication

**Decision**: JWT (JSON Web Tokens) via **LexikJWTAuthenticationBundle** for Symfony.
- `POST /api/auth/login` → returns access token (1h) + refresh token (7d).
- Stateless; all admin endpoints require `Authorization: Bearer <token>`.
- Password storage: bcrypt (Symfony password hasher).

**Rationale**: Standard Symfony JWT setup, well-documented, integrates with Symfony Security.

---

## 11. Docker Compose Services

**Decision**:

| Service | Image | Purpose |
|---------|-------|---------|
| `backend` | `php:8.3-fpm` + Nginx | Symfony API |
| `db` | `postgres:16` | Primary database |
| `redis` | `redis:7` | Cache + session + queue |
| `asterisk` | `andrius/asterisk` or custom | PBX for voice calls |
| `frontend` | `node:20` (dev) / Nginx (prod) | React dev server |
| `mailpit` | `axllent/mailpit` | Local email capture (optional) |

All secrets via `.env` file; `.env.example` committed.

---

## 12. Testing Strategy Summary

| Layer | Tool | Type | Scope |
|-------|------|------|-------|
| Domain entities/VOs | PHPUnit | Unit | All domain classes |
| Application handlers | PHPUnit + Prophecy | Unit | Mocked ports |
| Doctrine repositories | PHPUnit + test DB | Functional | Real Postgres |
| Symfony controllers | WebTestCase | Functional | Full HTTP cycle |
| OpenAI adapters | PHPUnit + HTTP mock | Unit | Mocked HTTP responses |
| React components | Vitest + RTL | Unit | Isolated rendering |
| React pages/flows | Vitest + RTL | Integration | With mock API |
| Critical user journeys | Playwright | E2E | Full stack |

Coverage gate: PHPStan level 8, PHP CS Fixer on backend; ESLint + TypeScript strict on frontend.

---

## 13. Open Questions Resolved

| Question | Resolution |
|----------|------------|
| Auth method | JWT via LexikJWTAuthenticationBundle |
| Menu AI provider | OpenAI gpt-4.1 multimodal, `json_object` response format |
| STT provider | OpenAI Whisper API |
| TTS provider | OpenAI TTS API (`tts-1`) |
| Asterisk integration method | AGI scripts + HTTP callbacks |
| Frontend state mgmt | Zustand + TanStack Query |
| Race condition handling | Doctrine pessimistic write lock |
| Time slot granularity | 60 min default, configurable |
| Redis role | Cache + call session state + Messenger transport |
| Frontend UI library | shadcn/ui (Tailwind) |
