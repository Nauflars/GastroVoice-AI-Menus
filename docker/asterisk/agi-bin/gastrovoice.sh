#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# GastroVoice AGI — Traditional mode (Audio → Whisper STT → gpt-4.1-mini → TTS → Audio)
#
# Record caller speech → POST WAV to /api/voice/call → play back MP3 response
# Loops up to MAX_TURNS or until AI returns intent=goodbye / end_call.
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── AGI protocol helpers ────────────────────────────────────────────────────

agi_read() {
  while IFS= read -r line; do
    line="${line%%$'\r'}"            # strip CR
    [ -z "$line" ] && break         # blank line = end of headers
  done
}

agi_cmd() {
  echo "$1"
  read -r result                    # "200 result=…"
  echo "$result" >&2
}

# ── Config ──────────────────────────────────────────────────────────────────

API_URL="${GASTROVOICE_API:-http://backend:8080}"
RESTAURANT_ID="${RESTAURANT_ID:-a1b2c3d4-e5f6-7890-abcd-ef1234567890}"
MAX_TURNS=10
SESSION_ID=""
TMP_DIR="/tmp/gv_$$"
mkdir -p "$TMP_DIR"

# ── Read AGI environment ────────────────────────────────────────────────────

agi_read
CALLER_ID="${agi_callerid:-unknown}"

# ── Main conversation loop ──────────────────────────────────────────────────

for turn in $(seq 1 "$MAX_TURNS"); do

  REC_FILE="$TMP_DIR/rec_${turn}"
  RESP_FILE="$TMP_DIR/resp_${turn}.mp3"
  RESP_WAV="$TMP_DIR/resp_${turn}.wav"

  # 1. Record caller speech (up to 15 s, 3 s silence, beep=false)
  agi_cmd "RECORD FILE ${REC_FILE} wav \"\" 15000 0 BEEP s=3"

  # 2. Build curl multipart POST
  SID_FLAG=""
  if [ -n "$SESSION_ID" ]; then
    SID_FLAG="-F sessionId=${SESSION_ID}"
  fi

  HTTP_CODE=$(curl -s -w '%{http_code}' -o "$RESP_FILE" \
    -X POST "${API_URL}/api/voice/call" \
    -F "restaurantId=${RESTAURANT_ID}" \
    -F "callerId=${CALLER_ID}" \
    ${SID_FLAG} \
    -F "audioFile=@${REC_FILE}.wav;type=audio/wav" \
    -D "$TMP_DIR/headers_${turn}.txt" \
  )

  if [ "$HTTP_CODE" != "200" ]; then
    echo "VERBOSE \"Backend returned HTTP $HTTP_CODE on turn $turn\" 1" >&2
    break
  fi

  # 3. Parse response headers
  HDRS="$TMP_DIR/headers_${turn}.txt"
  NEW_SID=$(grep -ioP '(?<=x-session-id:\s).+' "$HDRS" 2>/dev/null | tr -d '\r\n' || true)
  INTENT=$(grep -ioP '(?<=x-intent:\s).+' "$HDRS" 2>/dev/null | tr -d '\r\n' || true)

  [ -n "$NEW_SID" ] && SESSION_ID="$NEW_SID"

  # 4. Convert MP3 → WAV (8 kHz mono slin) and play back
  sox "$RESP_FILE" -r 8000 -c 1 -t wav "$RESP_WAV" 2>/dev/null \
    || ffmpeg -y -i "$RESP_FILE" -ar 8000 -ac 1 -f wav "$RESP_WAV" 2>/dev/null

  agi_cmd "STREAM FILE ${RESP_WAV%.wav} \"\""

  # 5. Check for conversation end
  if [ "$INTENT" = "goodbye" ] || [ "$INTENT" = "end_call" ]; then
    break
  fi
done

# ── Cleanup ─────────────────────────────────────────────────────────────────

rm -rf "$TMP_DIR"
agi_cmd "HANGUP"
