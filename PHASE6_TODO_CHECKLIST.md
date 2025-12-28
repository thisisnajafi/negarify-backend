# Phase 6: Admin Dashboard APIs - TODO Checklist

## Task 6.1: Admin Authentication & Middleware

### Subtask 6.1.1: Verify AdminMiddleware Exists
**Files to Check/Modify:**
- `app/Http/Middleware/AdminMiddleware.php` (verify exists and is correct)
- `bootstrap/app.php` (verify middleware alias registered)

**Routes & Middleware:**
- All admin routes must use `auth:sanctum` + `admin` middleware

**DB Reads/Writes:**
- Read: `users` (check role='admin')

**Indexes Used:**
- `users.role` (for admin check)

**Caching Decisions:**
- None (auth checks must be real-time)

**Authorization Rules:**
- Only users with `role='admin'` can access admin endpoints
- Non-admin users receive 403 Forbidden

**Acceptance Criteria:**
- AdminMiddleware exists and enforces admin role
- Middleware alias registered in bootstrap/app.php
- All admin routes protected
- Non-admin cannot access any admin endpoint

**Failure Cases & Security Pitfalls:**
- Non-admin accesses admin endpoint → 403 Forbidden
- Middleware not registered → 500 error
- Role check bypassed → Security breach

**Test Coverage Expectations:**
- Feature test: Non-admin cannot access admin endpoints
- Feature test: Admin can access admin endpoints
- Feature test: Unauthenticated user cannot access admin endpoints

---

### Subtask 6.1.2: Add Admin Routes Protection
**Files to Modify:**
- `routes/api.php` (verify all admin routes use middleware)

**Routes & Middleware:**
- All routes under `/api/v1/admin/*` must use `auth:sanctum` + `admin`

**DB Reads/Writes:**
- None (routing only)

**Indexes Used:**
- None

**Caching Decisions:**
- None

**Authorization Rules:**
- All admin routes protected by middleware

**Acceptance Criteria:**
- All admin routes use correct middleware
- No admin routes accessible without authentication
- Consistent 403 response for unauthorized access

**Failure Cases & Security Pitfalls:**
- Admin route without middleware → Security breach
- Inconsistent error responses → User confusion

---

## Task 6.2: Sales Dashboard API

### Subtask 6.2.1: Create AdminSalesController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/sales/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `orders` (WHERE status='paid')
- Read: `token_bundles` (for top bundles)

**Indexes Used:**
- `orders.status` (for filtering paid orders)
- `orders.created_at` (for date filtering)
- `orders.user_id` (for LTV calculation)

**Caching Decisions:**
- Cache summary for 5 minutes
- Cache key: `admin:sales:summary:{range}:{start_date}:{end_date}`
- Invalidate on new paid order

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Controller exists with summary endpoint
- Returns sales data in expected format
- Date filtering works correctly

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- Incorrect revenue calculation → Financial inaccuracy
- Missing date filter → Performance issue

---

