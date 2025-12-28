# Phase 3: Token System & Payment - Financial Domain Summary

## Financial System Architecture (Audit-Grade)

### 1. Token as Prepaid Value Representation
- **Tokens are prepaid credits**: Users purchase tokens upfront, tokens represent prepaid value for AI content generation
- **1 token = 1 unit of generation capacity**: Token consumption is based on model complexity, quality, and content type
- **Token balance is a liability**: User's token balance represents our obligation to provide generation services
- **Balance must be derivable**: User's `tokens_balance` field must match sum of all `token_transactions` (audit requirement)
- **Balance is never directly mutated**: Balance changes only through transaction records (append-only ledger)

### 2. USD → Toman Pricing Flow
- **Source of truth**: Token bundle `price_usd` is immutable base price in USD
- **Dynamic conversion**: Toman price = `price_usd * dollar_rate` (calculated server-side, never trusted from client)
- **Rate snapshot**: Order stores `dollar_rate` at purchase time (immutable historical record)
- **Rate source**: TGJU.org provides USD to Rials rate, converted to Toman (Rials ÷ 10)
- **Rate volatility**: Exchange rate changes every 5 minutes, prices recalculated on each purchase request
- **No price manipulation**: Client cannot provide prices; all calculations server-side

### 3. Immutable Financial Records (Audit Trail)
- **Orders are immutable after creation**: Once created, order fields (price_toman, price_usd, dollar_rate) never change
- **Order status transitions are linear**: pending → paid/failed/cancelled (no backward transitions)
- **Token transactions are append-only**: Once created, transactions are never deleted or modified
- **Transaction types are explicit**: purchase, consume, refund, bonus, adjustment (each has distinct business meaning)
- **Currency rates are historical**: Each rate fetch creates a new record (never updates existing)

### 4. Idempotency in Financial Operations
- **Payment requests are idempotent**: Same order cannot create multiple payment requests (authority uniqueness)
- **Callback processing is idempotent**: Same authority/ref_id cannot credit tokens twice (order status check)
- **Token credit is idempotent**: Order status check prevents double-crediting on duplicate callbacks
- **Transaction creation is idempotent**: Reference ID checks prevent duplicate transaction logs
- **Rate fetching is idempotent**: Multiple fetches create separate records (no harm in duplicates)

### 5. Money/Token Loss Prevention
- **Double-spend prevention**: Order status check + authority uniqueness prevents duplicate token credits
- **Race condition prevention**: Database transactions ensure atomic order creation and status updates
- **Callback replay prevention**: Order status validation prevents processing already-paid orders
- **Token balance drift prevention**: Balance calculated from transactions, not directly mutated
- **Price manipulation prevention**: All prices calculated server-side, never trusted from client
- **Rate failure handling**: Fallback to last known rate prevents purchase blocking

### 6. Atomic Operations (ACID Compliance)
- **Order creation + payment request**: Must be atomic (order created before payment request)
- **Payment verification + token credit**: Must be atomic (status update + balance update + transaction log)
- **Token transaction + balance update**: Must be atomic (transaction created + user balance updated)
- **Rate fetch + cache update**: Should be atomic (database write + Redis cache update)
- **Order status transitions**: Must be atomic (status check + status update in single transaction)

### 7. Financial Data Flow Integrity
- **Order lifecycle**: pending → (payment request) → paid/failed/cancelled (linear, no loops)
- **Token flow**: purchase → credit → consume → (optional refund) (one-way flow)
- **Balance calculation**: `tokens_balance = SUM(token_transactions.amount_tokens WHERE user_id = X)`
- **Price calculation**: `price_toman = price_usd * dollar_rate` (always recalculated, never cached)
- **Rate flow**: TGJU.org → scrape → store → cache → use (fallback to last known if scrape fails)

### 8. External Payment Gateway Integration
- **Zarinpal authority**: Unique identifier for payment request (prevents duplicate requests)
- **Zarinpal ref_id**: Unique identifier for successful payment (prevents duplicate credits)
- **Server-to-server verification**: Callback verification must call Zarinpal API (never trust client data)
- **Payment amount verification**: Verify callback amount matches order amount (prevents partial payment attacks)
- **Idempotent callback handling**: Check order status before processing (prevents duplicate processing)

