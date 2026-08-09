#!/usr/bin/env bash
# Runs the full PHPUnit suite (unit + integration) in Docker.
# Spins up a throwaway Postgres instance for the integration tests and tears it down on exit.
set -euo pipefail

cd "$(dirname "$0")"

NETWORK="paperparrotpush-test-net-$$"
DB_CONTAINER="paperparrotpush-test-db-$$"
IMAGE_TAG="paperparrotpush-test"

cleanup() {
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker network create "$NETWORK" >/dev/null

echo "Starting test database..."
docker run -d --rm --name "$DB_CONTAINER" --network "$NETWORK" \
  -e POSTGRES_USER=test -e POSTGRES_PASSWORD=test -e POSTGRES_DB=pushusers \
  -v "$PWD/createTables.sql:/docker-entrypoint-initdb.d/db.sql:ro" \
  postgres:16 >/dev/null

until docker exec "$DB_CONTAINER" pg_isready -U test -d pushusers >/dev/null 2>&1; do
  sleep 1
done

echo "Building test image..."
docker build -f src/Dockerfile.test -t "$IMAGE_TAG" ./src >/dev/null

echo "Running tests..."
docker run --rm --network "$NETWORK" \
  -e POSTGRES_HOST="$DB_CONTAINER" -e POSTGRES_PORT=5432 -e POSTGRES_DB=pushusers \
  -e POSTGRES_USER=test -e POSTGRES_PASSWORD=test \
  "$IMAGE_TAG" "$@"