### Subtask 6.2.2: Implement summary() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (summary method)
- `app/Http/Requests/Api/V1/SalesSummaryRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/sales/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `orders` (aggregate paid orders)
- Read: `token_bundles` (for top bundles)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 5 minutes

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Returns total revenue (toman and USD)
- Returns total orders count
- Returns revenue by day/week/month
- Returns top selling bundles
- Returns refunds data
- Returns customer LTV
- Date filtering works (range or explicit dates)

**Failure Cases & Security Pitfalls:**
- Revenue includes non-paid orders → Financial inaccuracy
- Date filtering incorrect → Wrong metrics
- Missing refunds → Incomplete data

**Test Coverage Expectations:**
- Feature test: Summary returns correct revenue
- Feature test: Date filtering works
- Feature test: Top bundles correct
- Feature test: LTV calculation correct

---

### Subtask 6.2.3: Calculate Revenue by Day/Week/Month
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (revenue breakdown logic)
- `app/Services/AdminSalesService.php` (new - optional service)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `orders` (GROUP BY date period)

**Indexes Used:**
- `orders.created_at` (for date grouping)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Revenue by day: Daily breakdown
- Revenue by week: Weekly breakdown
- Revenue by month: Monthly breakdown
- Time-series data in chronological order

**Failure Cases & Security Pitfalls:**
- Incorrect date grouping → Wrong metrics
- Missing dates in series → Incomplete data

---

### Subtask 6.2.4: Calculate Top Selling Bundles
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (top bundles logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `orders` (JOIN token_bundles, GROUP BY bundle)
- Read: `token_bundles` (for bundle details)

**Indexes Used:**
- `orders.token_bundle_id` (for grouping)
- `orders.status` (for filtering paid)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Top bundles sorted by order count or revenue
- Includes bundle name, order count, revenue
- Limited to top 10 bundles

**Failure Cases & Security Pitfalls:**
- Incorrect sorting → Wrong top bundles
- Missing bundle data → Incomplete response

---

### Subtask 6.2.5: Track Refunds
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (refunds logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `orders` (WHERE status='refunded' or similar)
- Read: `token_transactions` (WHERE type='refund')

**Indexes Used:**
- `orders.status` (for refunded orders)
- `token_transactions.type` (for refund transactions)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Refund count and amount tracked
- Refunds included in revenue calculation (subtracted)
- Refund breakdown by date if required

**Failure Cases & Security Pitfalls:**
- Refunds not tracked → Incomplete financial data
- Refunds not subtracted from revenue → Financial inaccuracy

---

### Subtask 6.2.6: Calculate Customer LTV
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (LTV logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `orders` (GROUP BY user_id, SUM revenue)

**Indexes Used:**
- `orders.user_id` (for grouping)
- `orders.status` (for filtering paid)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Average LTV calculated (total revenue / unique customers)
- Per-customer LTV available if required
- LTV by cohort if required

**Failure Cases & Security Pitfalls:**
- Incorrect LTV calculation → Wrong business metrics
- Missing customer data → Incomplete LTV

---

### Subtask 6.2.7: Add Date Range Filtering
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSalesController.php` (date filtering logic)
- `app/Http/Requests/Api/V1/SalesSummaryRequest.php` (date validation)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `orders` (WHERE created_at BETWEEN start AND end)

**Indexes Used:**
- `orders.created_at` (for date filtering)

**Caching Decisions:**
- Cache key includes date range

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Range parameter works (day/week/month/year)
- Explicit start_date/end_date works
- Date filtering applied to all metrics
- Timezone handled correctly (UTC)

**Failure Cases & Security Pitfalls:**
- Date filtering not applied → Wrong metrics
- Timezone issues → Incorrect date ranges

---

### Subtask 6.2.8: Optimize Queries with Indexes
**Files to Create/Modify:**
- Database migrations (verify indexes exist)
- Query optimization in controller

**Routes & Middleware:**
- N/A (optimization only)

**DB Reads/Writes:**
- Read: `orders` (with optimized queries)

**Indexes Used:**
- All indexes from previous subtasks

**Caching Decisions:**
- None (optimization only)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- All date filters use indexes
- Status filters use indexes
- Queries execute in <300ms
- No full table scans

**Failure Cases & Security Pitfalls:**
- Missing indexes → Slow queries
- Full table scans → Performance degradation

**Test Coverage Expectations:**
- Performance test: Query execution time <300ms
- Verify indexes are used (EXPLAIN queries)

---

## Task 6.3: Users Dashboard API

### Subtask 6.3.1: Create AdminUsersController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/users/summary` - `auth:sanctum`, `admin`
- GET `/api/v1/admin/users/list` - `auth:sanctum`, `admin` (if required)

**DB Reads/Writes:**
- Read: `users` (for user metrics)
- Read: `generation_jobs` (for activity metrics)

**Indexes Used:**
- `users.created_at` (for signup date)
- `generation_jobs.user_id` (for activity)
- `generation_jobs.created_at` (for activity date)

**Caching Decisions:**
- Cache summary for 5 minutes
- Cache key: `admin:users:summary:{range}`

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Controller exists with summary endpoint
- Returns user metrics in expected format

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- User data leaked → Privacy breach

---

