# Phase 3: Token System & Payment - TODO Checklist

## Task 3.1: Token Bundle Management

### Subtask 3.1.1: Create TokenBundleController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/TokenBundleController.php` (new file)
- `app/Http/Requests/Api/V1/StoreTokenBundleRequest.php` (new - admin only)
- `app/Http/Requests/Api/V1/UpdateTokenBundleRequest.php` (new - admin only)
- `routes/api.php` (add routes)

**Database Reads/Writes:**
- Read: `token_bundles` table (SELECT active bundles)
- Read: `currency_rates` table (get latest rate for price calculation)
- Write: `token_bundles` table (admin CRUD operations)

**External Dependencies:**
- CurrencyRate model (for rate lookup)
- Redis cache (for rate caching, optional optimization)

**Idempotency Requirements:**
- Bundle listing is idempotent (read-only, no side effects)
- Bundle creation is idempotent (name uniqueness check prevents duplicates)
- Bundle update is idempotent (same data = same result)

**Acceptance Criteria:**
- Public endpoint lists active bundles with real-time Toman prices
- Admin endpoints allow CRUD operations
- Prices calculated server-side (never from client)
- Response includes: id, name, token_amount, bonus_tokens, total_tokens, price_usd, price_toman, is_active

**Failure Modes and Rollback:**
- Rate fetch failure: Use fallback rate (last known), log warning
- Bundle not found: Return 404
- Validation failure: Return 422 with errors
- No rollback needed (read operations)

---

### Subtask 3.1.2: Implement index() - List All Active Bundles
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/TokenBundleController.php` (implement index method)

**Database Reads/Writes:**
- Read: `token_bundles` WHERE is_active = true
- Read: `currency_rates` (latest rate for price calculation)

**External Dependencies:**
- CurrencyRate::getLatestUsdToTomanRate() (or service method)
- Redis cache (optional, for rate caching)

**Idempotency Requirements:**
- Read-only operation, fully idempotent

**Acceptance Criteria:**
- Returns all active bundles ordered by display_order
- Each bundle includes calculated price_toman (price_usd * dollar_rate)
- Response includes total_tokens (token_amount + bonus_tokens)
- HTTP 200 on success

**Failure Modes and Rollback:**
- Rate unavailable: Use fallback rate, log warning, continue
- No active bundles: Return empty array (not error)

---

### Subtask 3.1.3: Calculate Real-Time Prices in Toman
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/TokenBundleController.php` (price calculation logic)
- `app/Services/CurrencyRateService.php` (new - optional service for rate management)

**Database Reads/Writes:**
- Read: `currency_rates` (latest rate)
- Read: Redis cache (rate cache, optional)

**External Dependencies:**
- CurrencyRate model
- Redis (for caching)

**Idempotency Requirements:**
- Price calculation is deterministic (same inputs = same output)

**Acceptance Criteria:**
- Price calculation: `price_toman = price_usd * dollar_rate`
- Rate fetched from cache (5-min TTL) or database (fallback)
- If no rate available, use last known rate (never block purchase)
- Prices calculated for each bundle in response

**Failure Modes and Rollback:**
- Rate fetch failure: Use last known rate, log error, continue
- Cache failure: Fallback to database, continue
- No rate in database: Return error (should not happen if scraper running)

---

