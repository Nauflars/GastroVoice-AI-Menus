-- gastrovoice.lua
-- Full AI voice conversation loop for GastroVoice
-- Flow: record → POST audio to backend → play MP3 response → repeat

local api_url    = session:getVariable("GASTROVOICE_API") or "http://backend:8080"
local rest_id    = session:getVariable("RESTAURANT_ID")   or "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
local caller_id  = session:getVariable("caller_id_number") or "unknown"
local session_id = ""
local max_turns  = 10

-- ── helpers ──────────────────────────────────────────────────────────────────

local function read_file(path)
    local f = io.open(path, "r")
    if not f then return "" end
    local s = f:read("*a")
    f:close()
    return s
end

local function trim(s)
    return (s or ""):match("^%s*(.-)%s*$")
end

local function convert_mp3_to_wav(mp3, wav)
    -- Use ffmpeg: resample to 8 kHz mono signed-16-bit PCM (Asterisk/FS native)
    local cmd = string.format(
        'ffmpeg -y -i "%s" -ar 8000 -ac 1 -f wav "%s" >/dev/null 2>&1',
        mp3, wav
    )
    return os.execute(cmd)
end

-- ── main ─────────────────────────────────────────────────────────────────────

freeswitch.consoleLog("INFO", string.format(
    "[GastroVoice] Call started – caller=%s restaurant=%s\n", caller_id, rest_id
))

for turn = 1, max_turns do

    if not session:ready() then
        freeswitch.consoleLog("INFO", "[GastroVoice] Session ended by caller\n")
        break
    end

    local rec  = string.format("/tmp/gv_rec_%d.wav",   turn)
    local mp3  = string.format("/tmp/gv_resp_%d.mp3",  turn)
    local wav  = string.format("/tmp/gv_resp_%d.wav",  turn)
    local hdrs = string.format("/tmp/gv_hdrs_%d.txt",  turn)
    local code = string.format("/tmp/gv_code_%d.txt",  turn)

    -- 1. Record caller speech
    --    record <file> [<max_secs>] [<silence_threshold_ms>] [<silence_hits>]
    --    silence_hits × 20 ms = silence duration; here 150 × 20ms = 3 s silence
    session:execute("record", rec .. " 20 200 150")

    if not session:ready() then break end

    -- 2. POST audio to backend
    local sid_flag = ""
    if session_id ~= "" then
        sid_flag = string.format(' -F "sessionId=%s"', session_id)
    end

    local curl_cmd = string.format(
        'curl -s -X POST "%s/api/voice/call"'
        .. ' -F "restaurantId=%s"'
        .. ' -F "callerId=%s"'
        .. '%s'
        .. ' -F "audioFile=@%s;type=audio/wav"'
        .. ' -o "%s"'
        .. ' -D "%s"'
        .. ' -w "%%{http_code}"'
        .. ' > "%s" 2>/dev/null',
        api_url, rest_id, caller_id, sid_flag, rec, mp3, hdrs, code
    )

    os.execute(curl_cmd)

    local http_code = trim(read_file(code))
    if http_code ~= "200" then
        freeswitch.consoleLog("ERR", string.format(
            "[GastroVoice] Backend returned HTTP %s on turn %d\n", http_code, turn
        ))
        break
    end

    -- 3. Parse response headers
    local headers = read_file(hdrs)

    local new_sid = headers:match("[Xx]%-[Ss]ession%-[Ii]d:%s*([^\r\n]+)")
    if new_sid then session_id = trim(new_sid) end

    local intent = headers:match("[Xx]%-[Ii]ntent:%s*([^\r\n]+)")
    if intent then intent = trim(intent) end

    local transcript = headers:match("[Xx]%-[Tt]ranscript:%s*([^\r\n]+)")

    freeswitch.consoleLog("INFO", string.format(
        "[GastroVoice] turn=%d intent=%s transcript=%s\n",
        turn, intent or "?", transcript or "?"
    ))

    -- 4. Convert MP3 → WAV and play
    if convert_mp3_to_wav(mp3, wav) then
        session:execute("playback", wav)
    else
        freeswitch.consoleLog("ERR", "[GastroVoice] ffmpeg conversion failed\n")
        break
    end

    -- 5. End conversation on goodbye
    if intent == "goodbye" or intent == "end_call" then
        freeswitch.consoleLog("INFO", "[GastroVoice] Conversation ended by AI\n")
        break
    end

    -- Clean up tmp files for this turn
    os.remove(rec)
    os.remove(mp3)
    os.remove(wav)
    os.remove(hdrs)
    os.remove(code)
end

freeswitch.consoleLog("INFO", "[GastroVoice] Hanging up\n")
session:hangup()
