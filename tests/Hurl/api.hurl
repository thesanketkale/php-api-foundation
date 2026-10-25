# PHP API Foundation Hurl End-to-End Test Suite
# Tests: Health probes, User registration, Login, Refresh token rotation,
# Token family reuse revocation, Notes CRUD, and ETag optimistic locking.

# 1. Health Probe Liveness
GET http://localhost:8080/v1/health
HTTP 200
[Asserts]
jsonpath "$.status" == "pass"

# 2. Health Probe Readiness (Database Connectivity)
GET http://localhost:8080/v1/health/ready
HTTP 200
[Asserts]
jsonpath "$.status" == "pass"
jsonpath "$.database" == "connected"

# 3. User Registration
POST http://localhost:8080/v1/auth/register
Content-Type: application/json
{
  "email": "hurl-user@example.com",
  "password": "HurlSecurePassword123!"
}
HTTP 201
[Asserts]
jsonpath "$.data.email" == "hurl-user@example.com"
[Captures]
user_id: jsonpath "$.data.id"

# 4. User Login
POST http://localhost:8080/v1/auth/login
Content-Type: application/json
{
  "email": "hurl-user@example.com",
  "password": "HurlSecurePassword123!"
}
HTTP 200
[Asserts]
jsonpath "$.data.token_type" == "Bearer"
[Captures]
access_token: jsonpath "$.data.access_token"
refresh_token_1: jsonpath "$.data.refresh_token"

# 5. Fetch Profile (Authenticated)
GET http://localhost:8080/v1/me
Authorization: Bearer {{access_token}}
HTTP 200
[Asserts]
jsonpath "$.data.id" == "{{user_id}}"
jsonpath "$.data.email" == "hurl-user@example.com"

# 6. Create Note
POST http://localhost:8080/v1/notes
Authorization: Bearer {{access_token}}
Content-Type: application/json
{
  "title": "Hurl Automated E2E Note",
  "content": "Testing notes API with optimistic concurrency control."
}
HTTP 201
[Asserts]
header "Location" exists
header "ETag" exists
jsonpath "$.data.version" == 1
[Captures]
note_id: jsonpath "$.data.id"
initial_etag: header "ETag"

# 7. Get Note by ID
GET http://localhost:8080/v1/notes/{{note_id}}
Authorization: Bearer {{access_token}}
HTTP 200
[Asserts]
header "ETag" == "{{initial_etag}}"
jsonpath "$.data.title" == "Hurl Automated E2E Note"

# 8. Update Note without If-Match (Fails with 412)
PUT http://localhost:8080/v1/notes/{{note_id}}
Authorization: Bearer {{access_token}}
Content-Type: application/json
{
  "title": "Should Fail",
  "content": "Missing If-Match header"
}
HTTP 412
[Asserts]
jsonpath "$.status" == 412
jsonpath "$.title" == "Precondition Required"

# 9. Update Note with Valid If-Match (Succeeds with 200 & version 2)
PUT http://localhost:8080/v1/notes/{{note_id}}
Authorization: Bearer {{access_token}}
If-Match: {{initial_etag}}
Content-Type: application/json
{
  "title": "Hurl Note Updated Successfully",
  "content": "Content updated with verified ETag matching."
}
HTTP 200
[Asserts]
header "ETag" != "{{initial_etag}}"
jsonpath "$.data.version" == 2
jsonpath "$.data.title" == "Hurl Note Updated Successfully"
[Captures]
updated_etag: header "ETag"

# 10. List Notes with Pagination
GET http://localhost:8080/v1/notes?page=1&per_page=10
Authorization: Bearer {{access_token}}
HTTP 200
[Asserts]
jsonpath "$.data" count >= 1
jsonpath "$.meta.current_page" == 1

# 11. Delete Note
DELETE http://localhost:8080/v1/notes/{{note_id}}
Authorization: Bearer {{access_token}}
HTTP 204

# 12. Verify Note Deleted (404)
GET http://localhost:8080/v1/notes/{{note_id}}
Authorization: Bearer {{access_token}}
HTTP 404

# 13. Refresh Token Rotation (Consumes refresh_token_1, yields refresh_token_2)
POST http://localhost:8080/v1/auth/refresh
Content-Type: application/json
{
  "refresh_token": "{{refresh_token_1}}"
}
HTTP 200
[Asserts]
jsonpath "$.data.token_type" == "Bearer"
[Captures]
refresh_token_2: jsonpath "$.data.refresh_token"

# 14. Token Reuse Revocation Attack (Re-present consumed refresh_token_1)
POST http://localhost:8080/v1/auth/refresh
Content-Type: application/json
{
  "refresh_token": "{{refresh_token_1}}"
}
HTTP 401
[Asserts]
jsonpath "$.code" == "TOKEN_REUSE_REVOKED"

# 15. Verify Family Revocation (refresh_token_2 is now also revoked!)
POST http://localhost:8080/v1/auth/refresh
Content-Type: application/json
{
  "refresh_token": "{{refresh_token_2}}"
}
HTTP 401
