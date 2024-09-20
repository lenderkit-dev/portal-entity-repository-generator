#!/usr/bin/env bash

HOST_DOMAIN="host.docker.internal"
ping -q -c1 $HOST_DOMAIN >/dev/null 2>&1
if [ $? -ne 0 ]; then
  HOST_IP=$(/sbin/ip route | awk '/default/ { print $3 }')
  echo -e "$HOST_IP\t$HOST_DOMAIN" >>/etc/hosts
  echo "Defined host ip ${HOST_IP} as 'host.docker.internal'"
fi

exec "$@"
