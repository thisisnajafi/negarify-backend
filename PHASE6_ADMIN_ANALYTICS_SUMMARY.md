# Phase 6: Admin Dashboard APIs - Analytics Understanding

## Admin Analytics Requirements (Production-Grade Design)

### 1. What Data Admins Need and Why
- **Sales Metrics**: Revenue tracking, order volumes, top-selling bundles - Critical for business decisions and financial planning
- **User Metrics**: Active users (DAU/WAU/MAU), top users by activity/spending - Understand user engagement and identify power users
- **Model Usage**: Per-model statistics (requests, success rates, latency, costs) - Optimize model selection and cost management
- **Token Analytics**: Consumption by provider/type, cost/profit analysis - Track token economy health and profitability
- **Cost & Profit**: Revenue vs costs, profit margins, breakdowns by model/provider - Financial health and optimization
- **System Health**: Queue lengths, worker status, failed jobs, API latency, storage usage - Operational monitoring and reliability

### 2. Financially Sensitive Metrics
- **Revenue Numbers**: Must be accurate (only count paid orders, correct currency conversion)
- **Cost Calculations**: Must match actual API costs (from generation_jobs.cost_usd)
- **Profit Margins**: Revenue - Cost must be correct (financial reporting depends on this)
- **Token Consumption**: Must match ledger (token_transactions must reconcile)
- **Refunds**: Must be tracked separately and excluded from revenue
- **Customer LTV**: Lifetime value calculations must be accurate for business decisions

### 3. Date Filtering Behaviors
- **Range Parameter**: `range=day|week|month|year|all` - Predefined ranges for common queries
- **Explicit Dates**: `start_date=YYYY-MM-DD&end_date=YYYY-MM-DD` - Custom date ranges
- **Timezone Handling**: All dates in UTC, convert to local timezone for display (or document timezone)
- **Default Behavior**: If no range/date specified, default to last 30 days (or document default)
- **Date Boundaries**: start_date inclusive (>=), end_date inclusive (<=) or exclusive (<) - Document behavior
- **Performance**: Date filtering must use indexed columns (created_at, paid_at, completed_at)

### 4. Accuracy vs Performance Tradeoffs
- **Raw Queries**: Direct aggregation from source tables (orders, token_transactions, generation_jobs) - Accurate but potentially slow
- **Aggregated Tables**: Pre-computed metrics in analytics_models_usage, system_health - Fast but must be kept in sync
- **Hybrid Approach**: Use aggregated tables for historical data, raw queries for recent data (last 24 hours)
- **Caching**: Cache aggregated results for 5-15 minutes (balance freshness vs performance)
- **Scheduled Aggregation**: Daily job to pre-compute metrics (reduces query load during business hours)
- **Real-Time vs Batch**: Real-time for critical metrics (queue length), batch for historical analytics

### 5. Avoiding Expensive Full-Table Scans
- **Indexes Required**: 
  - orders: status, created_at, paid_at, user_id
  - token_transactions: type, created_at, user_id, generation_job_id
  - generation_jobs: status, created_at, completed_at, model_id, provider_id, job_type
  - users: created_at, role
- **Query Patterns**: Always filter by date range first (uses created_at index), then aggregate
- **Limit Aggregations**: Use GROUP BY with date truncation (DATE(created_at)) for time-series
- **Pagination**: All list endpoints must be paginated (max 100 items per page)
- **Selective Columns**: SELECT only needed columns (avoid SELECT *)
- **Eager Loading**: Use with() for relationships, but avoid N+1 queries

