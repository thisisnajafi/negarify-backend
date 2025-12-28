# Phase 6: Admin Dashboard APIs - Admin Analytics Understanding

## Data Requirements & Why

### 1. Sales Dashboard Data
- **Total Revenue**: Sum of all paid orders (status='paid') - financially sensitive
- **Revenue by Period**: Daily/weekly/monthly breakdown for trend analysis
- **Top Selling Bundles**: Which token bundles sell most (informs pricing strategy)
- **Refunds**: Track chargebacks and refunds (financial risk monitoring)
- **Customer LTV**: Lifetime value per customer (business intelligence)
- **Why**: Admins need to understand revenue trends, identify best-selling products, and monitor financial health

### 2. Users Dashboard Data
- **DAU/WAU/MAU**: Daily/Weekly/Monthly Active Users (engagement metrics)
  - DAU: Users who logged in or generated content in last 24 hours
  - WAU: Users active in last 7 days
  - MAU: Users active in last 30 days
- **Top Users by Generation**: Power users who generate most content
- **Top Users by Spending**: High-value customers (revenue focus)
- **Cohort Analysis**: User retention by signup cohort
- **Churn/Reactivation**: Users who stopped using vs returned
- **Why**: Understand user engagement, identify power users, track retention

### 3. Models Usage Dashboard Data
- **Requests per Model**: How often each AI model is used
- **Success/Failure Rates**: Model reliability metrics
- **Average Latency**: Response time per model (performance monitoring)
- **Tokens Consumed**: Cost tracking per model
- **Cost/Revenue per Model**: Profitability analysis
- **Why**: Optimize model selection, identify failing models, track costs

### 4. Token Analytics Dashboard Data
- **Consumption by Provider**: Which AI provider consumes most tokens
- **Consumption by Type**: Image vs video vs audio usage patterns
- **Cost/Profit per Provider**: Provider profitability
- **Time-Series Trends**: Token consumption over time
- **Why**: Understand usage patterns, optimize provider selection, track costs

### 5. Cost & Profit Dashboard Data
- **Total Revenue**: Sum of all paid orders
- **Total Cost**: Sum of all provider costs (from generation jobs)
- **Profit**: Revenue - Cost (financially sensitive)
- **Profit Margins**: Profit as percentage of revenue
- **Breakdown by Model/Provider**: Where profit comes from
- **Historical Comparison**: Profit trends over time
- **Why**: Financial health monitoring, identify profitable/unprofitable models

### 6. System Health Dashboard Data
- **Queue Length**: Number of pending jobs (system load)
- **Worker Status**: Are queue workers running?
- **Failed Jobs**: Error rate monitoring
- **API Latency**: Response time metrics
- **Storage Usage**: Disk/bandwidth consumption
- **Why**: Monitor system performance, identify bottlenecks, prevent outages

## Financially Sensitive Metrics

### Critical Financial Numbers (Must Be Accurate)
1. **Total Revenue**: Sum of paid orders only (status='paid')
2. **Total Cost**: Sum of provider costs from generation jobs
3. **Profit**: Revenue - Cost (must match financial records)
4. **Refunds**: Chargeback and refund amounts
5. **Customer LTV**: Lifetime value calculations
6. **Token Consumption Costs**: Provider costs per token

### Financial Accuracy Requirements
- All financial calculations must use database transactions
- Revenue must only count paid orders (not pending/cancelled)
- Cost must match actual provider charges
- Profit calculations must be auditable
- Date filtering must respect timezone (UTC recommended)
- Rounding must be consistent (4 decimal places for USD, 2 for Toman)

## Date Filtering Behaviors

### Range Parameter (Quick Filters)
- `range=day`: Last 24 hours
- `range=week`: Last 7 days
- `range=month`: Last 30 days
- `range=year`: Last 365 days
- Default: `range=month` (last 30 days)