### Subtask 3.1.4: Add Admin CRUD Endpoints (Protected)
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/TokenBundleController.php` (implement store, update, destroy)
- `app/Http/Requests/Api/V1/StoreTokenBundleRequest.php` (validation)
- `app/Http/Requests/Api/V1/UpdateTokenBundleRequest.php` (validation)
- `routes/api.php` (add admin routes)

**Database Reads/Writes:**
- Write: `token_bundles` (INSERT, UPDATE, DELETE)
- Read: `token_bundles` (for update/destroy operations)

**External Dependencies:**
- Admin middleware (already exists)

**Idempotency Requirements:**
- Store: Name uniqueness prevents duplicates
- Update: Same data = same result (idempotent)
- Destroy: Safe to call multiple times (check exists before delete)

**Acceptance Criteria:**
- POST /admin/tokens/bundles creates bundle
- PUT /admin/tokens/bundles/{id} updates bundle
- DELETE /admin/tokens/bundles/{id} deletes bundle (soft delete preferred)
- All endpoints require admin authentication
- Validation enforces: name (required, unique), token_amount (required, integer, min:1), price_usd (required, decimal, min:0), bonus_tokens (optional, integer, min:0)

**Failure Modes and Rollback:**
- Validation failure: Return 422, no database changes
- Unauthorized: Return 401
- Bundle not found: Return 404
- Database error: Transaction rollback, return 500

---

### Subtask 3.1.5: Add Bundle Activation/Deactivation
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/TokenBundleController.php` (implement activate/deactivate methods)
- `routes/api.php` (add activation routes)

**Database Reads/Writes:**
- Write: `token_bundles` (UPDATE is_active field)

**External Dependencies:**
- None

**Idempotency Requirements:**
- Activate/deactivate is idempotent (same state = no change)

**Acceptance Criteria:**
- POST /admin/tokens/bundles/{id}/activate sets is_active = true
- POST /admin/tokens/bundles/{id}/deactivate sets is_active = false
- Returns updated bundle
- HTTP 200 on success, 404 if bundle not found

**Failure Modes and Rollback:**
- Bundle not found: Return 404
- Already in desired state: Return 200 (idempotent, no error)

**Test Coverage Expectations:**
- Feature test: List bundles with real-time prices
- Feature test: Admin can create/update/delete bundles
- Feature test: Activation/deactivation works
- Feature test: Price calculation uses current rate
- Feature test: Fallback rate used when scraping fails

---

## Task 3.2: Currency Rate Scraper

### Subtask 3.2.1: Create TgjuScraperService Class
**Files to Create/Modify:**
- `app/Services/TgjuScraperService.php` (new file)

**Database Reads/Writes:**
- Write: `currency_rates` (INSERT new rate record)
- Read: `currency_rates` (get last known rate for fallback)

**External Dependencies:**
- HTTP client (Laravel HTTP facade) for scraping TGJU.org
- DOM parser (optional, for HTML parsing)
- Redis (for caching)

**Idempotency Requirements:**
- Rate fetching is idempotent (multiple fetches create separate records, no harm)

**Acceptance Criteria:**
- Service class exists with proper namespace
- Methods: `fetchUsdRate(): ?float`, `getCurrentRate(): float`
- Error handling for network failures
- Logging of fetch attempts and results

**Failure Modes and Rollback:**
- Network failure: Return null, log error, use fallback
- HTML parsing failure: Return null, log error, use fallback
- No rollback needed (read-only external operation)

---

### Subtask 3.2.2: Implement HTML Scraping for TGJU.org
**Files to Create/Modify:**
- `app/Services/TgjuScraperService.php` (implement scraping logic)

**Database Reads/Writes:**
- None (scraping only, no database writes yet)

**External Dependencies:**
- TGJU.org website (HTTP GET request)
- HTML parser (regex or DOM parser)

**Idempotency Requirements:**
- Scraping is idempotent (multiple requests = same result if rate unchanged)

**Acceptance Criteria:**
- HTTP GET request to TGJU.org
- Extract USD to Rials rate from HTML
- Handle HTML structure changes gracefully
- Return rate as float or null on failure

**Failure Modes and Rollback:**
- Network timeout: Return null, log error
- HTML structure changed: Return null, log error, alert admin
- Rate not found in HTML: Return null, log error

---

### Subtask 3.2.3: Extract USD to Rials Rate
**Files to Create/Modify:**
- `app/Services/TgjuScraperService.php` (rate extraction logic)

**Database Reads/Writes:**
- None

**External Dependencies:**
- HTML content from TGJU.org

**Idempotency Requirements:**
- Rate extraction is deterministic (same HTML = same rate)

**Acceptance Criteria:**
- Extract numeric rate from HTML (USD to Rials)
- Handle different HTML formats
- Validate extracted rate (must be positive number)
- Return rate as float

