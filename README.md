# Chat Backend

A RESTful chat backend built with PHP 8.2+, Slim 4, and SQLite. Supports user registration, group creation, group membership, and cursor-paginated group messaging.

---

## Table of Contents

- [Project Overview](#project-overview)
- [Architecture & Decisions](#architecture--decisions)
- [Domain Scenarios](#domain-scenarios)
- [Setup & Running](#setup--running)
- [Running Tests](#running-tests)
- [API Reference](#api-reference)
- [Security Considerations](#security-considerations)

---

## Project Overview

| Item | Detail |
|------|--------|
| Language | PHP 8.2+ |
| Framework | Slim 4 |
| Database | SQLite |
| DI Container | PHP-DI 7.1 |
| Token library | ramsey/uuid |
| Test framework | PHPUnit 11 |

The application exposes a JSON HTTP API. One endpoint is public (user registration). All other endpoints require a bearer-style `X-User-Token` header resolved by authentication middleware.

---

## Architecture & Decisions

### Layered Structure

```
HTTP Request
    ↓
AuthMiddleware          (token → User entity, 401 on failure)
    ↓
Controller              (parse request, call service, return response)
    ↓
Service                 (all business logic and membership checks)
    ↓
Repository              (prepared SQL statements, entity hydration)
    ↓
PDO / SQLite
```

Layer boundaries are strict: controllers never touch PDO, repositories never throw HTTP exceptions, middleware never checks group membership. Domain exceptions thrown in the service layer (`NotFoundException`, `ForbiddenException`, `ValidationException`) are caught by the error handler and mapped to the appropriate HTTP status code before the response is written.

### Key Decisions

**No interfaces, no generic repositories.**
The constraints favour simplicity over abstraction. Concrete classes are injected directly via PHP-DI autowiring, which is sufficient for a single-database backend.

**Immutable domain entities.**
All four domain entities (`User`, `Group`, `Membership`, `Message`) use PHP 8.2 `readonly` properties and `DateTimeImmutable`. There are no setters. This prevents accidental mutation anywhere in the call stack.

**Timestamps are always `CURRENT_TIMESTAMP` from the database.**
PHP never generates insertion timestamps. This avoids clock-skew issues and keeps time handling consistent.

**UUID tokens, not passwords.**
User registration returns a randomly generated UUID v4 token (via `ramsey/uuid`). The token is the sole credential. There is no login endpoint and no password hashing.

**Username snapshot on messages.**
`messages.username_snapshot` stores the sender's username at the time of posting. If a user is deleted, historical messages retain their display name.

**Cursor-based pagination (`after_id`).**
Messages are fetched in ascending order with `id > after_id`. Offset-based pagination degrades on large tables; cursor-based does not. Default limit is 50, hard cap is 100.

**SQLite for portability.**
A single `.sqlite` file requires zero external services, making the project trivial to run and test locally. SQLite uses database-level write locking, which is acceptable for a single-process development server but would be a bottleneck under concurrent write load. Swapping to PostgreSQL or MySQL would require only the PDO DSN and a schema translation — no application code changes.

**In-memory SQLite for tests.**
Integration tests boot the full application against an in-memory `:memory:` database seeded from `database/schema.sql`. Each test class gets a fresh schema with no shared state between test cases.

---

## Domain Scenarios

### 1. User Registration

A client POSTs a username. The backend generates a UUID token and stores both. The token is returned once and must be kept by the client — there is no way to retrieve it again.

### 2. Group Creation

An authenticated user creates a group by name. The creator is automatically added as a member via a `group_user` row inserted within the same service call. Both writes (group insert and membership insert) are intentionally kept as separate statements: SQLite auto-commits each statement and, because the membership insert references the group by the last-insert ID returned immediately before it, the window for partial failure is negligible in a single-process context. In a production RDBMS under concurrent load, these two writes would be wrapped in an explicit transaction to guarantee atomicity.

### 3. Joining a Group

An authenticated user sends a join request for an existing group. The service checks the group exists (404 otherwise) and inserts a membership row if not already present. Calling this endpoint when already a member is a no-op — the existing row is preserved and `204` is returned, making the operation safe to retry.

### 4. Sending a Message

The sender must be a member of the target group. The service enforces this check (403 if not a member, 404 if the group does not exist). The username at send-time is snapshotted into the message row.

### 5. Listing Messages

Only members can read messages (403 for non-members). Results are returned oldest-first. Clients pass the `id` of the last received message as `after_id` to fetch the next page. Any `limit` value above 100 is silently clamped to 100; values below 1 are treated as the default of 50.

---

## Setup & Running

### Prerequisites

- PHP 8.2 or higher (with the `pdo_sqlite` and `json` extensions enabled)
- Composer

### 1. Install dependencies

```bash
composer install
```

### 2. Run the database migration

```bash
sqlite3 database/database.sqlite < database/schema.sql
```

This creates all four tables (`users`, `groups`, `group_user`, `messages`) inside `database/database.sqlite`.

### 3. Start the development server

```bash
php -S localhost:8080 -t public/
```

The API is now available at `http://localhost:8080`.

---

## Running Tests

Tests use an in-memory SQLite database; no external services or a running server are needed.

### Run all tests

```bash
./vendor/bin/phpunit
```

### Run only unit tests

```bash
./vendor/bin/phpunit --testsuite Unit
```

### Run only integration tests

```bash
./vendor/bin/phpunit --testsuite Integration
```

### Test coverage summary

| Suite | Files | What is covered |
|-------|-------|-----------------|
| Unit — Services | 3 | Business logic, exception paths, limit capping |
| Integration — Repositories | 4 | SQL correctness, constraints, pagination queries |
| Integration — HTTP Endpoints | 3 | Full request/response cycle, auth, error codes |

---

## API Reference

All request and response bodies are JSON. Protected endpoints require the header:

```
X-User-Token: <token>
```

---

### POST /users

Register a new user. No authentication required.

**Request body**

```json
{ "username": "alice" }
```

**Response `201 Created`**

```json
{
  "id": 1,
  "username": "alice",
  "token": "550e8400-e29b-41d4-a716-446655440000",
  "created_at": "2026-03-01T10:00:00+00:00"
}
```

**Errors**

| Code | Reason |
|------|--------|
| 400 | `username` missing or empty |

---

### POST /groups

Create a new group. The authenticated user is automatically added as a member.

**Request body**

```json
{ "name": "general" }
```

**Response `201 Created`**

```json
{
  "id": 1,
  "name": "general",
  "created_at": "2026-03-01T10:00:00+00:00"
}
```

**Errors**

| Code | Reason |
|------|--------|
| 400 | `name` missing or empty |
| 401 | Invalid or missing token |

---

### POST /groups/{id}/join

Join an existing group.

**Response `204 No Content`**

**Errors**

| Code | Reason |
|------|--------|
| 401 | Invalid or missing token |
| 404 | Group not found |

---

### POST /groups/{id}/messages

Send a message to a group. The sender must be a member.

**Request body**

```json
{ "message_text": "Hello, world!" }
```

**Response `201 Created`**

```json
{
  "id": 1,
  "group_id": 1,
  "user_id": 1,
  "username_snapshot": "alice",
  "message_text": "Hello, world!",
  "created_at": "2026-03-01T10:00:00+00:00"
}
```

**Errors**

| Code | Reason |
|------|--------|
| 400 | `message_text` missing or empty |
| 401 | Invalid or missing token |
| 403 | Authenticated user is not a member of the group |
| 404 | Group not found |

---

### GET /groups/{id}/messages

List messages in a group in ascending order. The requester must be a member.

**Query parameters**

| Parameter | Type | Default | Maximum | Description |
|-----------|------|---------|---------|-------------|
| `after_id` | integer | — | — | Return only messages with `id` greater than this value |
| `limit` | integer | 50 | 100 | Number of messages to return. Values above 100 are clamped; values below 1 fall back to 50. |

**Response `200 OK`**

```json
[
  {
    "id": 1,
    "group_id": 1,
    "user_id": 1,
    "username_snapshot": "alice",
    "message_text": "Hello, world!",
    "created_at": "2026-03-01T10:00:00+00:00"
  }
]
```

**Errors**

| Code | Reason |
|------|--------|
| 401 | Invalid or missing token |
| 403 | Authenticated user is not a member of the group |
| 404 | Group not found |

---

### Error response format

All error responses share the same shape:

```json
{ "error": "human-readable message" }
```

---

## Security Considerations

**Prepared statements throughout.**
Every repository method uses PDO prepared statements. User-supplied values are never interpolated into SQL strings, eliminating SQL injection at the data access layer.

**Token confidentiality.**
UUID tokens are returned only once, at registration. They are stored in plain text in the database — acceptable for a take-home scope, but a production system should store a hashed form (e.g. `hash('sha256', $token)`) and compare on lookup. As long as database access is restricted to the application process, plain-text storage carries limited risk; the concern arises if database backups or read replicas are accessible to parties outside the application.

**No sensitive data in error responses.**
The error handler returns a generic `{"error": "..."}` message for unhandled exceptions, ensuring stack traces and internal paths are never exposed to the client.

**Foreign key enforcement.**
The SQLite connection enables `PRAGMA foreign_keys = ON` at connection time. This ensures referential integrity (e.g. a message cannot reference a non-existent group) is enforced at the database level, not only in application code.
