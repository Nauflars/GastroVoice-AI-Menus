# Data Model: GastroVoice AI

**Feature**: 001-gastrovoice-ai-system
**Date**: 2026-05-25

---

## Bounded Contexts & Aggregate Roots

### 1. RestaurantManagement Context

#### `Restaurant` (Aggregate Root)

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | Primary key |
| `name` | string(255) | NOT NULL |
| `address` | string(500) | NOT NULL |
| `phone` | string(30) | NOT NULL |
| `seatCapacity` | int | Total covers; NOT NULL; ≥ 1 |
| `slotDurationMinutes` | int | Default 60; configurable |
| `timezone` | string(64) | IANA tz string, e.g. `Europe/Madrid` |
| `createdAt` | datetime | Immutable |
| `updatedAt` | datetime | Auto-updated |

#### `OpeningHour` (Entity, belongs to Restaurant)

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `restaurantId` | UUID FK | |
| `dayOfWeek` | enum (Mon–Sun) | |
| `openTime` | time | HH:MM |
| `closeTime` | time | HH:MM |
| `isClosed` | bool | True = closed that day |

---

### 2. Menu Context

#### `MenuCategory` (Aggregate Root)

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `restaurantId` | UUID | Cross-context reference (not FK) |
| `name` | string(150) | NOT NULL; unique per restaurant |
| `displayOrder` | int | For ordering in menu display |
| `isActive` | bool | Soft-deletion flag |
| `createdAt` | datetime | |

#### `MenuItem` (Aggregate Root)

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `categoryId` | UUID FK | |
| `name` | string(255) | NOT NULL |
| `description` | text | Nullable |
| `price` | decimal(10,2) | NOT NULL; ≥ 0.00 |
| `isAvailable` | bool | Controls visibility in active menu |
| `createdAt` | datetime | |
| `updatedAt` | datetime | |

**Value Objects**:
- `Price`: wraps `decimal(10,2)`, enforces ≥ 0, formats to string
- `CategoryName`: wraps string, enforces non-empty, max 150 chars

**Domain Events**:
- `MenuItemCreated`
- `MenuItemAvailabilityChanged`
- `MenuImportCompleted`

---

### 3. Ordering Context

#### `Order` (Aggregate Root)

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `restaurantId` | UUID | Cross-context reference |
| `status` | enum | `pending | confirmed | preparing | ready | collected | cancelled` |
| `pickupAt` | datetime | Requested pickup time |
| `totalAmount` | decimal(10,2) | Computed from order lines |
| `customerName` | string(255) | NOT NULL |
| `customerPhone` | string(30) | NOT NULL |
| `notes` | text | Optional customer notes |
| `createdAt` | datetime | |
| `updatedAt` | datetime | |

#### `OrderLine` (Entity, belongs to Order)

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `orderId` | UUID FK | |
| `menuItemId` | UUID | Cross-context reference (snapshot) |
| `menuItemName` | string(255) | Snapshot at order time |
| `unitPrice` | decimal(10,2) | Snapshot at order time |
| `quantity` | int | ≥ 1 |
| `lineTotal` | decimal(10,2) | Computed: unitPrice × quantity |

**Order status transitions**:
```
pending → confirmed → preparing → ready → collected
     ↘ cancelled (from any state before collected)
```

**Domain Events**:
- `OrderPlaced`
- `OrderStatusChanged`

**Invariants**:
- An `Order` MUST have at least one `OrderLine`.
- `pickupAt` MUST be in the future at creation time.
- All referenced `menuItemId` MUST be available at the time of order creation.

---

### 4. Reservations Context

#### `Reservation` (Aggregate Root)

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `restaurantId` | UUID | Cross-context reference |
| `reservationDate` | date | NOT NULL |
| `timeSlot` | time | HH:MM, aligned to slot grid |
| `numPeople` | int | NOT NULL; ≥ 1 |
| `customerName` | string(255) | NOT NULL |
| `customerPhone` | string(30) | NOT NULL |
| `customerEmail` | string(255) | Nullable |
| `status` | enum | `pending | confirmed | cancelled` |
| `notes` | text | Optional |
| `createdAt` | datetime | |
| `updatedAt` | datetime | |

**Availability Rule** (Domain Service: `ReservationAvailabilityChecker`):
```
availableCapacity(restaurantId, date, timeSlot) =
  restaurant.seatCapacity
  - SUM(numPeople WHERE status IN (pending, confirmed)
         AND reservationDate = date
         AND timeSlot = slot)

isAvailable = availableCapacity >= requested.numPeople
```

**Domain Events**:
- `ReservationCreated`
- `ReservationConfirmed`
- `ReservationCancelled`

---

### 5. VoiceAssistant Context

#### `CallSession` (Aggregate Root)

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID (= Asterisk call channel ID) | |
| `restaurantId` | UUID | |
| `status` | enum | `active | completed | abandoned` |
| `messages` | JSON | Array of `{role, content}` (OpenAI format) |
| `currentIntent` | string(50) | Last detected intent |
| `pendingData` | JSON | Partial intent data accumulated |
| `startedAt` | datetime | |
| `endedAt` | datetime | Nullable |

Storage: Redis (TTL 2 hours) with async persistence to Postgres via Messenger.

**Domain Events**:
- `CallSessionStarted`
- `IntentDetected`
- `CallSessionEnded`

---

### 6. Identity Context

#### `AdminUser` (Aggregate Root)

| Field | Type | Notes |
|-------|------|-------|
| `id` | UUID | |
| `email` | string(255) | Unique, NOT NULL |
| `passwordHash` | string(255) | bcrypt |
| `restaurantId` | UUID | Scoped to one restaurant (v1) |
| `roles` | JSON | Array of role strings |
| `createdAt` | datetime | |

---

## Entity Relationship Diagram (logical, simplified)

```
Restaurant 1──* OpeningHour
Restaurant 1──* MenuCategory (cross-context ref)
MenuCategory 1──* MenuItem
Restaurant 1──* Order (cross-context ref)
Order 1──* OrderLine
MenuItem ──referenced by── OrderLine (snapshot, not FK)
Restaurant 1──* Reservation (cross-context ref)
Restaurant 1──* AdminUser (cross-context ref)
Restaurant 1──* CallSession (cross-context ref)
```

---

## Database Notes

- All primary keys are UUIDs (Doctrine `uuid` type, native Postgres `uuid`).
- Cross-context references store the UUID but do NOT use database foreign keys; integrity
  is maintained at the application layer (DDD rule: aggregates do not reference other
  aggregates by FK).
- Soft-deletion via `isActive`/`status` flags; no hard deletes in Domain layer.
- All `datetime` fields stored in UTC; timezone conversion is a presentation concern.
- Doctrine Migrations used for all schema changes; migrations MUST be reversible.