**Failure Modes and Rollback:**
- Rate not found: Return null
- Invalid format: Return null, log error
- Negative rate: Return null, log error (data corruption)

---

### Subtask 3.2.4: Convert Rials to Toman (Divide by 10)
**Files to Create/Modify:**
- `app/Services/TgjuScraperService.php` (conversion logic)

**Database Reads/Writes:**
- None

**External Dependencies:**
- None

**Idempotency Requirements:**
- Conversion is deterministic (same input = same output)

**Acceptance Criteria:**
- Convert Rials to Toman: `toman_rate = rials_rate / 10`
- Return Toman rate as float
- Precision: 2 decimal places

**Failure Modes and Rollback:**
- Division by zero: Should not happen (Rials rate always positive)
- Invalid input: Return null

---

### Subtask 3.2.5: Store Rate in Database
**Files to Create/Modify:**
- `app/Services/TgjuScraperService.php` (database storage logic)

**Database Reads/Writes:**
- Write: `currency_rates` (INSERT new rate record)

**External Dependencies:**
- CurrencyRate model

**Idempotency Requirements:**
- Multiple fetches create separate records (append-only, no updates)

**Acceptance Criteria:**
- Create new CurrencyRate record with: currency_from='USD', currency_to='IRR', rate (Rials), source='tgju', fetched_at=now()
- Never update existing records (always insert)
- Return stored rate

**Failure Modes and Rollback:**
- Database error: Log error, return null, use fallback
- Transaction rollback if needed

---

### Subtask 3.2.6: Cache Rate in Redis (5-Minute TTL)
**Files to Create/Modify:**
- `app/Services/TgjuScraperService.php` (Redis caching logic)

**Database Reads/Writes:**
- Read: Redis cache (get cached rate)
- Write: Redis cache (set cached rate)

**External Dependencies:**
- Redis (Laravel Cache facade)

**Idempotency Requirements:**
- Cache operations are idempotent (same key = same value)

**Acceptance Criteria:**
- Cache key: `currency_rate:usd_toman`
- TTL: 5 minutes (300 seconds)
- Store Toman rate in cache
- Return cached rate if available and not expired

**Failure Modes and Rollback:**
- Cache failure: Fallback to database, log warning, continue
- Cache miss: Fetch from database, cache result

---

### Subtask 3.2.7: Create Scheduled Job (Every 5 Minutes)
**Files to Create/Modify:**
- `routes/console.php` (add scheduled job)

**Database Reads/Writes:**
- Write: `currency_rates` (via service method)

**External Dependencies:**
- TgjuScraperService
- Laravel scheduler

**Idempotency Requirements:**
- Scheduled job is idempotent (multiple runs create separate records)

**Acceptance Criteria:**
- Job runs every 5 minutes
- Calls TgjuScraperService::fetchUsdRate()
- Logs success/failure
- Does not block if scraping fails

**Failure Modes and Rollback:**
- Scraping failure: Log error, use fallback rate, continue
- Service unavailable: Log error, retry next run

---

### Subtask 3.2.8: Implement Fallback to Last Known Rate
**Files to Create/Modify:**
- `app/Services/TgjuScraperService.php` (fallback logic)

**Database Reads/Writes:**
- Read: `currency_rates` (get latest rate)

**External Dependencies:**
- CurrencyRate model

**Idempotency Requirements:**
- Fallback is deterministic (always returns same last known rate)

**Acceptance Criteria:**
- If scraping fails, return last known rate from database
- If no rate in database, return null (should not happen)
- Log fallback usage
- Never block purchases due to rate unavailability

**Failure Modes and Rollback:**
- No rate in database: Return null, log critical error
- Database error: Return null, log error

---

### Subtask 3.2.9: Add Error Handling and Logging
**Files to Create/Modify:**
- `app/Services/TgjuScraperService.php` (error handling throughout)

**Database Reads/Writes:**
- None (logging only)

**External Dependencies:**
- Laravel Log facade

