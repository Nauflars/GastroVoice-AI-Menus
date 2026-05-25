#!/bin/bash
# GastroVoice AGI script — relays call to Symfony HTTP endpoint

CALLER_ID="$1"
API_URL="${GASTROVOICE_API:-http://backend:8080}"

curl -s -X POST "${API_URL}/api/voice/call" \
  -H "Content-Type: application/json" \
  -d "{\"callerId\": \"${CALLER_ID}\"}"
