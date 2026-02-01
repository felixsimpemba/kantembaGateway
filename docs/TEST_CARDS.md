# Test Cards for Payment Gateway

## Overview

The Payment Gateway supports test card numbers to simulate different payment scenarios without real transactions.

---

## Successful Payments

### Visa

```
Card Number: 4242424242424242
Any CVC, any future expiry date
Result: Payment succeeds
```

### Mastercard

```
Card Number: 5555555555554444
Any CVC, any future expiry date
Result: Payment succeeds
```

---

## Failed Payments

### Card Declined

```
Card Number: 4000000000000002
Any CVC, any future expiry date
Result: card_declined
```

### Insufficient Funds

```
Card Number: 4000000000009995
Any CVC, any future expiry date
Result: insufficient_funds
```

### Expired Card

```
Card Number: 4000000000000069
Any CVC, any future expiry date
Result: expired_card
```

### Incorrect CVC

```
Card Number: 4000000000000127
Any CVC, any future expiry date
Result: incorrect_cvc
```

### Processing Error

```
Card Number: 4000000000000119
Any CVC, any future expiry date
Result: processing_error
```

---

## Complete Payment Flow Example

### Step 1: Initialize Payment

```bash
curl -X POST http://localhost:8000/api/payments/initialize \
  -H "Authorization: Bearer pk_test_B7OzBKZ799SNsSUai3RThejCGsyP3cYt" \ 
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Idempotency-Key: unique-$(date +%s)" \
  -d '{
    "amount": 100.00,
    "currency": "USD",
    "customer_email": "customer@example.com",
    "customer_name": "John Doe"
  }'
```

**Response:**

```json
{
  "message": "Payment initialized successfully",
  "payment": {
    "reference": "pay_xxxxxxxxxxxxxxxxxxxx",
    "amount": 100.00,
    "fee": 3.20,
    "net_amount": 96.80,
    "status": "initialized",
    ...
  }
}
```

### Step 2: Process Payment (Success)

```bash
curl -X POST http://localhost:8000/api/payments/process \
  -H "Authorization: Bearer pk_test_B7OzBKZ799SNsSUai3RThejCGsyP3cYt" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "payment_reference": "pay_YVwtf5wJtZQm3HuTl732C9t9",
    "card_number": "4242424242424242",
    "exp_month": "12",
    "exp_year": "25",
    "cvc": "123"
  }'
```

**Response (Success):**

```json
{
  "success": true,
  "payment": {
    "reference": "pay_xxxxxxxxxxxxxxxxxxxx",
    "status": "succeeded",
    "card_last4": "4242",
    "card_brand": "visa",
    ...
  },
  "message": "Payment processed successfully"
}
```

### Step 3: Verify Payment

```bash
curl -X GET http://localhost:8000/api/payments/pay_xxxxxxxxxxxxxxxxxxxx \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json"
```

### Step 4: Create Refund (Optional)

```bash
curl -X POST http://localhost:8000/api/refunds \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "payment_reference": "pay_xxxxxxxxxxxxxxxxxxxx",
    "amount": 50.00,
    "reason": "Customer requested partial refund"
  }'
```

### Step 5: View Transactions

```bash
curl -X GET "http://localhost:8000/api/transactions?limit=10" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json"
```

---

## Test Scenario: Failed Payment

```bash
# Initialize payment
RESPONSE=$(curl -s -X POST http://localhost:8000/api/payments/initialize \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"amount": 100.00, "currency": "USD"}')

PAYMENT_REF=$(echo $RESPONSE | grep -o '"reference":"[^"]*"' | cut -d'"' -f4)

# Process with declined card
curl -X POST http://localhost:8000/api/payments/process \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"payment_reference\": \"$PAYMENT_REF\",
    \"card_number\": \"4000000000000002\",
    \"exp_month\": \"12\",
    \"exp_year\": \"25\",
    \"cvc\": \"123\"
  }"
```

**Response (Failure):**

```json
{
  "success": false,
  "payment": {
    "reference": "pay_xxxxxxxxxxxxxxxxxxxx",
    "status": "failed",
    "failure_reason": "card_declined",
    ...
  },
  "error": "card_declined"
}
```

---

## Fee Calculation

**Formula:** `fee = (amount × 2.9%) + $0.30`

**Examples:**

- $10.00 payment → $0.59 fee → $9.41 net
- $100.00 payment → $3.20 fee → $96.80 net
- $1000.00 payment → $29.30 fee → $970.70 net

---

## Notes

1. **Luhn Validation**: All test cards pass Luhn algorithm validation
2. **Expiry**: Use any future date (e.g., 12/25)
3. **CVC**: Use any 3-4 digit number
4. **Idempotency**: Use unique `Idempotency-Key` headers for each request
5. **Refunds**: Can only refund succeeded payments, supports partial refunds