**Idempotency Requirements:**
- Logging is idempotent (same error = same log)

**Acceptance Criteria:**
- Log all scraping attempts (success/failure)
- Log rate extraction failures
- Log cache failures
- Log fallback usage
- Never log sensitive data

**Failure Modes and Rollback:**
- Logging failure: Should not block rate fetching (logging is non-critical)

---

### Subtask 3.2.10: Create API Endpoint to Get Current Rate
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/CurrencyController.php` (new file)
- `routes/api.php` (add route)

**Database Reads/Writes:**
- Read: Redis cache or `currency_rates` (get current rate)

**External Dependencies:**
- TgjuScraperService or CurrencyRate model

**Idempotency Requirements:**
- Read-only operation, fully idempotent

**Acceptance Criteria:**
- GET /api/v1/currency/rate returns current USD to Toman rate
- Response includes: rate, rate_in_toman, fetched_at, source
- HTTP 200 on success
- Public endpoint (no auth required, rate is public info)

**Failure Modes and Rollback:**
- Rate unavailable: Return 503 with message, log error

**Test Coverage Expectations:**
- Feature test: Scraper fetches rate from TGJU.org (mocked HTML)
- Feature test: Rate conversion (Rials to Toman)
- Feature test: Rate storage in database
- Feature test: Rate caching in Redis
- Feature test: Fallback to last known rate
- Feature test: Scheduled job runs correctly
- Feature test: API endpoint returns current rate

---

## Task 3.3: Zarinpal Payment Integration

### Subtask 3.3.1: Install or Create Zarinpal SDK
**Files to Create/Modify:**
- `composer.json` (if using package) OR
- `app/Services/ZarinpalService.php` (create SDK wrapper)

**Database Reads/Writes:**
- None (SDK only)

**External Dependencies:**
- Zarinpal API (payment gateway)
- HTTP client (for API calls)

**Idempotency Requirements:**
- SDK methods should be idempotent (retry-safe)

**Acceptance Criteria:**
- ZarinpalService class exists
- Methods: `requestPayment()`, `verifyPayment()`
- Handles Zarinpal API authentication
- Error handling for API failures

**Failure Modes and Rollback:**
- API failure: Return error, log, order remains pending
- Network failure: Return error, log, order remains pending

---

### Subtask 3.3.2: Create ZarinpalService Class
**Files to Create/Modify:**
- `app/Services/ZarinpalService.php` (new file)

**Database Reads/Writes:**
- None (service only, no direct DB access)

**External Dependencies:**
- Zarinpal API endpoints
- Config: ZARINPAL_MERCHANT_ID, ZARINPAL_SANDBOX

**Idempotency Requirements:**
- Payment request is idempotent (same order = same authority)
- Payment verification is idempotent (same authority = same result)

**Acceptance Criteria:**
- Service class with proper namespace
- Constructor loads config from services.php
- Methods handle API authentication
- Error handling and logging

**Failure Modes and Rollback:**
- Config missing: Throw exception
- API error: Return error array, log error

---

### Subtask 3.3.3: Implement Payment Request Method
**Files to Create/Modify:**
- `app/Services/ZarinpalService.php` (implement requestPayment method)

**Database Reads/Writes:**
- None (service only)

**External Dependencies:**
- Zarinpal Payment Request API

**Idempotency Requirements:**
- Same order should return same authority (if order already has authority, return existing)

**Acceptance Criteria:**
- Method signature: `requestPayment(Order $order): array`
- Calls Zarinpal Payment Request API
- Returns: ['authority' => string, 'payment_url' => string] or error
- Handles API errors gracefully
- Logs payment requests (without sensitive data)

**Failure Modes and Rollback:**
- API failure: Return error, log, order remains pending
- Network timeout: Return error, log, order remains pending

---

### Subtask 3.3.4: Implement Payment Verification Method
**Files to Create/Modify:**
- `app/Services/ZarinpalService.php` (implement verifyPayment method)

**Database Reads/Writes:**
- None (service only)

**External Dependencies:**
- Zarinpal Payment Verification API

**Idempotency Requirements:**
- Same authority should return same verification result (idempotent)

**Acceptance Criteria:**
- Method signature: `verifyPayment(string $authority, int $amount): array`
- Calls Zarinpal Payment Verification API
- Returns: ['status' => string, 'ref_id' => string] or error
- Verifies amount matches order amount
- Handles API errors gracefully
- Logs verification attempts

**Failure Modes and Rollback:**
- API failure: Return error, log, order remains pending
- Amount mismatch: Return error, log, order remains pending
- Invalid authority: Return error, log

---

### Subtask 3.3.5: Create OrderController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/OrderController.php` (new file)
- `app/Http/Requests/Api/V1/PurchaseTokenRequest.php` (new - validation)