### 9. Audit Requirements
- **Every token movement logged**: All balance changes must create transaction record
- **Order history preserved**: Orders never deleted (soft delete if needed, but prefer status field)
- **Rate history preserved**: All rate fetches stored (for price audit trail)
- **Payment details preserved**: Authority, ref_id, paid_at stored in order (for payment audit)
- **Transaction traceability**: Each transaction links to order_id or generation_job_id (full audit trail)

### 10. Failure Modes and Recovery
- **Scraper failure**: Fallback to last known rate (purchases continue, rate may be stale)
- **Payment gateway failure**: Order remains pending, user can retry (idempotent)
- **Callback failure**: Order remains pending, manual verification possible (ref_id stored)
- **Database transaction failure**: Rollback prevents partial state (atomic operations)
- **Cache failure**: Fallback to database (rate still available, slightly slower)

### 11. Financial Consistency Rules
- **Balance = Sum of Transactions**: User balance must equal sum of all transactions (reconciliation check)
- **Order Amount = Bundle Price**: Order price must match bundle price at time of purchase
- **Token Amount = Bundle Amount**: Order tokens must match bundle token_amount + bonus_tokens
- **Rate Used = Rate Stored**: Order dollar_rate must match rate used for price calculation
- **Status Consistency**: Order status must reflect payment state (paid = has ref_id and paid_at)

### 12. Race Condition Prevention
- **Concurrent purchases**: Database unique constraint on zarinpal_authority prevents duplicate payment requests
- **Concurrent callbacks**: Order status check + database transaction prevents double credit
- **Concurrent balance updates**: Database transaction ensures atomic balance + transaction creation
- **Concurrent rate fetches**: Multiple fetches create separate records (no conflict)

### 13. Token Credit Rules
- **Credit only on paid status**: Tokens credited only when order status = 'paid'
- **Credit amount = order amount**: Tokens credited = order.amount_tokens (includes bonus)
- **One credit per order**: Order can only credit tokens once (status check prevents duplicates)
- **Transaction log required**: Every credit must create transaction record (audit requirement)
- **Balance update required**: User balance must be updated atomically with transaction creation

### 14. Price Calculation Rules
- **USD price is source of truth**: Bundle price_usd never changes (immutable)
- **Toman price is derived**: Calculated from price_usd * dollar_rate (never stored in bundle)
- **Rate is snapshot**: Order stores dollar_rate at purchase time (historical accuracy)
- **Rate is current**: Price calculated using latest rate (or fallback to last known)
- **No client prices**: Client never provides prices; all calculations server-side

### 15. Order State Machine
- **Initial state**: pending (order created, payment requested)
- **Success transition**: pending → paid (payment verified, tokens credited)
- **Failure transition**: pending → failed (payment failed, no tokens credited)
- **Cancellation**: pending → cancelled (user cancelled, no tokens credited)
- **No backward transitions**: Once paid/failed/cancelled, order cannot return to pending

### 16. Transaction Types and Meanings
- **purchase**: Token credit from order payment (positive amount_tokens)
- **consume**: Token debit from generation job (negative amount_tokens)
- **refund**: Token debit from order refund (negative amount_tokens, links to order_id)
- **bonus**: Token credit from promotions (positive amount_tokens, no order_id)
- **adjustment**: Manual token adjustment by admin (positive or negative, no order_id)

### 17. Currency Rate Management
- **Rate source**: TGJU.org (web scraping, not API)
- **Rate format**: USD to Rials (IRR), converted to Toman (÷10)
- **Rate storage**: Historical records in currency_rates table (never updates, always appends)
- **Rate caching**: Redis cache with 5-minute TTL (reduces database queries)
- **Rate fallback**: Last known rate if scraping fails (ensures purchase availability)

