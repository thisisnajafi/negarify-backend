# Phase 6: Admin Dashboard APIs - TODO Checklist

## Task 6.1: Admin Authentication & Middleware

### Subtask 6.1.1: Verify AdminMiddleware Exists
**Files to Check:**
- `app/Http/Middleware/AdminMiddleware.php` (should already exist from Phase 5)

**Routes & Middleware:**
- All admin routes must use `auth:sanctum` + `admin` middleware
- Middleware alias registered in `bootstrap/app.php`

**DB Reads/Writes:**
- Read: `users` (check role field)

**Indexes Used:**
- `users.role` (if indexed, for role checks)

**Caching Decisions:**
- None (role check is fast, no caching needed)

**Authorization Rules:**
- Only users with `role = 'admin'` can access admin endpoints
- Non-admin users receive 403 Forbidden

**Acceptance Criteria:**
- AdminMiddleware exists and works correctly
- All admin routes protected
- Non-admin users cannot access admin endpoints

**Failure Cases & Security Pitfalls:**
- Non-admin accesses admin endpoint → 403 Forbidden
- Middleware not applied → Security breach
- Role check bypassed → Security breach

**Test Coverage Expectations:**
- Feature test: Non-admin cannot access admin endpoints
- Feature test: Admin can access admin endpoints
- Feature test: Unauthenticated user cannot access admin endpoints

---

## Task 6.2: Sales Dashboard API

### Subtask 6.2.1: Create AdminSalesController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/sales/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `orders` (WHERE status='paid', with date filtering)
- Read: `token_bundles` (for bundle names in top bundles)

**Indexes Used:**
- `orders.status` (for filtering paid orders)
- `orders.created_at` (for date filtering)
- `orders.paid_at` (for date filtering on paid orders)
- `orders.user_id` (for user-related queries)

**Caching Decisions:**
- Cache summary results for 10 minutes
- Cache key: `admin:sales:summary:{range}:{start_date}:{end_date}`
- Invalidate on new order creation (or accept stale data)

**Authorization Rules:**
- Admin-only access

**Acceptance Criteria:**
- Controller exists with summary endpoint
- Returns sales summary data

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- Slow query on large dataset → Performance issue
- Incorrect revenue calculation → Financial error

---