**Database Reads/Writes:**
- Write: `orders` (INSERT new order)
- Read: `token_bundles` (get bundle details)
- Read: `currency_rates` (get current rate)

**External Dependencies:**
- ZarinpalService
- CurrencyRateService (or model)

**Idempotency Requirements:**
- Purchase request is idempotent (same bundle + user = can create multiple orders, but each order is unique)

**Acceptance Criteria:**
- Controller in Api/V1 namespace
- Uses Form Request for validation
- Thin controller (business logic in services)

**Failure Modes and Rollback:**
- Validation failure: Return 422, no database changes
- Unauthorized: Return 401

---

### Subtask 3.3.6: Implement purchase() - Create Order and Payment Request
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/OrderController.php` (implement purchase method)

**Database Reads/Writes:**
- Read: `token_bundles` (get bundle)
- Read: `currency_rates` (get current rate)
- Write: `orders` (INSERT new order with pending status)

**External Dependencies:**
- ZarinpalService::requestPayment()
- CurrencyRateService::getCurrentRate()

**Idempotency Requirements:**
- Multiple purchase requests create separate orders (idempotent at order level)
- Order creation is atomic (transaction ensures consistency)

**Acceptance Criteria:**
- Validates bundle_id (required, exists, is_active)
- Gets current USD to Toman rate
- Calculates price_toman = price_usd * dollar_rate
- Creates order with: user_id, token_bundle_id, amount_tokens, price_toman, price_usd, dollar_rate, status='pending'
- Calls ZarinpalService::requestPayment()
- Stores zarinpal_authority in order
- Returns payment URL to user
- HTTP 200 on success, 422 on validation error, 400 if bundle inactive

**Failure Modes and Rollback:**
- Bundle not found: Return 404
- Bundle inactive: Return 400
- Rate unavailable: Use fallback rate, log warning, continue
- Payment request failure: Order created but status remains pending, return 500
- Database error: Transaction rollback, return 500

---

### Subtask 3.3.7: Implement callback() - Handle Zarinpal Callback
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/OrderController.php` (implement callback method)

**Database Reads/Writes:**
- Read: `orders` (find by zarinpal_authority)
- Write: `orders` (UPDATE status to 'paid', set zarinpal_ref_id, paid_at)
- Write: `users` (UPDATE tokens_balance)
- Write: `token_transactions` (INSERT purchase transaction)

**External Dependencies:**
- ZarinpalService::verifyPayment()
- Database transaction (for atomicity)

**Idempotency Requirements:**
- Callback processing is idempotent (same authority processed twice = no double credit)
- Order status check prevents duplicate processing
- zarinpal_ref_id uniqueness prevents duplicate credits

**Acceptance Criteria:**
- Validates authority and status from Zarinpal callback
- Finds order by zarinpal_authority
- Checks order status (must be 'pending', reject if already paid/failed)
- Calls ZarinpalService::verifyPayment() (server-to-server)
- Verifies amount matches order amount
- Updates order: status='paid', zarinpal_ref_id, paid_at=now()
- Credits tokens: user.tokens_balance += order.amount_tokens
- Creates transaction: type='purchase', amount_tokens=order.amount_tokens, order_id=order.id
- All operations in database transaction (atomic)
- Returns success response or redirects to frontend
- HTTP 200 on success, 400 if already processed, 404 if order not found

