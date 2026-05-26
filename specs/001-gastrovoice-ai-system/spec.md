# Feature Specification: GastroVoice AI — Integral Restaurant Management System

**Feature Branch**: `feat/001-gastrovoice-ai-system`

**Created**: 2026-05-25

**Status**: Draft

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Restaurant Administrator Sets Up the System (Priority: P1)

A restaurant owner accesses the web administration panel, creates their restaurant profile,
configures opening hours, table capacity, and defines initial menu categories.

**Why this priority**: Without the core restaurant setup, no other feature can function. This
is the foundation on which every other user journey depends.

**Independent Test**: Can be fully tested by logging in, creating a restaurant, configuring
hours/capacity, adding at least one menu category, and verifying all data persists correctly.

**Acceptance Scenarios**:

1. **Given** a user with valid credentials, **When** they log in, **Then** they access the
   administration dashboard with their restaurant's data.
2. **Given** an authenticated administrator, **When** they save opening hours and seating
   capacity, **Then** the configuration is persisted and reflected in subsequent API responses.
3. **Given** an administrator, **When** they create a menu category, **Then** the category
   appears in the menu management section and is available for product assignment.

---

### User Story 2 — Menu Management via Manual Entry (Priority: P2)

An administrator manually creates, edits and deletes products (menu items) assigned to
categories, including name, description, price and availability.

**Why this priority**: The digital menu is the central catalogue used by both the web panel
and the AI voice assistant to answer customer queries and take orders.

**Independent Test**: Can be tested end-to-end by creating a category, adding multiple
products, editing one, deactivating another, and verifying the public menu API reflects
the changes.

**Acceptance Scenarios**:

1. **Given** an existing category, **When** an administrator adds a product with name,
   description and price, **Then** the product appears under that category.
2. **Given** an existing product, **When** the administrator marks it as unavailable, **Then**
   it no longer appears in the customer-facing menu.
3. **Given** an existing product, **When** the administrator updates its price, **Then** new
   orders reflect the updated price.

---

### User Story 3 — Automatic Menu Import from Image (Priority: P2)

An administrator uploads one or more images of a physical menu. The system analyses the
image using a multimodal AI model, extracts categories, product names, descriptions and
prices, and automatically populates the digital menu.

**Why this priority**: This dramatically reduces onboarding time for new restaurants and is
a key differentiator of the product.

**Independent Test**: Testable by uploading a clear menu image and verifying that the
resulting categories and products in the database match the content visible in the image
with reasonable accuracy.

**Acceptance Scenarios**:

1. **Given** an authenticated administrator, **When** they upload a menu image, **Then** the
   system returns a structured preview of detected categories and products.
2. **Given** a confirmed preview, **When** the administrator accepts it, **Then** the
   categories and products are persisted in the database.
3. **Given** an image the AI cannot interpret, **When** processing fails, **Then** the system
   informs the administrator with a clear error and no partial data is saved.

---

### User Story 4 — Takeaway Order Management (Priority: P2)

A customer (or a staff member on their behalf) creates a takeaway order selecting products
from the menu, providing a pickup time and contact details. Administrators can view and
manage the order lifecycle from the web panel.

**Why this priority**: Takeaway order management is a direct revenue-generating feature and
can operate independently of reservations and voice features.

**Independent Test**: Testable by creating an order via API, verifying it appears in the
admin panel, updating its status through the full lifecycle, and confirming final state.

**Acceptance Scenarios**:

1. **Given** an available menu, **When** a customer submits an order with products and pickup
   time, **Then** the order is created with status "pending" and a unique identifier.
2. **Given** a pending order, **When** an administrator marks it as "ready", **Then** the
   order status updates and the customer can be notified.
3. **Given** an order, **When** the pickup time passes without collection, **Then** the system
   flags the order for administrator review.

---

### User Story 5 — Reservation Management with Capacity Validation (Priority: P2)

A customer (or voice assistant) requests a reservation for a given date, time and number
of people. The system validates availability against existing reservations and restaurant
capacity before confirming or rejecting the request.

**Why this priority**: Reservation validation is a core business rule that protects the
restaurant from overbooking and is a prerequisite for the voice assistant feature.

**Independent Test**: Testable by creating multiple reservations up to the capacity limit
and verifying that an additional request for the same slot is rejected automatically.

**Acceptance Scenarios**:

1. **Given** a configured restaurant with capacity for 20 people at 13:00, **When** a
   reservation for 4 people is requested at 13:00 with 16 already booked, **Then** the
   reservation is confirmed.
