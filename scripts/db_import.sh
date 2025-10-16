#!/usr/bin/env bash
set -euo pipefail

# Simple helper to import database/create_database.sql into a MySQL instance

DB_HOST=${DB_HOST:-localhost}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-}
DB_NAME=${DB_NAME:-live_streaming_booking}
SQL_FILE=${SQL_FILE:-database/create_database.sql}

if [[ ! -f "$SQL_FILE" ]]; then
  echo "SQL file not found: $SQL_FILE" >&2
  exit 1
fi

echo "Importing $SQL_FILE into $DB_USER@$DB_HOST ($DB_NAME)"
mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p$DB_PASS} < "$SQL_FILE"
echo "Done."