**Failure Modes and Rollback:**
- Order not found: Return 404
- Order already paid: Return 400 (idempotent, no error)
- Verification failure: Order remains pending, return 400
- Amount mismatch: Order remains pending, return 400
- Database error: Transaction rollback, order remains pending, return 500
- Token credit failure: Transaction rollback, order remains pending, return 500

---

### Subtask 3.3.8: Calculate Price in Toman Using Current Rate
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/OrderController.php` (price calculation in purchase method)

**Database Reads/Writes:**
- Read: `currency_rates` (get current rate)

**External Dependencies:**
- CurrencyRateService::getCurrentRate()

**Idempotency Requirements:**
- Price calculation is deterministic (same inputs = same output)

**Acceptance Criteria:**
- Gets current USD to Toman rate (from cache or database)
- Calculates: price_toman = bundle.price_usd * dollar_rate
- Stores dollar_rate in order (snapshot for audit)
- If rate unavailable, uses fallback rate (never blocks purchase)

**Failure Modes and Rollback:**
- Rate unavailable: Use fallback rate, log warning, continue
- Invalid rate: Use fallback rate, log error, continue

---

### Subtask 3.3.9: Create Order with Pending Status
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/OrderController.php` (order creation in purchase method)

**Database Reads/Writes:**
- Write: `orders` (INSERT)

**External Dependencies:**
- Order model
- Database transaction

**Idempotency Requirements:**
- Order creation is idempotent (same bundle + user can create multiple orders)

**Acceptance Criteria:**
- Creates order with all required fields
- Status = 'pending'
- Stores zarinpal_authority (unique constraint prevents duplicates)
- Returns order ID

**Failure Modes and Rollback:**
- Duplicate authority: Database unique constraint prevents, return 500
- Validation failure: Return 422, no order created
- Database error: Transaction rollback, return 500

---

### Subtask 3.3.10: Credit Tokens on Successful Payment
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/OrderController.php` (token credit in callback method)

**Database Reads/Writes:**
- Write: `users` (UPDATE tokens_balance)
- Write: `token_transactions` (INSERT purchase transaction)

**External Dependencies:**
- Database transaction (for atomicity)

**Idempotency Requirements:**
- Token credit is idempotent (order status check prevents double credit)
- Transaction creation is idempotent (order_id check prevents duplicates)

**Acceptance Criteria:**
- Credits tokens: user.tokens_balance += order.amount_tokens (includes bonus)
- Creates transaction: type='purchase', amount_tokens=order.amount_tokens, order_id=order.id
- Both operations in single database transaction
- Only credits if order status was 'pending' (prevents double credit)

**Failure Modes and Rollback:**
- Order already paid: Skip credit (idempotent, no error)
- Balance update failure: Transaction rollback, order remains pending
- Transaction creation failure: Transaction rollback, order remains pending

---

### Subtask 3.3.11: Create Transaction Log
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/OrderController.php` (transaction creation in callback method)

**Database Reads/Writes:**
- Write: `token_transactions` (INSERT)

**External Dependencies:**
- TokenTransaction model
- Database transaction

**Idempotency Requirements:**
- Transaction creation is idempotent (order_id check prevents duplicates)

**Acceptance Criteria:**
- Creates transaction with: user_id, order_id, amount_tokens, type='purchase', description
- Transaction created atomically with balance update
- Transaction links to order_id for audit trail

**Failure Modes and Rollback:**
- Transaction creation failure: Rollback balance update, order remains pending

---

### Subtask 3.3.12: Handle Payment Failures
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/OrderController.php` (failure handling in callback method)

**Database Reads/Writes:**
- Write: `orders` (UPDATE status to 'failed')

**External Dependencies:**
- None

**Idempotency Requirements:**
- Failure handling is idempotent (same failure = same result)

**Acceptance Criteria:**
- If verification fails: Update order status to 'failed'
- If amount mismatch: Update order status to 'failed'
- If invalid authority: Return 404
- Log all failures
- No tokens credited on failure

**Failure Modes and Rollback:**
- Status update failure: Log error, order remains pending (manual review needed)

---

### Subtask 3.3.13: Add Idempotency for Payments
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/OrderController.php` (idempotency checks throughout)