### Explicit Date Filtering
- `start_date`: YYYY-MM-DD format (inclusive)
- `end_date`: YYYY-MM-DD format (inclusive)
- If both provided, use explicit dates (ignore range)
- Timezone: All dates stored in UTC, queries use UTC

### Date Filtering Logic
```php
if ($request->has('start_date') && $request->has('end_date')) {
    // Use explicit dates
    $start = Carbon::parse($request->start_date)->startOfDay();
    $end = Carbon::parse($request->end_date)->endOfDay();
} else {
    // Use range parameter
    $range = $request->get('range', 'month');
    $end = now();
    $start = match($range) {
        'day' => $end->copy()->subDay(),
        'week' => $end->copy()->subWeek(),
        'month' => $end->copy()->subMonth(),
        'year' => $end->copy()->subYear(),
        default => $end->copy()->subMonth(),
    };
}
```

## Accuracy vs Performance Tradeoffs

### Raw Queries (High Accuracy, Lower Performance)
- **Use When**: Real-time data required, small datasets, financial reports
- **Approach**: Direct SQL aggregations (SUM, COUNT, GROUP BY)
- **Performance Risk**: Full table scans on large tables
- **Mitigation**: Ensure indexes on date columns, status columns

### Aggregated Tables (Lower Accuracy, Higher Performance)
- **Use When**: Historical data, large datasets, dashboard views
- **Approach**: Pre-aggregated data in analytics tables (analytics_models_usage, etc.)
- **Performance Risk**: Stale data if aggregation job fails
- **Mitigation**: Scheduled aggregation jobs, fallback to raw queries

### Hybrid Approach (Recommended)
- **Real-time**: Use raw queries for current period (last 24 hours)
- **Historical**: Use aggregated tables for older data (last 7+ days)
- **Fallback**: If aggregated data missing, use raw queries

## Avoiding Expensive Full-Table Scans

### Index Requirements
- **Date Columns**: All date filters must have indexes
  - `orders.created_at` (indexed)
  - `generation_jobs.created_at` (indexed)
  - `token_transactions.created_at` (indexed)
- **Status Columns**: Filter by status before aggregating
  - `orders.status` (indexed, filter status='paid')
  - `generation_jobs.status` (indexed, filter status='completed')
- **Foreign Keys**: Join columns must be indexed
  - `orders.user_id` (indexed)
  - `generation_jobs.model_id` (indexed)
  - `token_transactions.provider_id` (indexed)

### Query Optimization Strategies
1. **Filter First**: Apply date/status filters before aggregating
2. **Limit Results**: Use pagination for large result sets
3. **Eager Loading**: Avoid N+1 queries with `with()`
4. **Select Specific Columns**: Don't select `*` if not needed
5. **Use Aggregated Tables**: Pre-aggregated data for historical periods
6. **Cache Results**: Cache expensive queries (5-10 minute TTL)

### Performance Monitoring
- Monitor query execution time (target: <500ms per endpoint)
- Use `EXPLAIN` to verify index usage
- Log slow queries (>1 second)
- Consider materialized views for complex aggregations

## Authorization Model & RBAC

### Role Hierarchy
- **Admin**: Full access to all admin endpoints
- **Moderator**: Limited admin access (if documented)
- **User**: No admin access (403 Forbidden)

### Authorization Checks
- **Middleware**: All admin routes protected by `auth:sanctum` + `admin` middleware
- **Controller Level**: Additional `$user->isAdmin()` checks for sensitive operations
- **Policy/Gate**: Use Laravel policies for fine-grained control (if needed)

### Unauthorized Access Response
```json
{
  "success": false,
  "message": "Access denied. Admin privileges required."
}
```
Status Code: 403 Forbidden

### Security Risks
- **Leaking Admin Data**: Non-admins must not see any admin metrics
- **Financial Data Exposure**: Revenue/cost/profit must be admin-only
- **User Privacy**: User search must respect privacy (admin-only)

