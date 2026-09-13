#!/bin/sh
set -e

mkdir -p /app/var/cache /app/var/log /app/var/data

exec "$@"
