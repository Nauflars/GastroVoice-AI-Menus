# Quickstart: GastroVoice AI — Local Development

**Date**: 2026-05-25

## Prerequisites

- Docker Desktop ≥ 4.x (or Docker Engine + Compose plugin)
- Node.js 20 LTS (for frontend development outside Docker)
- PHP 8.3 + Composer (optional, for running backend commands outside Docker)
- A SIP softphone: [Zoiper](https://www.zoiper.com/) or [Linphone](https://www.linphone.org/)

---

## 1. Clone & Configure Environment

```bash
git clone <repository-url> gastrovoice-ai
cd gastrovoice-ai
cp .env.example .env
```

Edit `.env` and fill in:
```
OPENAI_API_KEY=sk-...
POSTGRES_PASSWORD=gastrovoice
JWT_SECRET=change-this-secret-in-production
```

---

## 2. Start All Services

```bash
docker compose up -d
```

Services started:

| Service | URL / Port |
|---------|-----------|
| Backend API (Nginx + PHP-FPM) | http://localhost:8080/api |
| Frontend (Vite dev server) | http://localhost:5173 |
| PostgreSQL | localhost:5432 |
| Redis | localhost:6379 |
| Asterisk (SIP) | UDP 5060, 10000-20000 RTP |
| Mailpit (email capture) | http://localhost:8025 |

---

## 3. Initialize the Database

```bash
# Run Doctrine migrations
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

# Create the initial admin user
docker compose exec backend php bin/console app:create-admin --email=admin@example.com --password=admin123
```

---

## 4. Verify the API

```bash
# Login
curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"admin123"}' | jq .

# Save the token
TOKEN=<access_token_from_above>

# Get restaurant configuration
curl -s http://localhost:8080/api/restaurant \
  -H "Authorization: Bearer $TOKEN" | jq .

# Get public menu (no auth)
curl -s http://localhost:8080/api/menu | jq .
```

---

## 5. Verify the Frontend

Open http://localhost:5173 in your browser. Log in with `admin@example.com` / `admin123`.

---

## 6. Test the Voice Assistant (HTTP Simulation — no SIP needed)

```bash
# Start a simulated call session
curl -s -X POST http://localhost:8080/api/voice/simulate \
  -H 'Content-Type: application/json' \
  -d '{
    "sessionId": "test-session-001",
    "message": "Hola, quiero hacer una reserva para 2 personas mañana a las 20:00"
  }' | jq .

# Continue the conversation (same sessionId)
curl -s -X POST http://localhost:8080/api/voice/simulate \
  -H 'Content-Type: application/json' \
  -d '{
    "sessionId": "test-session-001",
    "message": "Me llamo Juan García, mi teléfono es 612345678"
  }' | jq .
```

---

## 7. Test with a SIP Softphone (Asterisk integration)

1. Open Zoiper or Linphone.
2. Create a SIP account:
   - **Domain**: `localhost` (or your Docker host IP)
   - **Username**: `1001`
   - **Password**: `1234`
   - **Port**: `5060`
3. Call extension `9000` — this triggers the GastroVoice AI voice assistant dialplan.
4. Speak your request; the assistant will respond via TTS audio.

---

## 8. Run the Test Suite

```bash
# Backend: all tests
docker compose exec backend php bin/phpunit

# Backend: unit tests only
docker compose exec backend php bin/phpunit --testsuite Unit

# Backend: functional tests only
docker compose exec backend php bin/phpunit --testsuite Functional

# Backend: static analysis
docker compose exec backend vendor/bin/phpstan analyse --level=8

# Backend: code style
docker compose exec backend vendor/bin/php-cs-fixer fix --dry-run --diff

# Frontend: unit + integration tests
docker compose exec frontend npm run test

# Frontend: lint
docker compose exec frontend npm run lint

# E2E tests (requires full stack running)
docker compose exec frontend npx playwright test
```

---

## 9. Upload a Menu Image (AI Import)

```bash
# Upload image for AI extraction
curl -s -X POST http://localhost:8080/api/menu/import \
  -H "Authorization: Bearer $TOKEN" \
  -F "images[]=@/path/to/menu-photo.jpg" | jq .

# Note the previewId from the response, then confirm:
curl -s -X POST http://localhost:8080/api/menu/import/<previewId>/confirm \
  -H "Authorization: Bearer $TOKEN" | jq .
```

---

## 10. Common Troubleshooting

| Issue | Fix |
|-------|-----|
| Port 5060 already in use | Stop local Asterisk instance: `sudo systemctl stop asterisk` |
| DB migration fails | Check `POSTGRES_PASSWORD` matches in `.env` and `docker-compose.yml` |
| OpenAI API errors | Verify `OPENAI_API_KEY` is set and has sufficient quota |
| Frontend cannot reach API | Ensure `VITE_API_URL=http://localhost:8080/api` is set in `.env` |