### Mitigations
- Strict middleware enforcement (fail closed)
- Explicit role checks in controllers
- No admin data in error messages
- Audit logging for admin actions

## Query Patterns & Aggregations

### Revenue Calculation
```sql
SELECT 
    SUM(total_amount_toman) as total_revenue_toman,
    SUM(total_amount_usd) as total_revenue_usd,
    COUNT(*) as total_orders
FROM orders
WHERE status = 'paid'
    AND created_at >= ? AND created_at <= ?
```

### Token Consumption by Provider
```sql
SELECT 
    p.name as provider_name,
    SUM(gj.tokens_consumed) as tokens_consumed,
    SUM(gj.cost_usd) as cost_usd
FROM generation_jobs gj
JOIN providers p ON gj.provider_id = p.id
WHERE gj.status = 'completed'
    AND gj.created_at >= ? AND gj.created_at <= ?
GROUP BY p.id, p.name
```

### DAU/WAU/MAU Calculation
```sql
-- DAU: Users active in last 24 hours
SELECT COUNT(DISTINCT user_id) as dau
FROM (
    SELECT user_id FROM generation_jobs WHERE created_at >= NOW() - INTERVAL 24 HOUR
    UNION
    SELECT id as user_id FROM users WHERE last_login_at >= NOW() - INTERVAL 24 HOUR
) as active_users

-- WAU: Users active in last 7 days
-- MAU: Users active in last 30 days
```

## Caching Strategy

### Cacheable Endpoints
- Sales summary (5 minute TTL)
- Users summary (5 minute TTL)
- Models usage (10 minute TTL)
- Token analytics (10 minute TTL)
- Cost/profit summary (5 minute TTL)
- System health (1 minute TTL - more frequent updates)

### Cache Keys
- `admin:sales:summary:{range}:{start_date}:{end_date}`
- `admin:users:summary:{range}`
- `admin:models:usage:{range}:{start_date}:{end_date}`
- `admin:tokens:analytics:{range}:{start_date}:{end_date}`
- `admin:cost-profit:summary:{range}:{start_date}:{end_date}`
- `admin:system:health`

### Cache Invalidation
- Invalidate on new orders (sales cache)
- Invalidate on new generation jobs (models/tokens cache)
- Invalidate on system health updates (health cache)
- TTL-based expiration (fallback)

## Performance Targets

### Response Time Targets
- Sales summary: <300ms
- Users summary: <500ms
- Models usage: <500ms
- Token analytics: <500ms
- Cost/profit: <300ms
- System health: <200ms

### Query Count Targets
- Maximum 5 queries per endpoint
- Use eager loading to prevent N+1
- Prefer single aggregation query over multiple queries

### Data Volume Considerations
- Paginate large result sets (15-50 items per page)
- Limit time ranges for expensive queries (max 1 year)
- Use aggregated tables for historical data (>30 days)

## Error Handling

### Financial Calculation Errors
- If calculation fails, return error (don't return incorrect data)
- Log all financial calculation errors
- Provide fallback to cached data if available

### Query Timeout Handling
- Set query timeout (30 seconds)
- Return cached data if query times out
- Log timeout errors for investigation

### Missing Data Handling
- Return 0 for missing metrics (not null)
- Document which metrics may be unavailable
- Provide data availability status in response

## Testing Considerations

### Financial Accuracy Tests
- Verify revenue matches sum of paid orders
- Verify cost matches sum of generation job costs
- Verify profit = revenue - cost
- Test with edge cases (no orders, all refunds, etc.)

### Performance Tests
- Test with large datasets (10k+ orders, 100k+ generation jobs)
- Verify indexes are used (EXPLAIN queries)
- Test query timeout handling
- Test cache hit/miss scenarios

### Authorization Tests
- Verify non-admin cannot access any admin endpoint
- Verify admin can access all endpoints
- Test middleware enforcement
- Test role-based filtering (if moderators exist)