### Subtask 6.3.2: Implement summary() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (summary method)
- `app/Http/Requests/Api/V1/UsersSummaryRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/users/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `users` (for DAU/WAU/MAU)
- Read: `generation_jobs` (for activity)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 5 minutes

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Returns DAU/WAU/MAU
- Returns top users by generation count
- Returns top users by spending
- Returns cohort analysis if required
- Returns churn/reactivation metrics if required

**Failure Cases & Security Pitfalls:**
- Incorrect DAU/WAU/MAU calculation → Wrong metrics
- Missing user data → Incomplete metrics

**Test Coverage Expectations:**
- Feature test: DAU/WAU/MAU correct
- Feature test: Top users correct
- Feature test: Cohort analysis correct

---

### Subtask 6.3.3: Calculate DAU/WAU/MAU
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (DAU/WAU/MAU logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `users` (for login activity)
- Read: `generation_jobs` (for generation activity)

**Indexes Used:**
- `generation_jobs.created_at` (for activity date)
- `users.last_login_at` (if exists, for login activity)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- DAU: Users active in last 24 hours
- WAU: Users active in last 7 days
- MAU: Users active in last 30 days
- Activity = login OR generation job creation

**Failure Cases & Security Pitfalls:**
- Incorrect date ranges → Wrong metrics
- Missing activity data → Incomplete metrics

---

### Subtask 6.3.4: Get Top Users by Generation Count
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (top users logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `generation_jobs` (GROUP BY user_id, COUNT)

**Indexes Used:**
- `generation_jobs.user_id` (for grouping)
- `generation_jobs.created_at` (for date filtering)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Top users sorted by generation count
- Includes user name, generation count
- Limited to top 10 users
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect sorting → Wrong top users
- Missing user data → Incomplete response

---

### Subtask 6.3.5: Get Top Users by Spending
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (top spenders logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `orders` (GROUP BY user_id, SUM revenue)

**Indexes Used:**
- `orders.user_id` (for grouping)
- `orders.status` (for filtering paid)
- `orders.created_at` (for date filtering)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Top users sorted by total spending
- Includes user name, total spending
- Limited to top 10 users
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect sorting → Wrong top users
- Missing order data → Incomplete response

---

### Subtask 6.3.6: Implement Cohort Analysis
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (cohort logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint, optional)

**DB Reads/Writes:**
- Read: `users` (GROUP BY signup month)
- Read: `generation_jobs` (for retention)

**Indexes Used:**
- `users.created_at` (for signup date)
- `generation_jobs.user_id` (for activity)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Cohort analysis by signup month
- Retention rates per cohort
- Activity rates per cohort

**Failure Cases & Security Pitfalls:**
- Incorrect cohort calculation → Wrong metrics
- Missing retention data → Incomplete analysis

---

### Subtask 6.3.7: Track Churn and Reactivation
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (churn logic, optional)

**Routes & Middleware:**
- N/A (handled in summary endpoint, optional)

**DB Reads/Writes:**
- Read: `users` (for last activity)
- Read: `generation_jobs` (for activity)

**Indexes Used:**
- `generation_jobs.user_id` (for activity)
- `generation_jobs.created_at` (for activity date)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Churn: Users inactive for 30+ days
- Reactivation: Users active after 30+ days inactive
- Churn/reactivation rates calculated

**Failure Cases & Security Pitfalls:**
- Incorrect churn calculation → Wrong metrics
- Missing activity data → Incomplete analysis

---

### Subtask 6.3.8: Add User Search and Filtering
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminUsersController.php` (list method)
- `app/Http/Requests/Api/V1/UsersListRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/users/list` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `users` (with search/filter)

**Indexes Used:**
- `users.name` (for name search)
- `users.phone` (for phone search)
- `users.email` (for email search)
- `users.created_at` (for date filtering)

**Caching Decisions:**
- None (search results not cacheable)

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- User search by name/phone/email
- Pagination works
- Filtering by date/role works
- Results ordered correctly

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- SQL injection risk → Security breach (use parameterized queries)

**Test Coverage Expectations:**
- Feature test: User search works
- Feature test: Pagination works
- Feature test: Filtering works

---

## Task 6.4: Models Usage Dashboard API

### Subtask 6.4.1: Create AdminModelsController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/models/usage` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `analytics_models_usage` (preferred)
- Read: `generation_jobs` (fallback if analytics table empty)
- Read: `models` (for model details)

**Indexes Used:**
- `analytics_models_usage.model_id` (for model filtering)
- `analytics_models_usage.period_start` (for date filtering)
- `generation_jobs.model_id` (for fallback queries)
- `generation_jobs.created_at` (for date filtering)