### 18. Payment Gateway Integration Rules
- **Payment request**: Creates order with pending status, stores zarinpal_authority
- **Payment callback**: Verifies with Zarinpal API, updates order to paid, credits tokens
- **Callback verification**: Server-to-server API call (never trust client-provided payment data)
- **Amount verification**: Callback amount must match order amount (prevents partial payment)
- **Idempotency**: Same authority/ref_id cannot process twice (order status + unique constraints)

### 19. Financial Reconciliation Points
- **User balance reconciliation**: `user.tokens_balance` should equal `SUM(token_transactions.amount_tokens)`
- **Order revenue reconciliation**: `SUM(orders.price_toman WHERE status='paid')` = total revenue
- **Token inventory reconciliation**: `SUM(token_transactions.amount_tokens WHERE type='purchase')` = total tokens sold
- **Rate accuracy**: Order dollar_rate should match currency_rates.rate at order.created_at time

### 20. Security and Fraud Prevention
- **Price manipulation prevention**: All prices calculated server-side (client never provides prices)
- **Double-spend prevention**: Order status + authority uniqueness prevents duplicate credits
- **Partial payment prevention**: Amount verification in callback prevents accepting wrong amounts
- **Replay attack prevention**: Order status check prevents processing same callback twice
- **Token balance manipulation prevention**: Balance only updated through transactions (no direct updates)

### 21. Performance and Scalability
- **Rate caching**: Redis cache reduces database queries (5-minute TTL)
- **Transaction indexing**: Indexes on user_id, type, created_at for fast history queries
- **Order indexing**: Indexes on user_id, status, zarinpal_authority for fast lookups
- **Rate fetching**: Scheduled job runs every 5 minutes (background, non-blocking)
- **Balance calculation**: Can be optimized with materialized view or periodic reconciliation

### 22. Error Handling and Logging
- **Scraper errors**: Logged, fallback to last known rate (purchases continue)
- **Payment errors**: Logged, order remains pending (user can retry)
- **Callback errors**: Logged, order remains pending (manual verification possible)
- **Transaction errors**: Logged, transaction rolled back (atomic operations prevent partial state)
- **Financial events logged**: All token credits, debits, order status changes logged (audit trail)

### 23. Data Integrity Constraints
- **Order uniqueness**: zarinpal_authority must be unique (prevents duplicate payment requests)
- **Transaction reference**: order_id or generation_job_id links transaction to source
- **Balance consistency**: Balance should match transaction sum (reconciliation check)
- **Status consistency**: Order status must match payment state (paid = has ref_id)
- **Rate consistency**: Order dollar_rate must be valid (exists in currency_rates or fallback)

### 24. Business Rules Enforcement
- **Active bundles only**: Only active bundles can be purchased (is_active = true)
- **Token amount validation**: Order amount_tokens must match bundle token_amount + bonus_tokens
- **Price validation**: Order price_usd must match bundle price_usd (no discounts without explicit logic)
- **Rate validation**: Order dollar_rate must be within reasonable range (prevent scraping errors)
- **Status validation**: Order status transitions must be valid (pending → paid/failed/cancelled only)

### 25. Financial Reporting Requirements
- **Revenue tracking**: Sum of paid orders (price_toman) by date range
- **Token sales tracking**: Sum of purchase transactions (amount_tokens) by date range
- **Refund tracking**: Sum of refund transactions (amount_tokens) by date range
- **Rate history**: All rate fetches stored for price audit trail
- **Order audit**: All orders preserved with full payment details (authority, ref_id, timestamps)

---

## Critical Financial Principles

1. **Never trust client prices** - All prices calculated server-side
2. **Never mutate financial records** - Orders and transactions are append-only after creation
3. **Always log token movements** - Every balance change must create transaction record
4. **Always verify payments** - Server-to-server verification, never trust client data
5. **Always use transactions** - Atomic operations prevent partial state
6. **Always check order status** - Prevent duplicate processing
7. **Always snapshot rates** - Store rate at purchase time for audit
8. **Always handle failures gracefully** - Fallbacks prevent system blocking