### Subtask 6.2.2: Implement summary() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (summary method)
- `app/Http/Requests/Api/V1/AdminSalesSummaryRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/sales/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `orders` (aggregate revenue, count orders)
- Read: `token_bundles` (for bundle names)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 10 minutes

**Authorization Rules:**
- Admin-only

**Acceptance Criteria:**
- Returns total_revenue_toman, total_revenue_usd, total_orders
- Supports date range filtering (range parameter or start_date/end_date)
- Returns revenue_by_day/week/month (time-series)
- Returns top_bundles (top 10 selling bundles)
- Returns refunds data (if applicable)

**Failure Cases & Security Pitfalls:**
- Invalid date range → 400 Bad Request
- Revenue includes non-paid orders → Financial error
- Slow query → Performance issue

---

### Subtask 6.2.3: Calculate Revenue by Day/Week/Month
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (revenue aggregation logic)
- `app/Services/AdminSalesService.php` (new - optional service for complex logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `orders` (GROUP BY date, SUM(price_toman), SUM(price_usd))

**Indexes Used:**
- `orders.created_at` (for date grouping)
- `orders.paid_at` (alternative for paid orders)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Revenue grouped by day/week/month based on range parameter
- Returns array of {date, revenue_toman, revenue_usd, orders_count}
- Dates in ISO format (YYYY-MM-DD)
- Gaps filled with zeros (complete time series)

**Failure Cases & Security Pitfalls:**
- Incorrect date grouping → Data inconsistency
- Missing dates in time series → Charting issues

---

### Subtask 6.2.4: Calculate Top Selling Bundles
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (top bundles logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `orders` (GROUP BY token_bundle_id, SUM(price_toman), COUNT(*))
- Read: `token_bundles` (for bundle names)

**Indexes Used:**
- `orders.token_bundle_id` (for grouping)
- `orders.status` (for filtering paid orders)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns top 10 bundles by revenue
- Includes bundle name, revenue_toman, revenue_usd, orders_count
- Ordered by revenue DESC

**Failure Cases & Security Pitfalls:**
- Slow query → Performance issue
- Incorrect grouping → Data inconsistency

---

### Subtask 6.2.5: Track Refunds
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (refunds logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `orders` (WHERE status='refunded' or similar, if refund status exists)
- Read: `token_transactions` (WHERE type='refund', SUM(amount_tokens))

**Indexes Used:**
- `orders.status` (if refund status exists)
- `token_transactions.type` (for refund transactions)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns refund count and refund amount
- Separate from revenue (refunds excluded from revenue)
- If no refund system, return zeros

**Failure Cases & Security Pitfalls:**
- Refunds included in revenue → Financial error
- Missing refund data → Incomplete reporting

---

### Subtask 6.2.6: Calculate Customer LTV
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (LTV logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `orders` (GROUP BY user_id, SUM(price_toman) WHERE status='paid')

**Indexes Used:**
- `orders.user_id` (for grouping)
- `orders.status` (for filtering paid orders)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns average LTV (average revenue per customer)
- Returns median LTV (if possible)
- Returns top customers by LTV (top 10)

**Failure Cases & Security Pitfalls:**
- Incorrect LTV calculation → Business decision error
- Slow query → Performance issue

---

### Subtask 6.2.7: Add Date Range Filtering
**Files to Create/Modify:**
- `app/Http/Requests/Api/V1/AdminSalesSummaryRequest.php` (date validation)

**Routes & Middleware:**
- N/A (validation only)

**DB Reads/Writes:**
- None (validation only)

**Indexes Used:**
- None

**Caching Decisions:**
- Cache key includes date range

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Supports `range=day|week|month|year|all` parameter
- Supports `start_date=YYYY-MM-DD&end_date=YYYY-MM-DD` parameters
- Default to last 30 days if no range/date specified
- Validates date format and range (start_date <= end_date)

**Failure Cases & Security Pitfalls:**
- Invalid date format → 422 Validation Error
- start_date > end_date → 422 Validation Error
- Date range too large → Performance issue (consider max range limit)

---

### Subtask 6.2.8: Optimize Queries with Indexes
**Files to Create/Modify:**
- Database migration (if indexes missing)
- Verify indexes exist on orders table

**Routes & Middleware:**
- N/A (database optimization)

**DB Reads/Writes:**
- None (index creation)

**Indexes Used:**
- `orders.status` (for filtering paid orders)
- `orders.created_at` (for date filtering)
- `orders.paid_at` (for paid order date filtering)
- `orders.user_id` (for user-related queries)
- `orders.token_bundle_id` (for bundle grouping)
- Composite: `(status, created_at)` (for common query pattern)

**Caching Decisions:**
- N/A

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- All queries use indexes (verify with EXPLAIN)
- Query execution time < 1 second for typical date ranges
- No full table scans

**Failure Cases & Security Pitfalls:**
- Missing indexes → Slow queries
- Full table scans → Performance degradation

**Test Coverage Expectations:**
- Feature test: Sales summary returns correct data
- Feature test: Date range filtering works correctly
- Feature test: Revenue calculation is accurate (only paid orders)
- Feature test: Top bundles calculation is correct
- Performance test: Query uses indexes, execution time acceptable

---

## Task 6.3: Users Dashboard API

### Subtask 6.3.1: Create AdminUsersController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/users/summary` - `auth:sanctum`, `admin`
- GET `/api/v1/admin/users` - `auth:sanctum`, `admin` (list/search)

**DB Reads/Writes:**
- Read: `users` (for user counts and lists)
- Read: `generation_jobs` (for activity metrics)

**Indexes Used:**
- `users.created_at` (for date filtering)
- `users.role` (for role filtering)
- `generation_jobs.user_id` (for user activity)
- `generation_jobs.status` (for completed jobs)
- `generation_jobs.completed_at` (for activity date)

**Caching Decisions:**
- Cache summary for 15 minutes
- Cache key: `admin:users:summary:{date_range}`
- User list not cached (real-time data)

**Authorization Rules:**
- Admin-only access

**Acceptance Criteria:**
- Controller exists with summary and list endpoints
- Returns user metrics

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- User data leaked → Privacy breach

---

### Subtask 6.3.2: Implement summary() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (summary method)
- `app/Http/Requests/Api/V1/AdminUsersSummaryRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/users/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `users` (COUNT total users)
- Read: `generation_jobs` (COUNT active users by period)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 15 minutes

**Authorization Rules:**
- Admin-only

**Acceptance Criteria:**
- Returns DAU (Daily Active Users)
- Returns WAU (Weekly Active Users)
- Returns MAU (Monthly Active Users)
- Returns total_users
- Returns new_users (in date range)
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect DAU/WAU/MAU calculation → Metric error
- Slow query → Performance issue

---

### Subtask 6.3.3: Calculate DAU/WAU/MAU
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (DAU/WAU/MAU logic)
- `app/Services/AdminUsersService.php` (new - optional service)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `generation_jobs` (COUNT DISTINCT user_id WHERE status='completed' AND completed_at IN last 24h/7d/30d)

**Indexes Used:**
- `generation_jobs.user_id` (for distinct counting)
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.completed_at` (for date filtering)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- DAU: Users with completed generation in last 24 hours
- WAU: Users with completed generation in last 7 days
- MAU: Users with completed generation in last 30 days
- Uses DISTINCT user_id to avoid duplicates
- Accurate count (matches manual verification)