**Caching Decisions:**
- Cache usage for 10 minutes
- Cache key: `admin:models:usage:{range}:{start_date}:{end_date}`

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Controller exists with usage endpoint
- Returns model usage data in expected format

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- Incorrect usage data → Wrong metrics

---

### Subtask 6.4.2: Implement usage() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (usage method)
- `app/Http/Requests/Api/V1/ModelsUsageRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/models/usage` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `analytics_models_usage` or `generation_jobs`
- Read: `models` (for model details)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 10 minutes

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Returns requests per model
- Returns success/failure rates
- Returns average latency
- Returns tokens consumed
- Returns cost/revenue per model
- Date filtering works

**Failure Cases & Security Pitfalls:**
- Incorrect aggregation → Wrong metrics
- Missing model data → Incomplete response

**Test Coverage Expectations:**
- Feature test: Usage data correct
- Feature test: Date filtering works
- Feature test: Aggregation correct

---

### Subtask 6.4.3: Track Requests per Model
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (requests logic)

**Routes & Middleware:**
- N/A (handled in usage endpoint)

**DB Reads/Writes:**
- Read: `analytics_models_usage` (requests_count) or `generation_jobs` (COUNT)

**Indexes Used:**
- `analytics_models_usage.model_id` (for grouping)
- `generation_jobs.model_id` (for fallback grouping)

