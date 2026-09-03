---
paths:
  - 'database/migrations/**/*.php'
---

# Migrations

## Store identifiers as native UUID columns
Application UUID identifiers use `$table->uuid()` / `foreignUuid()`, not `binary(16)` or `ulid()`. Do not add AsBinary casts or custom belongsToMany subclasses for these keys. Send private ids and browser_db_id are UUIDs (uuid7 for send ids).
