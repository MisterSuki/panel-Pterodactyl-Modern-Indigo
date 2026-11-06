#!/bin/bash
# The same entrypoint as the official Pterodactyl images: it runs the startup command of the egg.
cd /home/container || exit 1

INTERNAL_IP=$(ip route get 1 2>/dev/null | awk '{print $(NF-2);exit}')
export INTERNAL_IP

MODIFIED_STARTUP=$(echo -e "${STARTUP}" | sed -e 's/{{/${/g' -e 's/}}/}/g')
echo -e ":/home/container$ ${MODIFIED_STARTUP}"

eval "${MODIFIED_STARTUP}"