### 6. Authorization Model and RBAC
- **Admin-Only Access**: All admin endpoints require `auth:sanctum` + `admin` middleware
- **Role Check**: Verify `user->role === 'admin'` (or `user->isAdmin()` method)
- **Consistent Responses**: 403 Forbidden for non-admin users (don't reveal endpoint existence)
- **Audit Trail**: Log all admin API access (who accessed what, when)
- **No Data Leakage**: Non-admin users should not see any admin data (even in error messages)
- **Moderator Role**: If moderator role exists, document what they can/cannot access

### 7. Query Optimization Strategies
- **Date Range First**: Always filter by date range before aggregating (reduces dataset size)
- **Index Usage**: Ensure queries use indexes (EXPLAIN queries to verify)
- **Batch Aggregation**: Use SUM(), COUNT(), AVG() at database level (not in PHP)
- **Avoid Subqueries**: Prefer JOINs over subqueries where possible
- **Limit Result Sets**: Use LIMIT for top-N queries (top bundles, top users)
- **Materialized Views**: Consider materialized views for complex aggregations (if PostgreSQL)

### 8. Caching Strategy
- **Cache Keys**: `admin:sales:summary:{range}:{start_date}:{end_date}` (include all filter params)
- **TTL**: 5-15 minutes for summary endpoints (balance freshness vs performance)
- **Invalidation**: Invalidate on data changes (new orders, new generations) - or accept stale data
- **Cache Tags**: Use Redis tags for bulk invalidation (e.g., `admin:sales:*`)
- **User-Specific**: Admin endpoints are user-agnostic (same data for all admins), so cache is safe

### 9. Data Accuracy Requirements
- **Revenue**: Only count orders with status='paid' (exclude pending/failed/cancelled)
- **Costs**: Sum from generation_jobs.cost_usd where status='completed' (only successful generations)
- **Token Consumption**: Sum from token_transactions where type='consume' (negative amounts)
- **Refunds**: Track separately (orders with refund status, or token_transactions with type='refund')
- **Reconciliation**: Token consumption must match generation_jobs.tokens_consumed (audit check)

### 10. Time-Series Data
- **Granularity**: Support daily, weekly, monthly aggregations
- **Date Truncation**: Use DATE(created_at) for daily, DATE_FORMAT for weekly/monthly
- **Gap Filling**: Return zeros for dates with no data (complete time series)
- **Ordering**: Always order by date ASC for time-series responses
- **Format**: Return as array of {date, value} objects for easy charting

### 11. Top-N Queries
- **Top Bundles**: Group by token_bundle_id, SUM(price_toman), ORDER BY SUM DESC, LIMIT 10
- **Top Users by Generation**: COUNT(generation_jobs) GROUP BY user_id, ORDER BY COUNT DESC, LIMIT 10
- **Top Users by Spending**: SUM(orders.price_toman) WHERE status='paid' GROUP BY user_id, ORDER BY SUM DESC, LIMIT 10
- **Performance**: Use indexes on grouping columns, limit results to top 10-20

### 12. DAU/WAU/MAU Definitions
- **DAU (Daily Active Users)**: Users who had at least one generation_job with status='completed' in last 24 hours
- **WAU (Weekly Active Users)**: Users who had at least one generation_job with status='completed' in last 7 days
- **MAU (Monthly Active Users)**: Users who had at least one generation_job with status='completed' in last 30 days
- **Alternative**: Could use last_login, but generation activity is more accurate for "active"
- **Consistency**: Use same definition across all endpoints (document clearly)

### 13. Cohort Analysis
- **Cohort Definition**: Group users by signup month/week
- **Retention**: For each cohort, calculate % of users who generated content in subsequent periods
- **Implementation**: Complex query (users JOIN generation_jobs, group by signup period, count active in each period)
- **Performance**: Consider pre-computing in scheduled job (daily aggregation)

### 14. System Health Metrics
- **Queue Length**: Count of pending jobs in queue (Redis LLEN or database COUNT where status='pending')
- **Worker Status**: Check if queue workers are running (Supervisor status, or heartbeat mechanism)
- **Failed Jobs**: COUNT from failed_jobs table (Laravel's failed_jobs table)
- **API Latency**: Average from generation_jobs.completed_at - generation_jobs.started_at (where completed)
- **Storage Usage**: Calculate from S3 (or estimate from generation_jobs count * avg file size)
- **Error Rate**: COUNT(failed) / COUNT(total) from generation_jobs in last 24 hours

### 15. Cost & Profit Calculation
- **Revenue**: SUM(orders.price_toman) WHERE status='paid' (in Toman) or SUM(orders.price_usd) (in USD)
- **Cost**: SUM(generation_jobs.cost_usd) WHERE status='completed' (API costs in USD)
- **Profit**: Revenue - Cost (convert to same currency for comparison)
- **Profit Margin**: (Profit / Revenue) * 100 (percentage)
- **Breakdown by Model**: GROUP BY model_id, calculate revenue (from token consumption) and cost per model
- **Breakdown by Provider**: GROUP BY provider_id, calculate revenue and cost per provider

### 16. Token Analytics
- **Consumption by Provider**: SUM(token_transactions.amount_tokens) WHERE type='consume' GROUP BY provider_id (from generation_jobs)
- **Consumption by Type**: SUM(token_transactions.amount_tokens) WHERE type='consume' GROUP BY job_type (from generation_jobs)
- **Cost per Provider**: SUM(generation_jobs.cost_usd) WHERE status='completed' GROUP BY provider_id
- **Profit per Provider**: Revenue (from token sales) - Cost (from API calls) per provider
- **Time-Series**: Daily/weekly/monthly token consumption trends

### 17. Models Usage Analytics
- **Requests per Model**: COUNT(generation_jobs) WHERE status IN ('completed', 'failed') GROUP BY model_id
- **Success Rate**: COUNT(completed) / COUNT(total) * 100 per model
- **Average Latency**: AVG(completed_at - started_at) WHERE status='completed' GROUP BY model_id
- **Tokens Consumed**: SUM(tokens_consumed) WHERE status='completed' GROUP BY model_id
- **Cost per Model**: SUM(cost_usd) WHERE status='completed' GROUP BY model_id
- **Use Aggregated Table**: If analytics_models_usage exists, prefer it (faster), otherwise aggregate from generation_jobs

### 18. Performance Risks and Mitigations
- **Risk**: Full table scans on large tables (orders, token_transactions, generation_jobs)
- **Mitigation**: Always filter by date range first, use indexes on created_at
- **Risk**: Complex aggregations on millions of rows
- **Mitigation**: Use aggregated tables (analytics_models_usage) for historical data, cache results
- **Risk**: N+1 queries when loading relationships
- **Mitigation**: Use eager loading (with()), select only needed columns
- **Risk**: Slow top-N queries
- **Mitigation**: Use indexes on grouping columns, limit results to top 10-20

### 19. Security Risks and Mitigations
- **Risk**: Non-admin users accessing admin endpoints
- **Mitigation**: Strict admin middleware, verify role on every request
- **Risk**: Data leakage in error messages
- **Mitigation**: Generic error messages, don't reveal table/column names
- **Risk**: SQL injection
- **Mitigation**: Use Eloquent ORM (parameterized queries), validate all inputs
- **Risk**: Rate limiting abuse
- **Mitigation**: Implement rate limiting on admin endpoints (if needed)

### 20. Testing Requirements
- **Unit Tests**: Test aggregation logic, date filtering, currency conversion
- **Feature Tests**: Test admin middleware blocks non-admin, test each endpoint returns correct shape
- **Integration Tests**: Test with seeded data, verify aggregations match expected results
- **Performance Tests**: Verify queries use indexes, check query execution time
- **Security Tests**: Verify non-admin cannot access endpoints, test SQL injection prevention

### 21. Error Handling
- **Invalid Date Ranges**: Return 400 Bad Request with clear error message
- **Missing Data**: Return empty arrays/zeros (don't throw errors)
- **Database Errors**: Log error, return 500 with generic message (don't expose DB details)
- **Authorization Errors**: Return 403 Forbidden (consistent across all endpoints)

### 22. Response Formats
- **Consistent Structure**: All endpoints return {success: true, data: {...}, meta: {...}}
- **Date Formats**: ISO 8601 format (YYYY-MM-DD or YYYY-MM-DDTHH:mm:ssZ)
- **Currency Formats**: Decimal with 2-4 decimal places (document precision)
- **Pagination**: {data: [...], meta: {current_page, last_page, per_page, total}}
- **Time-Series**: {data: [{date: 'YYYY-MM-DD', value: 123.45}, ...]}

### 23. Documentation Requirements
- **Endpoint Documentation**: Document all query parameters, response formats, examples
- **Metric Definitions**: Clearly define DAU/WAU/MAU, revenue, cost, profit calculations
- **Date Filtering**: Document timezone, inclusive/exclusive boundaries, default ranges
- **Performance Notes**: Document expected query times, caching behavior, aggregation schedules
- **Authorization**: Document who can access endpoints, what data they see

### 24. Monitoring and Alerting
- **Slow Queries**: Alert if admin endpoint takes > 2 seconds
- **High Error Rates**: Alert if admin endpoints return errors > 1%
- **Cache Misses**: Monitor cache hit rates (should be > 80% for summary endpoints)
- **Data Accuracy**: Periodic reconciliation checks (compare aggregated vs raw data)

### 25. Future Considerations
- **Real-Time Dashboards**: Consider WebSocket updates for real-time metrics
- **Export Functionality**: CSV/Excel export for detailed reports (future phase)
- **Custom Date Ranges**: Support for custom date range picker in frontend
- **Drill-Down**: Support for drilling down into specific metrics (e.g., click on model to see details)
- **Comparative Analysis**: Compare current period vs previous period (growth rates)

---

## Critical Principles

1. **Accuracy First**: Financial numbers must be correct (revenue, cost, profit)
2. **Performance Second**: Optimize queries, use indexes, cache where appropriate
3. **Security Always**: Strict admin-only access, no data leakage
4. **Consistency**: Same metric definitions across all endpoints
5. **Documentation**: Clearly document all calculations and assumptions
6. **Testing**: Test with real data patterns, verify aggregations
7. **Monitoring**: Track query performance, cache hit rates, error rates
8. **Scalability**: Design for growth (millions of records)