**Caching Decisions:**
- None (part of usage cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Request count per model
- Grouped by model and job_type
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect counting → Wrong metrics
- Missing requests → Incomplete data

---

### Subtask 6.4.4: Calculate Average Cost per Model
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (cost logic)

**Routes & Middleware:**
- N/A (handled in usage endpoint)

**DB Reads/Writes:**
- Read: `analytics_models_usage` (cost_usd) or `generation_jobs` (SUM cost_usd)

**Indexes Used:**
- Same as previous subtask

**Caching Decisions:**
- None (part of usage cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Average cost per model
- Total cost per model
- Cost breakdown by job_type

**Failure Cases & Security Pitfalls:**
- Incorrect cost calculation → Financial inaccuracy
- Missing cost data → Incomplete metrics

---

### Subtask 6.4.5: Track Tokens Consumed
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (tokens logic)

**Routes & Middleware:**
- N/A (handled in usage endpoint)

**DB Reads/Writes:**
- Read: `analytics_models_usage` (tokens_consumed) or `generation_jobs` (SUM tokens_consumed)

**Indexes Used:**
- Same as previous subtask

**Caching Decisions:**
- None (part of usage cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Total tokens consumed per model
- Tokens breakdown by job_type
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect token count → Wrong metrics
- Missing token data → Incomplete data

---

### Subtask 6.4.6: Calculate Average Latency
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (latency logic)

**Routes & Middleware:**
- N/A (handled in usage endpoint)

**DB Reads/Writes:**
- Read: `analytics_models_usage` (avg_latency_ms) or calculate from `generation_jobs` (started_at, completed_at)

**Indexes Used:**
- Same as previous subtask

**Caching Decisions:**
- None (part of usage cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Average latency per model
- Latency in milliseconds
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect latency calculation → Wrong metrics
- Missing latency data → Incomplete metrics

---

### Subtask 6.4.7: Track Failure Rates
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (failure rates logic)

**Routes & Middleware:**
- N/A (handled in usage endpoint)

**DB Reads/Writes:**
- Read: `analytics_models_usage` (failed_count, successful_count) or `generation_jobs` (COUNT by status)

**Indexes Used:**
- Same as previous subtask
- `generation_jobs.status` (for status filtering)

**Caching Decisions:**
- None (part of usage cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Failure rate per model (failed_count / total_count)
- Success rate per model
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect failure rate → Wrong metrics
- Missing status data → Incomplete metrics

---

### Subtask 6.4.8: Add Date Range Filtering
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminModelsController.php` (date filtering logic)
- `app/Http/Requests/Api/V1/ModelsUsageRequest.php` (date validation)

**Routes & Middleware:**
- N/A (handled in usage endpoint)

**DB Reads/Writes:**
- Read: `analytics_models_usage` or `generation_jobs` (with date filter)

**Indexes Used:**
- `analytics_models_usage.period_start` (for date filtering)
- `generation_jobs.created_at` (for fallback date filtering)

**Caching Decisions:**
- Cache key includes date range

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Range parameter works (day/week/month/year)
- Explicit start_date/end_date works
- Date filtering applied to all metrics

**Failure Cases & Security Pitfalls:**
- Date filtering not applied → Wrong metrics
- Timezone issues → Incorrect date ranges

---

### Subtask 6.4.9: Create Aggregation Job for Analytics (Optional)
**Files to Create/Modify:**
- `app/Console/Commands/AggregateModelsUsage.php` (new file, optional)
- `routes/console.php` (schedule job, optional)

**Routes & Middleware:**
- N/A (scheduled job)

**DB Reads/Writes:**
- Read: `generation_jobs` (aggregate data)
- Write: `analytics_models_usage` (store aggregated data)

**Indexes Used:**
- All indexes from previous subtasks

**Caching Decisions:**
- None (background job)

**Authorization Rules:**
- N/A (system job)

**Acceptance Criteria:**
- Job aggregates generation_jobs into analytics_models_usage
- Job runs daily (or as scheduled)
- Aggregated data matches raw data

**Failure Cases & Security Pitfalls:**
- Job fails → Stale analytics data
- Incorrect aggregation → Wrong metrics

**Test Coverage Expectations:**
- Feature test: Aggregation job works
- Feature test: Aggregated data correct

---

## Task 6.5: Token Analytics API

### Subtask 6.5.1: Create AdminTokensController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/tokens/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `token_transactions` (for consumption)
- Read: `generation_jobs` (for provider/job_type breakdown)
- Read: `providers` (for provider details)

**Indexes Used:**
- `token_transactions.type` (for filtering consumptions)
- `token_transactions.created_at` (for date filtering)
- `generation_jobs.provider_id` (for provider grouping)
- `generation_jobs.job_type` (for type grouping)

**Caching Decisions:**
- Cache summary for 10 minutes
- Cache key: `admin:tokens:analytics:{range}:{start_date}:{end_date}`

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Controller exists with summary endpoint
- Returns token analytics in expected format

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- Incorrect token data → Wrong metrics

---

### Subtask 6.5.2: Implement summary() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (summary method)
- `app/Http/Requests/Api/V1/TokenAnalyticsRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/tokens/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `token_transactions` (for consumption)
- Read: `generation_jobs` (for provider/job_type breakdown)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 10 minutes

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Returns tokens consumed per provider
- Returns tokens by type (image/video/audio)
- Returns cost/profit per provider
- Returns time-series data
- Date filtering works

**Failure Cases & Security Pitfalls:**
- Incorrect aggregation → Wrong metrics
- Missing token data → Incomplete response

**Test Coverage Expectations:**
- Feature test: Token analytics correct
- Feature test: Provider breakdown correct
- Feature test: Time-series data correct

---

### Subtask 6.5.3: Track Tokens Consumed per Provider
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (provider logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `generation_jobs` (GROUP BY provider_id, SUM tokens_consumed)
- Read: `providers` (for provider details)

**Indexes Used:**
- `generation_jobs.provider_id` (for grouping)
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.created_at` (for date filtering)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Tokens consumed per provider
- Provider name included
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect grouping → Wrong metrics
- Missing provider data → Incomplete response

---

### Subtask 6.5.4: Track Tokens by Type (Image/Video/Audio)
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (type logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `generation_jobs` (GROUP BY job_type, SUM tokens_consumed)

**Indexes Used:**
- `generation_jobs.job_type` (for grouping)
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.created_at` (for date filtering)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Tokens consumed by image
- Tokens consumed by video
- Tokens consumed by audio
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect grouping → Wrong metrics
- Missing type data → Incomplete response

---

### Subtask 6.5.5: Calculate Cost and Profit per Provider
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (cost/profit logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `generation_jobs` (SUM cost_usd per provider)
- Read: `orders` (SUM revenue per provider, if provider-specific)

**Indexes Used:**
- `generation_jobs.provider_id` (for grouping)
- `generation_jobs.cost_usd` (for cost calculation)
- `generation_jobs.created_at` (for date filtering)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Cost per provider (from generation_jobs)
- Profit per provider (revenue - cost, if provider-specific revenue available)
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect cost calculation → Financial inaccuracy
- Missing cost data → Incomplete metrics

---

### Subtask 6.5.6: Add Time-Series Data
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminTokensController.php` (time-series logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `generation_jobs` (GROUP BY date, provider/type)

**Indexes Used:**
- `generation_jobs.created_at` (for date grouping)
- All indexes from previous subtasks

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Time-series data by day/week/month
- Tokens consumed over time
- Cost over time
- Chronological order

**Failure Cases & Security Pitfalls:**
- Incorrect date grouping → Wrong metrics
- Missing dates in series → Incomplete data

---

## Task 6.6: Cost & Profit Dashboard API

### Subtask 6.6.1: Create AdminCostProfitController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/cost-profit/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `orders` (for revenue)
- Read: `generation_jobs` (for cost)
- Read: `models` (for model breakdown)
- Read: `providers` (for provider breakdown)

**Indexes Used:**
- `orders.status` (for filtering paid)
- `orders.created_at` (for date filtering)
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.created_at` (for date filtering)
- `generation_jobs.model_id` (for model breakdown)
- `generation_jobs.provider_id` (for provider breakdown)

**Caching Decisions:**
- Cache summary for 5 minutes
- Cache key: `admin:cost-profit:summary:{range}:{start_date}:{end_date}`

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Controller exists with summary endpoint
- Returns cost/profit data in expected format

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- Incorrect financial data → Financial inaccuracy

---

### Subtask 6.6.2: Implement summary() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (summary method)
- `app/Http/Requests/Api/V1/CostProfitSummaryRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/cost-profit/summary` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `orders` (for revenue)
- Read: `generation_jobs` (for cost)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 5 minutes

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Returns total revenue
- Returns total cost
- Returns profit (revenue - cost)
- Returns profit margins
- Returns breakdown by model
- Returns breakdown by provider
- Returns historical comparison
- Date filtering works

**Failure Cases & Security Pitfalls:**
- Incorrect profit calculation → Financial inaccuracy
- Missing financial data → Incomplete metrics

**Test Coverage Expectations:**
- Feature test: Cost/profit calculations correct
- Feature test: Profit = revenue - cost
- Feature test: Breakdown by model/provider correct

---

### Subtask 6.6.3: Calculate COGS per Model
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (COGS logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `generation_jobs` (SUM cost_usd per model)

**Indexes Used:**
- `generation_jobs.model_id` (for grouping)
- `generation_jobs.status` (for filtering completed)
- `generation_jobs.created_at` (for date filtering)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- COGS (Cost of Goods Sold) per model
- Model name included
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect COGS calculation → Financial inaccuracy
- Missing cost data → Incomplete metrics

---

### Subtask 6.6.4: Calculate Profit per Model
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (profit logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `generation_jobs` (for cost per model)
- Read: `orders` (for revenue, if model-specific revenue available)

**Indexes Used:**
- Same as previous subtask

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Profit per model (revenue - cost)
- Profit margin per model
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect profit calculation → Financial inaccuracy
- Missing revenue/cost data → Incomplete metrics

---

### Subtask 6.6.5: Track Profit Margins Over Time
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (margins logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `orders` (for revenue over time)
- Read: `generation_jobs` (for cost over time)

**Indexes Used:**
- `orders.created_at` (for date grouping)
- `generation_jobs.created_at` (for date grouping)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Profit margins by day/week/month
- Margin = (profit / revenue) * 100
- Time-series data in chronological order

**Failure Cases & Security Pitfalls:**
- Incorrect margin calculation → Financial inaccuracy
- Missing time-series data → Incomplete metrics

---

### Subtask 6.6.6: Break Down by Bundle/Campaign
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (bundle breakdown logic, optional)

**Routes & Middleware:**
- N/A (handled in summary endpoint, optional)

**DB Reads/Writes:**
- Read: `orders` (GROUP BY token_bundle_id)

**Indexes Used:**
- `orders.token_bundle_id` (for grouping)
- `orders.status` (for filtering paid)

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Profit breakdown by token bundle
- Bundle name included
- Date filtering applied

**Failure Cases & Security Pitfalls:**
- Incorrect breakdown → Wrong metrics
- Missing bundle data → Incomplete response

---

### Subtask 6.6.7: Add Historical Comparison
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminCostProfitController.php` (historical logic)

**Routes & Middleware:**
- N/A (handled in summary endpoint)

**DB Reads/Writes:**
- Read: `orders` (for previous period revenue)
- Read: `generation_jobs` (for previous period cost)

**Indexes Used:**
- All indexes from previous subtasks

**Caching Decisions:**
- None (part of summary cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Current period vs previous period
- Percentage change calculated
- Historical trends available

**Failure Cases & Security Pitfalls:**
- Incorrect comparison → Wrong metrics
- Missing historical data → Incomplete comparison

---

## Task 6.7: System Health API

### Subtask 6.7.1: Create AdminSystemHealthController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/admin/system/health` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `system_health` (preferred, recent records)
- Read: `jobs` (for queue length)
- Read: `failed_jobs` (for failed jobs count)

**Indexes Used:**
- `system_health.recorded_at` (for recent records)
- `system_health.metric_name` (for metric filtering)
- `jobs.queue` (for queue length)
- `failed_jobs.failed_at` (for failed jobs)

**Caching Decisions:**
- Cache health for 1 minute (frequent updates)
- Cache key: `admin:system:health`

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Controller exists with health endpoint
- Returns system health metrics in expected format

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- Incorrect health metrics → Wrong system status

---

### Subtask 6.7.2: Implement index() Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (index method)

**Routes & Middleware:**
- GET `/api/v1/admin/system/health` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `system_health` (recent records)
- Read: `jobs` (queue length)
- Read: `failed_jobs` (failed jobs count)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache for 1 minute

**Authorization Rules:**
- Admin only

**Acceptance Criteria:**
- Returns queue lengths
- Returns worker status
- Returns failed jobs count
- Returns error rates
- Returns API latency
- Returns storage usage

**Failure Cases & Security Pitfalls:**
- Incorrect metrics → Wrong system status
- Missing health data → Incomplete response

**Test Coverage Expectations:**
- Feature test: Health metrics correct
- Feature test: Queue length accurate
- Feature test: Failed jobs count accurate

---

### Subtask 6.7.3: Track Queue Lengths
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (queue logic)

**Routes & Middleware:**
- N/A (handled in health endpoint)

**DB Reads/Writes:**
- Read: `jobs` (COUNT by queue)

**Indexes Used:**
- `jobs.queue` (for queue grouping)

**Caching Decisions:**
- None (part of health cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Queue length per queue name
- Total queue length
- Real-time or cached (1 minute)

**Failure Cases & Security Pitfalls:**
- Incorrect queue count → Wrong system status
- Missing queue data → Incomplete metrics

---

### Subtask 6.7.4: Monitor Worker Status
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (worker logic)

**Routes & Middleware:**
- N/A (handled in health endpoint)

**DB Reads/Writes:**
- Read: `system_health` (worker status metric) or check queue workers directly

**Indexes Used:**
- `system_health.metric_name` (for worker status)

**Caching Decisions:**
- None (part of health cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Worker status (running/stopped)
- Number of active workers
- Worker health status

**Failure Cases & Security Pitfalls:**
- Incorrect worker status → Wrong system status
- Missing worker data → Incomplete metrics

---

### Subtask 6.7.5: Track Failed Jobs
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (failed jobs logic)

**Routes & Middleware:**
- N/A (handled in health endpoint)

**DB Reads/Writes:**
- Read: `failed_jobs` (COUNT recent failures)

**Indexes Used:**
- `failed_jobs.failed_at` (for recent failures)

**Caching Decisions:**
- None (part of health cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Failed jobs count (last 24 hours)
- Failed jobs rate
- Recent failure trends

**Failure Cases & Security Pitfalls:**
- Incorrect failed jobs count → Wrong system status
- Missing failure data → Incomplete metrics

---

### Subtask 6.7.6: Calculate Error Rates
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (error rates logic)

**Routes & Middleware:**
- N/A (handled in health endpoint)

**DB Reads/Writes:**
- Read: `generation_jobs` (COUNT by status)
- Read: `failed_jobs` (for job failures)

**Indexes Used:**
- `generation_jobs.status` (for status filtering)
- `failed_jobs.failed_at` (for failure date)

**Caching Decisions:**
- None (part of health cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Error rate = failed_count / total_count
- Error rate by time period
- Error trends

**Failure Cases & Security Pitfalls:**
- Incorrect error rate → Wrong system status
- Missing error data → Incomplete metrics

---

### Subtask 6.7.7: Monitor API Latency
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (latency logic)

**Routes & Middleware:**
- N/A (handled in health endpoint)

**DB Reads/Writes:**
- Read: `system_health` (latency metric) or calculate from logs

**Indexes Used:**
- `system_health.metric_name` (for latency metric)

**Caching Decisions:**
- None (part of health cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Average API latency
- P95/P99 latency if available
- Latency trends

**Failure Cases & Security Pitfalls:**
- Incorrect latency → Wrong system status
- Missing latency data → Incomplete metrics

---

### Subtask 6.7.8: Track Storage Usage
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminSystemHealthController.php` (storage logic)

**Routes & Middleware:**
- N/A (handled in health endpoint)

**DB Reads/Writes:**
- Read: `system_health` (storage metric) or calculate from S3

**Indexes Used:**
- `system_health.metric_name` (for storage metric)

**Caching Decisions:**
- None (part of health cache)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Storage usage (GB)
- Bandwidth usage if available
- Storage trends

**Failure Cases & Security Pitfalls:**
- Incorrect storage data → Wrong system status
- Missing storage data → Incomplete metrics

---

### Subtask 6.7.9: Create Scheduled Job to Record Metrics (Optional)
**Files to Create/Modify:**
- `app/Console/Commands/RecordSystemHealth.php` (new file, optional)
- `routes/console.php` (schedule job, optional)

**Routes & Middleware:**
- N/A (scheduled job)

**DB Reads/Writes:**
- Read: `jobs` (for queue length)
- Read: `failed_jobs` (for failed jobs)
- Write: `system_health` (store metrics)

**Indexes Used:**
- All indexes from previous subtasks

**Caching Decisions:**
- None (background job)

**Authorization Rules:**
- N/A (system job)

**Acceptance Criteria:**
- Job records system health metrics
- Job runs every 5 minutes (or as scheduled)
- Metrics stored in system_health table

**Failure Cases & Security Pitfalls:**
- Job fails → Missing health metrics
- Incorrect metrics → Wrong system status

**Test Coverage Expectations:**
- Feature test: Health recording job works
- Feature test: Metrics stored correctly

---

## Summary

**Total Files to Create:** 15+
- 7 Controllers (AdminSalesController, AdminUsersController, AdminModelsController, AdminTokensController, AdminCostProfitController, AdminSystemHealthController)
- 7 Form Requests (SalesSummaryRequest, UsersSummaryRequest, ModelsUsageRequest, TokenAnalyticsRequest, CostProfitSummaryRequest, UsersListRequest)
- 2 Optional Scheduled Jobs (AggregateModelsUsage, RecordSystemHealth)

**Total Files to Modify:** 2
- routes/api.php (add admin dashboard routes)
- routes/console.php (add scheduled jobs if required)

**Dependencies:**
- Environment variables: None new
- Packages: None new
- Infrastructure: Redis (for caching)

**Authorization Checklist:**
- [ ] All admin routes protected by auth:sanctum + admin middleware
- [ ] Non-admin cannot access any admin endpoint
- [ ] Consistent 403 response for unauthorized access

**Financial Accuracy Checklist:**
- [ ] Revenue only counts paid orders
- [ ] Cost matches generation job costs
- [ ] Profit = revenue - cost
- [ ] All financial calculations use transactions
- [ ] Date filtering respects timezone (UTC)

**Performance Checklist:**
- [ ] All date filters use indexes
- [ ] Queries execute in <500ms
- [ ] No full table scans
- [ ] Caching implemented for expensive queries
- [ ] Pagination for large result sets

**Test Coverage Minimum:**
- 5+ feature tests for admin middleware
- 10+ feature tests for sales dashboard
- 10+ feature tests for users dashboard
- 10+ feature tests for models usage
- 8+ feature tests for token analytics
- 8+ feature tests for cost/profit
- 8+ feature tests for system health
- Test authorization correctness
- Test financial accuracy
- Test query performance

