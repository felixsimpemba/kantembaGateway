# Security Features Testing Guide

## Overview

The Payment Gateway API implements three advanced security features:

1. **Idempotency Keys** - Prevents duplicate requests
2. **Request Signatures** - HMAC verification for authenticity
3. **Secure Headers** - Timestamp validation and replay attack prevention

---

## 1. Idempotency Keys

### How It Works

- Caches successful POST responses for 24 hours
- Returns cached response if same key is used again
- Uses `Idempotency-Key` header

### Testing

**First Request:**

```bash
curl -X POST http://localhost:8000/api/auth/generate-key \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Idempotency-Key: unique-key-123" \
  -d '{"type":"test"}'
```

**Duplicate Request (should return cached response):**

```bash
curl -X POST http://localhost:8000/api/auth/generate-key \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Idempotency-Key: unique-key-123" \
  -d '{"type":"test"}'
```

Response will include header: `X-Idempotent-Replayed: true`

---

## 2. Request Signature Verification

### How It Works

- Uses HMAC-SHA256 to verify request authenticity
- Signature = HMAC(timestamp + method + path + body, webhook_secret)
- **Optional** - only validates if `X-Signature` header is present

### Generating Signature (PHP Example)

```php
$timestamp = time();
$method = 'POST';
$path = 'api/auth/generate-key';
$body = '{"type":"test"}';
$secret = 'YOUR_WEBHOOK_SECRET'; // From merchant record

$payload = $timestamp . $method . $path . $body;
$signature = hash_hmac('sha256', $payload, $secret);
```

### Testing

```bash
# Note: You need to calculate the signature dynamically
# This is typically done by your client SDK
curl -X POST http://localhost:8000/api/auth/generate-key \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Timestamp: $(date +%s)" \
  -H "X-Signature: CALCULATED_SIGNATURE" \
  -d '{"type":"test"}'
```

---

## 3. Secure Headers Validation

### How It Works

- Validates `X-Timestamp` header (must be within 5 minutes)
- Checks for required headers: `Accept`, `Content-Type`
- Prevents replay attacks

### Testing

**Valid Request:**

```bash
curl -X POST http://localhost:8000/api/auth/generate-key \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Timestamp: $(date +%s)" \
  -d '{"type":"test"}'
```

**Invalid Request (old timestamp):**

```bash
# This will fail - timestamp too old
curl -X POST http://localhost:8000/api/auth/generate-key \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Timestamp: 1234567890" \
  -d '{"type":"test"}'
```

Expected error:

```json
{
  "error": "Request expired",
  "message": "Request timestamp is too old or too far in the future. Please ensure your system clock is synchronized."
}
```

---

## Combined Example

All three features working together:

```bash
# Bash script to test all security features
API_KEY="pk_test_FIaIkHRiogKJ5J4GjovkzYes9EubCfms"
TIMESTAMP=$(date +%s)
IDEMPOTENCY_KEY="test-$(date +%s)"

curl -X POST http://localhost:8000/api/auth/generate-key \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Timestamp: $TIMESTAMP" \
  -H "Idempotency-Key: $IDEMPOTENCY_KEY" \
  -d '{"type":"test"}' \
  -v
```

---

## Error Responses

### Missing Accept Header

```json
{
  "error": "Missing required headers",
  "message": "The following headers are required: Accept"
}
```

### Invalid Idempotency Key Format

```json
{
  "error": "Invalid idempotency key format",
  "message": "Idempotency key must contain only alphanumeric characters, dashes, and underscores"
}
```

### Invalid Signature

```json
{
  "error": "Invalid signature",
  "message": "Request signature verification failed"
}
```

---

## Notes

1. **Signature verification is OPTIONAL** - Only validates if `X-Signature` header is present
2. **Idempotency is automatic** - Works for all POST requests when `Idempotency-Key` header is provided
3. **Timestamp validation** - Automatically enforced for POST/PUT/DELETE/PATCH requests if `X-Timestamp` is provided
4. **Cache storage** - Idempotency uses Laravel's cache (database by default)
