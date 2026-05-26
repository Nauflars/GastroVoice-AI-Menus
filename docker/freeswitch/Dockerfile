# Pre-built FreeSWITCH with Lua, Sofia-SIP, dptools, etc.
FROM drachtio/drachtio-freeswitch-mrf:latest

RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Copy our minimal config over the defaults
COPY conf/ /etc/freeswitch/

# Copy Lua scripts
COPY scripts/ /usr/share/freeswitch/scripts/

RUN mkdir -p /var/log/freeswitch /var/run/freeswitch /tmp \
    && chown -R freeswitch:freeswitch /var/log/freeswitch /var/run/freeswitch 2>/dev/null || true

EXPOSE 5060/udp
EXPOSE 5060/tcp
EXPOSE 16384-32767/udp

CMD ["freeswitch", "-nonat", "-nf"]