2. **Given** the same scenario with 20 already booked, **When** a reservation for 2 people
   is requested, **Then** the system rejects it with a clear unavailability message.
3. **Given** a confirmed reservation, **When** a customer cancels it, **Then** the freed
   capacity becomes available for new reservations.

---

### User Story 6 — Voice Assistant Handles Incoming Calls (Priority: P3)

A customer calls the restaurant's phone number. The AI voice assistant answers, understands
the customer's intent (reservation, order, availability query), collects the required
information through a multi-turn conversation and executes the corresponding action.

**Why this priority**: The voice assistant is the flagship AI feature, but it depends on
all previous layers (menu, orders, reservations) being operational first.

**Independent Test**: Testable via the HTTP simulation mode: sending plain-text conversation
turns to the backend and verifying that the correct intents are detected, the right actions
are executed, and coherent natural-language responses are returned for each turn.

**Acceptance Scenarios**:

1. **Given** an active call, **When** a customer says they want to make a reservation for two
   people tomorrow at 20:00, **Then** the assistant confirms availability and asks for the
   customer's name.
2. **Given** an ongoing reservation conversation with missing data, **When** the customer
   provides the missing field, **Then** the assistant continues without re-asking previous
   fields.
3. **Given** a customer asking for the day's menu, **When** the assistant retrieves the
   active menu, **Then** it reads out the categories and available products in natural
   language.
4. **Given** a call in HTTP simulation mode, **When** a POST request containing a text
   message is sent, **Then** the system processes it as if it were spoken audio and returns
   a text response.

---

### Edge Cases

- What happens when a menu image contains handwritten text or poor-quality photos?
- How does the system handle a reservation request that spans two opening-hour slots?
- What happens if the AI model returns a malformed JSON response during menu parsing or
  voice intent detection?
- How does the system handle a customer who abandons a voice call mid-conversation?
- What happens if two simultaneous voice calls create reservations for the last available
  slot (race condition)?
- How does the system behave when the OpenAI API is unavailable?

---

## Requirements *(mandatory)*

### Functional Requirements

**Authentication & Administration**
- **FR-001**: The system MUST allow administrators to authenticate securely with
  email and password.
- **FR-002**: All administration panel actions MUST be protected; unauthenticated requests
  MUST be rejected.

**Restaurant Configuration**
- **FR-003**: The system MUST allow an administrator to configure opening hours per day
  of the week (open/close times, closed days).
- **FR-004**: The system MUST allow an administrator to define total seating capacity.
- **FR-005**: The system MUST expose the restaurant configuration via a read API for
  use by the voice assistant.

**Menu Management**
- **FR-006**: The system MUST allow creation, update, and soft-deletion of menu categories.
- **FR-007**: The system MUST allow creation, update, and soft-deletion of menu items
  (name, description, price, availability flag, category assignment).
- **FR-008**: The system MUST expose a public read endpoint returning the current active menu
  structured by categories and items.
- **FR-009**: The system MUST accept one or more menu images uploaded by an administrator.
- **FR-010**: The system MUST send uploaded images to a multimodal AI model with a structured
  extraction prompt and receive a JSON response containing categories, product names,
  descriptions and prices.
- **FR-011**: The system MUST present the AI-extracted menu to the administrator for
  confirmation before persisting any data.
- **FR-012**: Upon confirmation, the system MUST persist the extracted categories and items,
  avoiding duplicates by matching on category name and product name.

**Takeaway Order Management**
- **FR-013**: The system MUST allow creating a takeaway order composed of one or more
  menu items with quantities, a pickup time and customer contact details.
- **FR-014**: Orders MUST follow a defined status lifecycle:
  `pending → confirmed → preparing → ready → collected` (or `cancelled`).
- **FR-015**: The administration panel MUST display all orders filterable by status and date.
- **FR-016**: The system MUST prevent orders containing items that are currently unavailable.

**Reservation Management**
- **FR-017**: The system MUST allow creating a reservation with date, time, number of
  people and customer details.
- **FR-018**: Before confirming a reservation, the system MUST validate that:
  (a) the requested date/time falls within opening hours,
  (b) the sum of people for all confirmed reservations in the same slot plus the new
  request does not exceed total capacity.
- **FR-019**: The system MUST allow cancelling a reservation, returning the capacity to
  the pool.
- **FR-020**: The administration panel MUST display all reservations filterable by date and
  status.