**Database Reads/Writes:**
- Read: `orders` (check status before processing)

**External Dependencies:**
- None

**Idempotency Requirements:**
- Payment request: Check if order already has authority (return existing)
- Payment callback: Check order status (reject if already paid/failed)

**Acceptance Criteria:**
- Purchase: If order exists with same bundle, create new order (multiple orders allowed)
- Callback: Check order.status before processing (reject if not 'pending')
- Callback: Check zarinpal_ref_id uniqueness (database constraint)
- Log idempotency checks

**Failure Modes and Rollback:**
- Duplicate callback: Return 400 (already processed), no tokens credited
- Duplicate authority: Database constraint prevents, return 500

**Test Coverage Expectations:**
- Feature test: Purchase creates order with pending status
- Feature test: Payment request returns payment URL
- Feature test: Callback verifies payment and credits tokens
- Feature test: Duplicate callback does not credit twice (idempotency)
- Feature test: Payment failure updates order status
- Feature test: Amount verification prevents partial payments
- Feature test: Server-to-server verification (mocked Zarinpal API)

---

## Task 3.4: Token Transaction System

### Subtask 3.4.1: Create TokenTransactionController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/TokenTransactionController.php` (new file)

**Database Reads/Writes:**
- Read: `token_transactions` (list user transactions)
- Read: `users` (get balance)

**External Dependencies:**
- None

**Idempotency Requirements:**
- Read-only operations, fully idempotent

**Acceptance Criteria:**
- Controller in Api/V1 namespace
- All methods require authentication
- Thin controller (business logic in services if needed)

**Failure Modes and Rollback:**
- Unauthorized: Return 401

---

### Subtask 3.4.2: Implement Transaction Logging Methods
**Files to Create/Modify:**
- `app/Services/TokenTransactionService.php` (new - optional service)
- Or implement directly in controller/model

**Database Reads/Writes:**
- Write: `token_transactions` (INSERT)
- Write: `users` (UPDATE tokens_balance)

**External Dependencies:**
- Database transaction (for atomicity)

**Idempotency Requirements:**
- Transaction creation is idempotent (reference_id check prevents duplicates)

**Acceptance Criteria:**
- Method to create transaction: `createTransaction(User $user, int $amount, string $type, ?int $orderId, ?string $description)`
- Creates transaction record
- Updates user balance atomically
- Returns transaction record

**Failure Modes and Rollback:**
- Transaction creation failure: Rollback balance update
- Balance update failure: Rollback transaction creation

---

### Subtask 3.4.3: Create getHistory() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/TokenTransactionController.php` (implement index method)

**Database Reads/Writes:**
- Read: `token_transactions` WHERE user_id = auth()->id()

**External Dependencies:**
- None

**Idempotency Requirements:**
- Read-only operation, fully idempotent

**Acceptance Criteria:**
- GET /api/v1/tokens/history returns user's transaction history
- Returns: id, type, amount_tokens, amount_usd, description, created_at, order_id (if purchase)
- Paginated (15 per page)
- Ordered by created_at DESC
- HTTP 200 on success

**Failure Modes and Rollback:**
- No transactions: Return empty array (not error)

---

### Subtask 3.4.4: Add Filtering (Type, Date Range)
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/TokenTransactionController.php` (add filters to index method)
- `app/Http/Requests/Api/V1/TokenTransactionHistoryRequest.php` (new - validation)

**Database Reads/Writes:**
- Read: `token_transactions` (with WHERE clauses)

**External Dependencies:**
- None

**Idempotency Requirements:**
- Filtering is idempotent (same filters = same results)

**Acceptance Criteria:**
- Filter by type: ?type=purchase|consume|refund|bonus|adjustment
- Filter by date range: ?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
- Filters are optional
- Validation enforces: type (in enum), dates (date format)

**Failure Modes and Rollback:**
- Invalid filter: Return 422 with validation errors

---

### Subtask 3.4.5: Add Pagination
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/TokenTransactionController.php` (pagination in index method)

