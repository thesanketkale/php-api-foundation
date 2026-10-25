# REST API Specification Reference

This document provides a concise reference for all endpoints exposed by `php-api-foundation`. The formal contract is maintained in [`openapi/openapi.yaml`](../openapi/openapi.yaml) (OpenAPI 3.1).

---

## 1. Authentication Endpoints

### `POST /v1/auth/register`
Creates a new user account and returns an initial JWT access/refresh token pair.
- **Request Body**:
  ```json
  {
    "email": "user@example.com",
    "password": "Password123!",
    "name": "Jane User"
  }
  ```
- **Responses**:
  - `201 Created`: User profile and token pair.
  - `409 Conflict`: Email address is already registered.
  - `422 Unprocessable Content`: Validation failure.

---

### `POST /v1/auth/login`
Authenticates user credentials and issues a new token pair and token family.
- **Request Body**:
  ```json
  {
    "email": "user@example.com",
    "password": "Password123!"
  }
  ```
- **Responses**:
  - `200 OK`: User profile and token pair.
  - `401 Unauthorized`: Invalid email or password.

---

### `POST /v1/auth/refresh`
Rotates the refresh token and returns a new access token and new refresh token.
- **Request Body**:
  ```json
  {
    "refresh_token": "raw-refresh-token-string"
  }
  ```
- **Responses**:
  - `200 OK`: New token pair.
  - `401 Unauthorized`: Token invalid, expired, or compromised (token reuse detected).

---

### `POST /v1/auth/logout`
Revokes the refresh token and its session family.
- **Headers**: `Authorization: Bearer <ACCESS_TOKEN>`
- **Request Body**:
  ```json
  {
    "refresh_token": "raw-refresh-token-string"
  }
  ```
- **Responses**:
  - `204 No Content`: Successfully logged out.
  - `401 Unauthorized`: Missing or invalid Bearer token.

---

## 2. User Profile Endpoint

### `GET /v1/me`
Retrieves authenticated user information.
- **Headers**: `Authorization: Bearer <ACCESS_TOKEN>`
- **Responses**:
  - `200 OK`: User entity object (`id`, `email`, `name`, `created_at`).
  - `401 Unauthorized`: Token expired or invalid.

---

## 3. Notes CRUD Endpoints

All notes endpoints require `Authorization: Bearer <ACCESS_TOKEN>`.

### `GET /v1/notes`
Lists notes belonging to the authenticated user with pagination and optional keyword search.
- **Query Parameters**:
  - `page` (optional int, default: `1`): Page number.
  - `per_page` (optional int, default: `15`, max: `100`): Items per page.
  - `search` (optional string): Substring search in note title or content.
- **Response (`200 OK`)**: Array of note objects wrapped in `data` with `meta.pagination`.

---

### `POST /v1/notes`
Creates a new note for the authenticated user.
- **Request Body**:
  ```json
  {
    "title": "Quarterly Objectives",
    "content": "Finalize Q4 API architecture roadmap."
  }
  ```
- **Response (`201 Created`)**: Created note object.

---

### `GET /v1/notes/{id}`
Retrieves a specific note by UUID.
- **Responses**:
  - `200 OK`: Note object.
  - `404 Not Found`: Note does not exist or belongs to another user.

---

### `PUT /v1/notes/{id}`
Updates an existing note.
- **Request Body**:
  ```json
  {
    "title": "Updated Title",
    "content": "Updated content text."
  }
  ```
- **Responses**:
  - `200 OK`: Updated note object.
  - `404 Not Found`: Note does not exist or belongs to another user.

---

### `DELETE /v1/notes/{id}`
Permanently deletes a note.
- **Responses**:
  - `204 No Content`: Note successfully deleted.
  - `404 Not Found`: Note does not exist or belongs to another user.

---

## 4. Health Check Endpoints

### `GET /v1/health`
Lightweight liveness probe checking container or process availability.
- **Response (`200 OK`)**:
  ```json
  {
    "status": "healthy",
    "timestamp": 1730000000,
    "environment": "production",
    "uptime_seconds": 1234
  }
  ```

---

### `GET /v1/health/ready`
Readiness probe verifying database connectivity and essential write access.
- **Responses**:
  - `200 OK`: Database connected and ready to accept traffic.
  - `503 Service Unavailable`: Database unreachable or write failure.