**Voice Assistant**
- **FR-021**: The system MUST accept an audio stream from an Asterisk AGI/AMI integration
  and convert it to text using a speech-to-text service.
- **FR-022**: The system MUST send the transcribed text to an LLM with a structured prompt
  that identifies the user's intent and extracts relevant entities (date, time, number of
  people, product names, customer name, phone number).
- **FR-023**: The LLM response MUST be a strict JSON object with keys `intent` and `data`.
- **FR-024**: The system MUST maintain conversation state per call session so that
  multi-turn interactions accumulate context.
- **FR-025**: Based on the detected intent the system MUST execute the corresponding
  business action (create reservation, query availability, create order, read menu).
- **FR-026**: The system MUST convert the natural-language reply to audio using a
  text-to-speech service and return it to Asterisk.
- **FR-027**: The system MUST provide an HTTP simulation endpoint that accepts plain text
  and returns a plain-text response, bypassing audio conversion, to enable testing without
  real telephony infrastructure.

**Infrastructure**
- **FR-028**: The system MUST be fully runnable locally using Docker Compose, including
  backend, database (PostgreSQL), and Asterisk services.
- **FR-029**: All inter-service communication MUST use documented, versioned API contracts
  (OpenAPI 3.x).

### Key Entities

- **Restaurant**: Represents the establishment. Attributes: name, address, phone, total
  seating capacity, opening hours per weekday.
- **MenuCategory**: Groups menu items. Attributes: name, display order, active flag.
  Belongs to a Restaurant.
- **MenuItem**: A product offered by the restaurant. Attributes: name, description, price,
  available flag. Belongs to a MenuCategory.
- **Order** (takeaway): Attributes: status, pickup time, customer name, customer phone,
  total amount. Contains one or more OrderLines.
- **OrderLine**: Attributes: menu item reference, quantity, unit price at time of order.
- **Reservation**: Attributes: date, time slot, number of people, customer name, customer
  phone/email, status (pending/confirmed/cancelled).
- **Customer**: Denormalised contact details embedded in Order and Reservation (no separate
  account required for customers in v1).
- **CallSession**: Tracks in-progress voice conversations. Attributes: call ID, channel
  state, conversation history (array of turns), current intent context.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator can complete full restaurant setup (config + menu with at
  least 5 items) in under 10 minutes from first login.
- **SC-002**: Menu extraction from a clear menu image produces a correctly structured
  preview in under 30 seconds.
- **SC-003**: Reservation availability validation completes in under 500 ms for any date/
  time query.
- **SC-004**: The voice assistant correctly identifies the user's intent in at least 90%
  of well-formed utterances during acceptance testing.
- **SC-005**: A complete reservation booking via voice (from greeting to confirmation)
  requires no more than 5 conversation turns for a happy path.
- **SC-006**: All backend Domain and Application layers maintain at least 80% automated
  test coverage.
- **SC-007**: The local Docker Compose stack starts all services and is ready to accept
  requests within 2 minutes on a standard developer machine.
- **SC-008**: No reservation is confirmed that would exceed the restaurant's configured
  capacity (0% overbooking rate).

---

## Assumptions

- A single restaurant instance is managed per deployment in v1; multi-tenancy is out of scope.
- Customer-facing online booking or ordering (without admin involvement) is out of scope for
  v1; the public API is primarily consumed by the voice assistant and optionally by a
  future customer-facing app.
- The OpenAI API (gpt-4.1 / gpt-4.1-mini or equivalent multimodal model) is accessible
  via a configured API key in the environment; costs are accepted by the operator.
- Whisper (OpenAI) or an equivalent speech-to-text service is used for audio transcription;
  the specific provider may be swapped via a Port interface without core logic changes.
- A TTS (text-to-speech) provider is available via API; the specific provider is an
  infrastructure concern and interchangeable.
- Asterisk is used as the telephony middleware for local development; production telephony
  integration (SIP trunks, cloud PBX) is a future concern.
- Reservations use fixed time slots aligned to the restaurant's opening hours; dynamic
  slot configuration (e.g., every 15 minutes) is a v2 enhancement.
- Authentication uses standard JWT-based session tokens; OAuth2/SSO is out of scope for v1.
- Email or SMS notifications for customers are out of scope for v1 (status is communicated
  verbally via voice assistant or manually by staff).
- The system is designed for a single timezone (the restaurant's local timezone); multi-
  timezone support is out of scope.
