#!/bin/sh
set -e

mkdir -p /app/var/cache /app/var/log

exec "$@"
