FROM andrius/asterisk:latest AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    curl \
    sox \
    lame \
    ffmpeg \
    && rm -rf /var/lib/apt/lists/*

COPY docker/asterisk/asterisk.conf /etc/asterisk/asterisk.conf
COPY docker/asterisk/pjsip.conf /etc/asterisk/pjsip.conf
COPY docker/asterisk/extensions.conf /etc/asterisk/extensions.conf
COPY docker/asterisk/queues.conf /etc/asterisk/queues.conf
COPY docker/asterisk/agi-bin/ /var/lib/asterisk/agi-bin/

RUN chmod +x /var/lib/asterisk/agi-bin/*.sh 2>/dev/null || true

EXPOSE 5060/udp
EXPOSE 10000-20000/udp

CMD ["asterisk", "-f", "-vvvg"]