**Failure Cases & Security Pitfalls:**
- Incorrect date range → Metric error
- Duplicate users counted → Data inconsistency

---

### Subtask 6.3.4: Get Top Users by Generation Count
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (top users logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `generation_jobs` (GROUP BY user_id, COUNT(*) WHERE status='completed')
- Read: `users` (for user names)

**Indexes Used:**
- `generation_jobs.user_id` (for grouping)
- `generation_jobs.status` (for filtering completed)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns top 10 users by generation count
- Includes user id, name, generation_count
- Ordered by generation_count DESC
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Slow query → Performance issue
- Incorrect count → Data inconsistency

---

### Subtask 6.3.5: Get Top Users by Spending
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (top spenders logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `orders` (GROUP BY user_id, SUM(price_toman) WHERE status='paid')
- Read: `users` (for user names)

**Indexes Used:**
- `orders.user_id` (for grouping)
- `orders.status` (for filtering paid orders)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns top 10 users by spending
- Includes user id, name, total_spent_toman, total_spent_usd, orders_count
- Ordered by total_spent DESC
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Slow query → Performance issue
- Includes non-paid orders → Financial error

---

### Subtask 6.3.6: Implement Cohort Analysis
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (cohort method, optional)
- `app/Services/AdminUsersService.php` (cohort calculation logic)

**Routes & Middleware:**
- GET `/api/v1/admin/users/cohorts` - `auth:sanctum`, `admin` (optional endpoint)

**DB Reads/Writes:**
- Read: `users` (GROUP BY signup month/week)
- Read: `generation_jobs` (COUNT active users per cohort per period)

**Indexes Used:**
- `users.created_at` (for signup date)
- `generation_jobs.user_id` (for user activity)
- `generation_jobs.completed_at` (for activity date)

**Caching Decisions:**
- Cache for 1 hour (cohort analysis is expensive)

**Authorization Rules:**
- Admin-only

**Acceptance Criteria:**
- Groups users by signup period (month/week)
- Calculates retention for each cohort
- Returns retention matrix (cohort x period)
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Very slow query → Performance issue (consider scheduled job)
- Incorrect retention calculation → Business decision error

---

### Subtask 6.3.7: Track Churn and Reactivation
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (churn logic, optional)

**Routes & Middleware:**
- N/A (handled in summary method, or separate endpoint)

**DB Reads/Writes:**
- Read: `users` (users with no activity in last 30 days = churned)
- Read: `generation_jobs` (users with activity after churn = reactivated)

**Indexes Used:**
- `generation_jobs.user_id` (for activity check)
- `generation_jobs.completed_at` (for last activity date)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns churned_users_count (no activity in last 30 days)
- Returns reactivated_users_count (activity after 30 days of inactivity)
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect churn definition → Metric error
- Slow query → Performance issue

---

### Subtask 6.3.8: Add User Search and Filtering
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (index method for list)
- `app/Http/Requests/Api/V1/AdminUsersListRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/users` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `users` (with search and filters, paginated)

**Indexes Used:**
- `users.phone` (for phone search)
- `users.email` (for email search)
- `users.name` (for name search)
- `users.role` (for role filtering)
- `users.created_at` (for date filtering)

**Caching Decisions:**
- None (real-time user data, not cacheable)

**Authorization Rules:**
- Admin-only

**Acceptance Criteria:**
- Supports search by phone, email, name
- Supports filtering by role, date range
- Paginated (15-50 per page)
- Returns user list with basic info (id, name, phone, email, role, created_at, tokens_balance)

**Failure Cases & Security Pitfalls:**
- Unbounded query → Performance issue (must paginate)
- Sensitive data exposed → Privacy breach (only return necessary fields)

**Test Coverage Expectations:**
- Feature test: Users summary returns correct DAU/WAU/MAU
- Feature test: Top users by generation count is accurate
- Feature test: Top users by spending is accurate
- Feature test: User search and filtering works
- Feature test: Pagination works correctly

---

## Task 6.4: Models Usage Dashboard API

### Subtask 6.4.1: Create AdminModelsController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/models/usage` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `generation_jobs` (aggregate by model_id)
- Read: `analytics_models_usage` (if aggregated table exists, prefer it)
- Read: `models` (for model names)

**Indexes Used:**
- `generation_jobs.model_id` (for grouping)
- `generation_jobs.status` (for success/failure counts)
- `generation_jobs.created_at` (for date filtering)
- `analytics_models_usage.model_id` (if using aggregated table)
- `analytics_models_usage.period_start` (for date filtering)

**Caching Decisions:**
- Cache for 15 minutes
- Cache key: `admin:models:usage:{date_range}`
- Prefer aggregated table (analytics_models_usage) for historical data

**Authorization Rules:**
- Admin-only access

**Acceptance Criteria:**
- Controller exists with usage endpoint
- Returns model usage statistics

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- Slow query on large dataset → Performance issue

---

### Subtask 6.4.2: Implement usage() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (usage method)
- `app/Http/Requests/Api/V1/AdminModelsUsageRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/models/usage` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `generation_jobs` or `analytics_models_usage` (aggregate by model)
- Read: `models` (for model names)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 15 minutes
- Prefer aggregated table for historical data

**Authorization Rules:**
- Admin-only

**Acceptance Criteria:**
- Returns usage statistics per model
- Includes: requests_count, successful_count, failed_count, success_rate, failure_rate
- Includes: tokens_consumed, cost_usd, avg_latency_ms
- Supports date range filtering
- Returns time-series data (daily/weekly/monthly)

**Failure Cases & Security Pitfalls:**
- Incorrect aggregation → Data inconsistency
- Slow query → Performance issue

---

### Subtask 6.4.3: Track Requests per Model
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (request counting logic)

**Routes & Middleware:**
- N/A (handled in usage method)

**DB Reads/Writes:**
- Read: `generation_jobs` (COUNT(*) GROUP BY model_id WHERE status IN ('completed', 'failed'))

**Indexes Used:**
- `generation_jobs.model_id` (for grouping)
- `generation_jobs.status` (for filtering)

**Caching Decisions:**
- None (part of usage response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns total requests per model
- Includes completed and failed requests
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect count → Data inconsistency
- Includes cancelled/pending → Metric error

---

### Subtask 6.4.4: Calculate Average Cost per Model
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (cost calculation logic)

**Routes & Middleware:**
- N/A (handled in usage method)

**DB Reads/Writes:**
- Read: `generation_jobs` (AVG(cost_usd) GROUP BY model_id WHERE status='completed')

**Indexes Used:**
- `generation_jobs.model_id` (for grouping)
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.cost_usd` (for averaging)

**Caching Decisions:**
- None (part of usage response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns average cost per model
- Returns total cost per model (SUM)
- Only includes completed jobs (status='completed')
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Includes failed jobs → Cost calculation error
- Incorrect average → Financial error

---

### Subtask 6.4.5: Track Tokens Consumed
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (token tracking logic)

**Routes & Middleware:**
- N/A (handled in usage method)

**DB Reads/Writes:**
- Read: `generation_jobs` (SUM(tokens_consumed) GROUP BY model_id WHERE status='completed')

**Indexes Used:**
- `generation_jobs.model_id` (for grouping)
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.tokens_consumed` (for summing)

**Caching Decisions:**
- None (part of usage response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns total tokens consumed per model
- Only includes completed jobs
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Includes failed jobs → Token count error
- Mismatch with token_transactions → Data inconsistency

---

### Subtask 6.4.6: Calculate Average Latency
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (latency calculation logic)

**Routes & Middleware:**
- N/A (handled in usage method)

**DB Reads/Writes:**
- Read: `generation_jobs` (AVG(completed_at - started_at) GROUP BY model_id WHERE status='completed')

**Indexes Used:**
- `generation_jobs.model_id` (for grouping)
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.started_at` (for latency calculation)
- `generation_jobs.completed_at` (for latency calculation)

**Caching Decisions:**
- None (part of usage response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns average latency per model (in milliseconds)
- Only includes completed jobs with started_at and completed_at
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Missing started_at/completed_at → Incorrect latency
- Includes failed jobs → Latency error

---

### Subtask 6.4.7: Track Failure Rates
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (failure rate logic)

**Routes & Middleware:**
- N/A (handled in usage method)

**DB Reads/Writes:**
- Read: `generation_jobs` (COUNT(*) WHERE status='failed' GROUP BY model_id)

**Indexes Used:**
- `generation_jobs.model_id` (for grouping)
- `generation_jobs.status` (for filtering failed)

**Caching Decisions:**
- None (part of usage response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns failed_count per model
- Returns failure_rate (failed_count / total_count * 100)
- Returns success_rate (successful_count / total_count * 100)
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect rate calculation → Metric error
- Division by zero → Error handling needed

---

### Subtask 6.4.8: Add Date Range Filtering
**Files to Create/Modify:**
- `app/Http/Requests/Api/V1/AdminModelsUsageRequest.php` (date validation)

**Routes & Middleware:**
- N/A (validation only)

**DB Reads/Writes:**
- None (validation only)

**Indexes Used:**
- None

**Caching Decisions:**
- Cache key includes date range

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Supports `range=day|week|month|year|all` parameter
- Supports `start_date=YYYY-MM-DD&end_date=YYYY-MM-DD` parameters
- Default to last 30 days if no range/date specified
- Validates date format and range

**Failure Cases & Security Pitfalls:**
- Invalid date format → 422 Validation Error
- Date range too large → Performance issue

---

### Subtask 6.4.9: Create Aggregation Job for Analytics
**Files to Create/Modify:**
- `app/Console/Commands/AggregateModelsUsage.php` (new - scheduled job)
- `routes/console.php` (schedule job)

**Routes & Middleware:**
- N/A (scheduled job)

**DB Reads/Writes:**
- Read: `generation_jobs` (aggregate by model, period)
- Write: `analytics_models_usage` (INSERT/UPDATE aggregated data)

**Indexes Used:**
- All indexes from previous subtasks

**Caching Decisions:**
- None (background job)

**Authorization Rules:**
- N/A (system job)

**Acceptance Criteria:**
- Job aggregates generation_jobs into analytics_models_usage table
- Runs daily (or as specified)
- Aggregates by model_id, job_type, period_type (daily/weekly/monthly)
- Calculates all metrics (requests, success, failure, tokens, cost, latency)

**Failure Cases & Security Pitfalls:**
- Job fails → Analytics data not updated
- Duplicate aggregation → Data inconsistency (use UPSERT)

**Test Coverage Expectations:**
- Feature test: Models usage returns correct statistics
- Feature test: Success/failure rates are accurate
- Feature test: Average latency is correct
- Feature test: Tokens consumed matches generation_jobs
- Feature test: Date range filtering works
- Feature test: Aggregation job works correctly

---

## Task 6.5: Token Analytics API

### Subtask 6.5.1: Create AdminTokensController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/tokens/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `token_transactions` (aggregate by provider, type)
- Read: `generation_jobs` (for provider/job_type mapping)
- Read: `providers` (for provider names)

**Indexes Used:**
- `token_transactions.type` (for filtering consume transactions)
- `token_transactions.created_at` (for date filtering)
- `token_transactions.generation_job_id` (for joining with generation_jobs)
- `generation_jobs.provider_id` (for provider grouping)
- `generation_jobs.job_type` (for type grouping)

**Caching Decisions:**
- Cache for 15 minutes
- Cache key: `admin:tokens:summary:{date_range}`

**Authorization Rules:**
- Admin-only access

**Acceptance Criteria:**
- Controller exists with summary endpoint
- Returns token analytics data

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- Slow query → Performance issue

---

### Subtask 6.5.2: Implement summary() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (summary method)
- `app/Http/Requests/Api/V1/AdminTokensSummaryRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/tokens/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `token_transactions` (aggregate consumption)
- Read: `generation_jobs` (for provider/job_type mapping)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 15 minutes

**Authorization Rules:**
- Admin-only

**Acceptance Criteria:**
- Returns tokens consumed per provider
- Returns tokens consumed by type (image/video/audio)
- Returns cost and profit per provider
- Returns time-series data (daily/weekly/monthly)
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect aggregation → Data inconsistency
- Mismatch with generation_jobs → Reconciliation error

---

### Subtask 6.5.3: Track Tokens Consumed per Provider
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (provider aggregation logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `token_transactions` (JOIN generation_jobs, GROUP BY provider_id, SUM(amount_tokens) WHERE type='consume')

**Indexes Used:**
- `token_transactions.type` (for filtering consume)
- `token_transactions.generation_job_id` (for joining)
- `generation_jobs.provider_id` (for grouping)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns tokens consumed per provider
- Includes provider name, tokens_consumed
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect JOIN → Data inconsistency
- Missing provider mapping → Null values

---

### Subtask 6.5.4: Track Tokens by Type (Image/Video/Audio)
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (type aggregation logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `token_transactions` (JOIN generation_jobs, GROUP BY job_type, SUM(amount_tokens) WHERE type='consume')

**Indexes Used:**
- `token_transactions.type` (for filtering consume)
- `token_transactions.generation_job_id` (for joining)
- `generation_jobs.job_type` (for grouping)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns tokens consumed by job_type (image/video/audio)
- Includes job_type, tokens_consumed
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect grouping → Data inconsistency
- Missing job_type → Null values

---

### Subtask 6.5.5: Calculate Cost and Profit per Provider
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (cost/profit calculation logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `generation_jobs` (SUM(cost_usd) GROUP BY provider_id WHERE status='completed')
- Read: `orders` (SUM(price_usd) for revenue per provider - complex, may need token consumption mapping)

**Indexes Used:**
- `generation_jobs.provider_id` (for grouping)
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.cost_usd` (for summing)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns cost_usd per provider (from generation_jobs)
- Returns profit per provider (revenue - cost, if revenue can be calculated)
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect cost calculation → Financial error
- Revenue calculation complex → May need approximation

---

### Subtask 6.5.6: Add Time-Series Data
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (time-series logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `token_transactions` (GROUP BY DATE(created_at), provider_id/job_type, SUM(amount_tokens))

**Indexes Used:**
- `token_transactions.created_at` (for date grouping)
- All previous indexes

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns daily/weekly/monthly token consumption trends
- Returns array of {date, tokens_consumed, cost_usd} per provider/type
- Gaps filled with zeros (complete time series)
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect date grouping → Data inconsistency
- Missing dates → Charting issues

**Test Coverage Expectations:**
- Feature test: Token analytics returns correct data
- Feature test: Tokens consumed per provider is accurate
- Feature test: Tokens consumed by type is accurate
- Feature test: Cost per provider is correct
- Feature test: Time-series data is correct
- Feature test: Date range filtering works

---

## Task 6.6: Cost & Profit Dashboard API

### Subtask 6.6.1: Create AdminCostProfitController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/cost-profit/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `orders` (for revenue - SUM(price_usd) WHERE status='paid')
- Read: `generation_jobs` (for costs - SUM(cost_usd) WHERE status='completed')

**Indexes Used:**
- `orders.status` (for filtering paid orders)
- `orders.created_at` (for date filtering)
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.created_at` (for date filtering)
- `generation_jobs.model_id` (for model breakdown)
- `generation_jobs.provider_id` (for provider breakdown)

**Caching Decisions:**
- Cache for 10 minutes
- Cache key: `admin:cost-profit:summary:{date_range}`

**Authorization Rules:**
- Admin-only access

**Acceptance Criteria:**
- Controller exists with summary endpoint
- Returns cost and profit data

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- Incorrect financial calculations → Financial error

---

### Subtask 6.6.2: Implement summary() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (summary method)
- `app/Http/Requests/Api/V1/AdminCostProfitSummaryRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/cost-profit/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `orders` (revenue calculation)
- Read: `generation_jobs` (cost calculation)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 10 minutes

**Authorization Rules:**
- Admin-only

**Acceptance Criteria:**
- Returns total_revenue_usd, total_cost_usd, total_profit_usd
- Returns profit_margin (profit / revenue * 100)
- Returns breakdown by model (revenue, cost, profit per model)
- Returns breakdown by provider (revenue, cost, profit per provider)
- Returns time-series data (profit margins over time)
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect revenue (includes non-paid) → Financial error
- Incorrect cost (includes failed jobs) → Financial error
- Profit calculation error → Financial reporting error

---

### Subtask 6.6.3: Calculate COGS per Model
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (COGS calculation logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `generation_jobs` (SUM(cost_usd) GROUP BY model_id WHERE status='completed')

**Indexes Used:**
- `generation_jobs.model_id` (for grouping)
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.cost_usd` (for summing)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns cost of goods sold (COGS) per model
- Only includes completed jobs
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Includes failed jobs → Cost error
- Incorrect grouping → Data inconsistency

---

### Subtask 6.6.4: Calculate Profit per Model
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (profit calculation logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `generation_jobs` (for costs per model)
- Read: `token_transactions` (for revenue per model - complex mapping needed)

**Indexes Used:**
- All previous indexes

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns profit per model (revenue - cost)
- Revenue calculated from token consumption (if possible)
- Cost from generation_jobs
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Revenue calculation complex → May need approximation
- Incorrect profit → Financial error

---

### Subtask 6.6.5: Track Profit Margins Over Time
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (margin tracking logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `orders` (GROUP BY DATE(created_at), SUM(price_usd))
- Read: `generation_jobs` (GROUP BY DATE(created_at), SUM(cost_usd))

**Indexes Used:**
- `orders.created_at` (for date grouping)
- `generation_jobs.created_at` (for date grouping)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns profit margins over time (daily/weekly/monthly)
- Returns array of {date, revenue_usd, cost_usd, profit_usd, profit_margin}
- Gaps filled with zeros
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect margin calculation → Financial error
- Date misalignment → Data inconsistency

---

### Subtask 6.6.6: Break Down by Bundle/Campaign
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (bundle breakdown logic, optional)

**Routes & Middleware:**
- N/A (handled in summary method, or separate endpoint)

**DB Reads/Writes:**
- Read: `orders` (GROUP BY token_bundle_id, SUM(price_usd) WHERE status='paid')
- Read: `token_bundles` (for bundle names)

**Indexes Used:**
- `orders.token_bundle_id` (for grouping)
- `orders.status` (for filtering paid)

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns revenue per bundle
- Returns profit per bundle (if cost can be allocated)
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Cost allocation complex → May not be feasible
- Incorrect breakdown → Data inconsistency

---

### Subtask 6.6.7: Add Historical Comparison
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (comparison logic)

**Routes & Middleware:**
- N/A (handled in summary method)

**DB Reads/Writes:**
- Read: `orders` (compare current period vs previous period)
- Read: `generation_jobs` (compare current period vs previous period)

**Indexes Used:**
- All previous indexes

**Caching Decisions:**
- None (part of summary response)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns current period metrics
- Returns previous period metrics (same duration, shifted back)
- Returns growth rates (percentage change)
- Supports date range filtering

**Failure Cases & Security Pitfalls:**
- Incorrect period calculation → Comparison error
- Division by zero → Error handling needed

**Test Coverage Expectations:**
- Feature test: Cost-profit summary returns correct data
- Feature test: Revenue calculation is accurate (only paid orders)
- Feature test: Cost calculation is accurate (only completed jobs)
- Feature test: Profit calculation is correct (revenue - cost)
- Feature test: Profit margins are accurate
- Feature test: Breakdown by model/provider is correct
- Feature test: Historical comparison works
- Feature test: Date range filtering works

---

## Task 6.7: System Health API

### Subtask 6.7.1: Create AdminSystemHealthController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/system-health` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `generation_jobs` (for queue length, latency, error rates)
- Read: `failed_jobs` (Laravel's failed_jobs table)
- Read: Redis (for queue length, if using Redis queue)

**Indexes Used:**
- `generation_jobs.status` (for queue length - pending jobs)
- `generation_jobs.created_at` (for recent jobs)
- `generation_jobs.completed_at` (for latency calculation)
- `failed_jobs.failed_at` (for failed jobs count)

**Caching Decisions:**
- Cache for 1 minute (system health changes frequently)
- Cache key: `admin:system-health`

**Authorization Rules:**
- Admin-only access

**Acceptance Criteria:**
- Controller exists with index endpoint
- Returns system health metrics

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- Slow query → Performance issue

---

### Subtask 6.7.2: Implement index() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (index method)

**Routes & Middleware:**
- GET `/api/v1/admin/system-health` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `generation_jobs` (queue length, latency)
- Read: `failed_jobs` (failed jobs count)
- Read: Redis (queue length)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 1 minute

**Authorization Rules:**
- Admin-only

**Acceptance Criteria:**
- Returns queue_length (pending jobs count)
- Returns worker_status (if possible)
- Returns failed_jobs_count
- Returns error_rate (failed / total in last 24h)
- Returns avg_api_latency_ms
- Returns storage_usage (if available)

**Failure Cases & Security Pitfalls:**
- Incorrect queue length → Operational error
- Missing metrics → Incomplete monitoring

---

### Subtask 6.7.3: Track Queue Lengths
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (queue length logic)

**Routes & Middleware:**
- N/A (handled in index method)

**DB Reads/Writes:**
- Read: `generation_jobs` (COUNT(*) WHERE status='pending')
- Read: Redis (LLEN queue:default, if using Redis)

**Indexes Used:**
- `generation_jobs.status` (for filtering pending)

**Caching Decisions:**
- None (real-time metric)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns queue_length (number of pending jobs)
- Returns queue_length_by_type (pending jobs by job_type)
- Real-time data (not cached)

**Failure Cases & Security Pitfalls:**
- Incorrect count → Operational error
- Slow query → Performance issue

---

### Subtask 6.7.4: Monitor Worker Status
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (worker status logic, optional)

**Routes & Middleware:**
- N/A (handled in index method)

**DB Reads/Writes:**
- Read: System/Redis (check if workers are running, if possible)

**Indexes Used:**
- None

**Caching Decisions:**
- Cache for 5 minutes (worker status doesn't change frequently)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns worker_status (active/inactive, if detectable)
- Returns worker_count (number of active workers, if detectable)
- If not detectable, return null or "unknown"

**Failure Cases & Security Pitfalls:**
- Cannot detect worker status → Return null (don't throw error)
- Incorrect status → Operational error

---

### Subtask 6.7.5: Track Failed Jobs
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (failed jobs logic)

**Routes & Middleware:**
- N/A (handled in index method)

**DB Reads/Writes:**
- Read: `failed_jobs` (COUNT(*) WHERE failed_at >= last 24h)

**Indexes Used:**
- `failed_jobs.failed_at` (for date filtering)

**Caching Decisions:**
- None (real-time metric)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns failed_jobs_count (last 24 hours)
- Returns failed_jobs_today (today's failed jobs)
- Returns recent_failed_jobs (last 10 failed jobs with details)

**Failure Cases & Security Pitfalls:**
- Incorrect count → Operational error
- Missing failed_jobs table → Error handling needed

---

### Subtask 6.7.6: Calculate Error Rates
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (error rate logic)

**Routes & Middleware:**
- N/A (handled in index method)

**DB Reads/Writes:**
- Read: `generation_jobs` (COUNT(*) WHERE status='failed' / COUNT(*) WHERE created_at >= last 24h)

**Indexes Used:**
- `generation_jobs.status` (for filtering failed)
- `generation_jobs.created_at` (for date filtering)

**Caching Decisions:**
- None (real-time metric)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns error_rate (failed / total * 100 in last 24h)
- Returns error_count (number of failed jobs in last 24h)
- Returns total_jobs (total jobs in last 24h)
- Handles division by zero (if total_jobs = 0)

**Failure Cases & Security Pitfalls:**
- Division by zero → Error handling needed
- Incorrect rate → Operational error

---

### Subtask 6.7.7: Monitor API Latency
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (latency logic)

**Routes & Middleware:**
- N/A (handled in index method)

**DB Reads/Writes:**
- Read: `generation_jobs` (AVG(completed_at - started_at) WHERE status='completed' AND completed_at >= last 24h)

**Indexes Used:**
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.completed_at` (for date filtering)
- `generation_jobs.started_at` (for latency calculation)

**Caching Decisions:**
- None (real-time metric)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns avg_api_latency_ms (average latency in milliseconds)
- Returns p95_latency_ms (95th percentile, if possible)
- Returns p99_latency_ms (99th percentile, if possible)
- Only includes completed jobs with started_at and completed_at

**Failure Cases & Security Pitfalls:**
- Missing started_at/completed_at → Incorrect latency
- Includes failed jobs → Latency error

---

### Subtask 6.7.8: Track Storage Usage
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (storage logic, optional)

**Routes & Middleware:**
- N/A (handled in index method)

**DB Reads/Writes:**
- Read: `generation_jobs` (COUNT(*) WHERE result_url IS NOT NULL, estimate storage)
- Read: S3 API (if available, get actual storage usage)

**Indexes Used:**
- `generation_jobs.result_url` (for counting files)

**Caching Decisions:**
- Cache for 1 hour (storage doesn't change frequently)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Returns storage_usage_mb (estimated or actual storage in MB)
- Returns file_count (number of generated files)
- If not available, return null or "unknown"

**Failure Cases & Security Pitfalls:**
- Cannot access S3 → Return null (don't throw error)
- Incorrect estimation → Operational error

---

### Subtask 6.7.9: Create Scheduled Job to Record Metrics
**Files to Create/Modify:**
- `app/Console/Commands/RecordSystemHealth.php` (new - scheduled job)
- `routes/console.php` (schedule job)

**Routes & Middleware:**
- N/A (scheduled job)

**DB Reads/Writes:**
- Read: `generation_jobs` (collect metrics)
- Read: `failed_jobs` (collect metrics)
- Write: `system_health` table (if exists, INSERT metrics)

**Indexes Used:**
- All indexes from previous subtasks

**Caching Decisions:**
- None (background job)

**Authorization Rules:**
- N/A (system job)

**Acceptance Criteria:**
- Job records system health metrics every 5 minutes (or as specified)
- Records: queue_length, failed_jobs_count, error_rate, avg_latency_ms
- Stores in system_health table (if exists) or logs metrics
- Job runs successfully without errors

**Failure Cases & Security Pitfalls:**
- Job fails → Metrics not recorded (log error, don't crash)
- Missing system_health table → Log metrics instead (or create table)

**Test Coverage Expectations:**
- Feature test: System health returns correct metrics
- Feature test: Queue length is accurate
- Feature test: Failed jobs count is correct
- Feature test: Error rate calculation is accurate
- Feature test: API latency is correct
- Feature test: Scheduled job records metrics correctly

---

## Summary

**Total Files to Create:** 20+
- 7 Controllers (AdminSalesController, AdminUsersController, AdminModelsController, AdminTokensController, AdminCostProfitController, AdminSystemHealthController)
- 7 Form Requests (one per controller for validation)
- 2 Scheduled Jobs (AggregateModelsUsage, RecordSystemHealth)
- Optional Services (AdminSalesService, AdminUsersService for complex logic)

**Total Files to Modify:** 3
- routes/api.php (add admin dashboard routes)
- routes/console.php (add scheduled jobs)
- BACKEND_TASKS.md (mark tasks complete)

**Dependencies:**
- Environment variables: None new
- Packages: None new (use existing Laravel features)
- Infrastructure: Redis (for queue monitoring, if applicable)

**Authorization Checklist:**
- [ ] All admin endpoints protected with auth:sanctum + admin middleware
- [ ] Non-admin users receive 403 Forbidden
- [ ] No data leakage in error messages

**Performance Checklist:**
- [ ] All queries use indexes
- [ ] Date filtering uses indexed columns
- [ ] Aggregations use database-level functions (SUM, COUNT, AVG)
- [ ] Pagination on all list endpoints
- [ ] Caching on summary endpoints (5-15 minute TTL)

**Accuracy Checklist:**
- [ ] Revenue only includes paid orders
- [ ] Costs only include completed jobs
- [ ] Token consumption matches generation_jobs
- [ ] DAU/WAU/MAU definitions consistent
- [ ] Profit = Revenue - Cost (correct calculation)

**Test Coverage Minimum:**
- 5+ feature tests per controller (authorization, data accuracy, date filtering)
- Performance tests for slow queries
- Security tests for access control