**Database Reads/Writes:**
- Read: `token_transactions` (with LIMIT/OFFSET)

**External Dependencies:**
- Laravel pagination

**Idempotency Requirements:**
- Pagination is idempotent (same page = same results)

**Acceptance Criteria:**
- Paginated results (15 per page)
- Response includes: data, current_page, total, per_page, last_page
- Query parameter: ?page=N

**Failure Modes and Rollback:**
- Invalid page: Return empty array (Laravel handles)

---

### Subtask 3.4.6: Implement Balance Calculation from Transactions
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/TokenTransactionController.php` (implement balance method)

**Database Reads/Writes:**
- Read: `token_transactions` (SUM amount_tokens)
- Read: `users` (get current balance)

**External Dependencies:**
- None

**Idempotency Requirements:**
- Balance calculation is deterministic (same transactions = same balance)

**Acceptance Criteria:**
- GET /api/v1/tokens/balance returns current balance
- Balance = SUM(token_transactions.amount_tokens WHERE user_id = X)
- Also returns user.tokens_balance (for comparison/reconciliation)
- HTTP 200 on success

**Failure Modes and Rollback:**
- No transactions: Return balance = 0

---

### Subtask 3.4.7: Add Transaction Types
**Files to Create/Modify:**
- Already in database enum, verify all types are handled

**Database Reads/Writes:**
- Read: `token_transactions` (filter by type)

**External Dependencies:**
- None

**Idempotency Requirements:**
- Type filtering is idempotent

**Acceptance Criteria:**
- All transaction types supported: purchase, consume, refund, bonus, adjustment
- Type filtering works in history endpoint
- Type descriptions are clear in responses

**Failure Modes and Rollback:**
- Invalid type: Return 422 with validation error

**Test Coverage Expectations:**
- Feature test: Get transaction history (paginated)
- Feature test: Filter by type
- Feature test: Filter by date range
- Feature test: Get balance (calculated from transactions)
- Feature test: Balance matches user.tokens_balance (reconciliation)
- Feature test: Transaction creation updates balance atomically

---

## Summary

**Total Files to Create:** 12
- 3 Controllers (TokenBundle, Order, TokenTransaction, Currency)
- 4 Form Requests (StoreTokenBundle, UpdateTokenBundle, PurchaseToken, TokenTransactionHistory)
- 2 Services (TgjuScraperService, ZarinpalService)
- 1 Optional Service (CurrencyRateService or use model methods)

**Total Files to Modify:** 3
- routes/api.php (add routes)
- routes/console.php (add scheduled job)
- config/services.php (add Zarinpal config if missing)

**Dependencies:**
- Environment variables: ZARINPAL_MERCHANT_ID, ZARINPAL_SANDBOX, ZARINPAL_CALLBACK_URL
- Packages: Guzzle (via Laravel HTTP), DOM parser (optional, for HTML scraping)
- Infrastructure: Redis (for rate caching), Database (for all financial records)

**Financial Integrity Checklist:**
- [ ] All prices calculated server-side (never from client)
- [ ] Orders immutable after creation (no price/amount changes)
- [ ] Token credit is atomic (transaction + balance update)
- [ ] Payment verification is server-to-server (never trust client)
- [ ] Callback processing is idempotent (status check prevents duplicates)
- [ ] All token movements logged (transaction records)
- [ ] Balance derivable from transactions (reconciliation possible)
- [ ] Rate snapshot stored in orders (audit trail)
- [ ] Database transactions ensure atomicity
- [ ] Unique constraints prevent duplicates (authority, ref_id)

**Test Coverage Minimum:**
- 15+ feature tests for token bundles
- 10+ feature tests for currency scraper
- 20+ feature tests for payment flow
- 10+ feature tests for transaction system
- Mock Zarinpal API in all tests
- Mock TGJU.org HTML in scraper tests

