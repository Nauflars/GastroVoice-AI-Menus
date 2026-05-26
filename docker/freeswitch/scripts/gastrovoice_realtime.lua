-- gastrovoice_realtime.lua
-- Realtime mode: Audio → gpt-4o-mini-realtime → Audio
--
-- Uses mod_audio_fork to stream bidirectional audio to the media-bridge
-- WebSocket server, which in turn connects to the OpenAI Realtime API.
--
-- The bridge handles:
--   • Audio resampling (8 kHz ↔ 24 kHz)
--   • Tool calls (query_menu, create_reservation, create_order, etc.)
--   • Conversation state

local bridge_ws = session:getVariable("media_bridge_ws")
                  or os.getenv("MEDIA_BRIDGE_WS")
                  or "ws://media-bridge:8765"

local uuid = session:get_uuid()

freeswitch.consoleLog("INFO", string.format(
    "[GastroVoice-RT] Starting realtime session — uuid=%s bridge=%s\n",
    uuid, bridge_ws
))

-- ── Start bidirectional audio fork to the media-bridge WebSocket ────────

-- mod_audio_fork: fork_audio_start <uuid> <ws_url> mono 8000
-- This forks both the caller's audio (send) and the channel's playback
-- (receive) to the WebSocket in PCM16 mono at 8 kHz.
local api = freeswitch.API()
local fork_result = api:execute(
    "uuid_audio_fork",
    string.format("%s start %s mono 8000", uuid, bridge_ws)
)

freeswitch.consoleLog("INFO", string.format(
    "[GastroVoice-RT] uuid_audio_fork result: %s\n", fork_result or "nil"
))

-- ── Keep the call alive while the bridge handles the conversation ───────

-- We park the call in a silence loop. The audio bridge does all the work.
-- We listen for DTMF '#' to let the caller hang up manually.
-- The call also ends if the bridge closes the WebSocket (FreeSWITCH detects
-- the socket close and mod_audio_fork stops).

local max_duration = 600  -- 10 min max call (configurable)

-- Play silence and wait — mod_audio_fork runs in background
while session:ready() do
    -- Wait 1 second at a time, check if call is still up
    session:execute("sleep", "1000")

    max_duration = max_duration - 1
    if max_duration <= 0 then
        freeswitch.consoleLog("INFO",
            "[GastroVoice-RT] Max call duration reached, hanging up\n")
        break
    end
end

-- ── Stop audio fork and clean up ────────────────────────────────────────

api:execute("uuid_audio_fork", string.format("%s stop", uuid))

freeswitch.consoleLog("INFO", string.format(
    "[GastroVoice-RT] Session ended — uuid=%s\n", uuid
))

session:hangup()
