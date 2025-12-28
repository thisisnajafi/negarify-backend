# Phase 7: Queue Jobs & Scheduled Tasks - Operations & Reliability Summary

## Operations & Reliability Understanding (Production Readiness Review)

### 1. Why Queue Workers Are Required
- **Non-Blocking API**: Generation requests are long-running (seconds to minutes) - cannot block HTTP requests
- **Scalability**: Multiple workers process jobs in parallel, handling load spikes
- **Reliability**: Failed jobs can be retried automatically without user intervention
- **Resource Management**: Workers can be scaled independently of web servers
- **User Experience**: Users receive immediate response, job processes in background

### 2. What Reliability Means Here
- **Retries**: Automatic retry on transient failures (network issues, API timeouts) - max 3 attempts with exponential backoff
- **Timeouts**: Jobs must complete within reasonable time (images: 2min, videos: 10min, audio: 4min) - prevents worker exhaustion
- **Idempotency**: Jobs can be safely retried without double-processing (check status before processing, lock records)
- **Token Safety**: Tokens reserved on job creation, consumed on success, refunded on failure - exactly-once semantics
- **Status Tracking**: Jobs transition through states (pending → processing → completed/failed) - prevents duplicate processing

### 3. How Scheduled Tasks Affect Financial and User Experience Correctness
- **Currency Rate Fetch (every 5 min)**: Ensures accurate token pricing - incorrect rates = financial loss or user confusion
- **Feed View Limit Reset (daily)**: Users get fresh daily limits - missing reset = users cannot view feed = poor UX
- **Analytics Aggregation (daily)**: Pre-computes metrics for admin dashboard - missing aggregation = slow dashboard = poor admin experience
- **OTP Cleanup (hourly)**: Removes expired OTPs - missing cleanup = database bloat = performance degradation
- **Old Job Cleanup (daily)**: Removes completed/failed jobs older than 90 days - missing cleanup = database bloat = slow queries
- **System Health Recording (every 5 min)**: Tracks system metrics - missing recording = no visibility = cannot detect issues

### 4. What Must Be Observable (Logs, Metrics)
- **Job Lifecycle**: Log job start, completion, failure with job_id, user_id, status, duration
- **Retry Events**: Log retry attempts with attempt number, reason for retry
- **Token Operations**: Log token reservation, consumption, refund with amounts and reasons
- **Scheduled Task Execution**: Log task start, completion, duration, records processed
- **Errors**: Log all exceptions with stack traces, context (job_id, user_id, error message)
- **Performance Metrics**: Track job duration, queue length, worker utilization, failed job rate
- **Never Log Secrets**: API keys, passwords, tokens, sensitive user data must never appear in logs

### 5. What Could Go Wrong in Production and How Phase 7 Mitigates It
- **Worker Exhaustion**: Long-running jobs tie up workers → Timeout configuration limits job duration → Mitigation: Appropriate timeouts per job type
- **Duplicate Job Execution**: Race conditions cause same job processed twice → Idempotency checks (status check, record locking) → Mitigation: Lock records, check status before processing
- **Token Double-Consumption**: Retry causes tokens consumed twice → Idempotent token operations (check if already consumed) → Mitigation: Transaction-based token operations, status checks
- **Queue Backlog**: Jobs accumulate faster than workers process → Monitor queue length, scale workers → Mitigation: Supervisor config with multiple workers, monitoring
- **Failed Jobs Pile Up**: Failed jobs not handled, accumulate in queue → Failed job handling, manual retry capability → Mitigation: Failed job table, retry mechanism, cleanup
- **Scheduled Task Overlap**: Tasks run simultaneously, cause conflicts → Use withoutOverlapping() → Mitigation: Prevent concurrent execution
- **Cron Misconfiguration**: Scheduler not running → Documentation, verification commands → Mitigation: Clear cron setup instructions, schedule:list verification
- **Memory Leaks**: Long-running workers consume memory → Max-time limits worker lifetime → Mitigation: --max-time=3600 (1 hour) restarts workers
- **Database Connection Exhaustion**: Too many workers hold connections → Connection pooling, worker limits → Mitigation: Appropriate numprocs in Supervisor
- **Stale Data**: Scheduled tasks fail silently → Error handling, logging, alerts → Mitigation: Try-catch blocks, structured logging, failure notifications

### 6. Retry Strategy
- **Max Attempts**: 3 retries (configurable via $tries property)
- **Backoff**: Exponential backoff (Laravel default: 0s, 1s, 4s, 9s) - prevents overwhelming external APIs
- **Retry Conditions**: Only retry on transient failures (network errors, timeouts) - not on validation errors
- **Idempotency**: Each retry checks job status - if already completed/failed, skip processing

### 7. Timeout Configuration
- **Image Jobs**: 120 seconds (2 minutes) - typically fast generation
- **Video Jobs**: 600 seconds (10 minutes) - longer generation + polling
- **Audio Jobs**: 240 seconds (4 minutes) - moderate generation time
- **Worker Max-Time**: 3600 seconds (1 hour) - prevents memory leaks, restarts workers

### 8. Failed Job Handling
- **Laravel Failed Jobs Table**: Automatically records failed jobs with exception details
- **Job Status Update**: Generation job marked as 'failed' with error_message
- **Token Refund**: Tokens refunded on failure (handled in job's failed() method)
- **Manual Retry**: Admins can retry failed jobs via API endpoint
- **Cleanup**: Old failed jobs cleaned up daily (older than 90 days)

### 9. Supervisor Configuration
- **Process Management**: Supervisor ensures workers always run, auto-restart on crash
- **Multiple Workers**: 4 workers by default (configurable) - parallel processing
- **Logging**: Worker stdout/stderr logged to files for debugging
- **User**: Runs as www-data (or appropriate user) - security isolation
- **Auto-Start**: Workers start automatically on system boot

### 10. Scheduled Task Reliability
- **Without Overlapping**: Prevents concurrent execution of same task (mutex locks)
- **Error Handling**: All scheduled tasks wrapped in try-catch, errors logged
- **Idempotency**: Tasks can be safely re-run if they fail mid-execution
- **Logging**: All tasks log start, completion, duration, records processed
- **Verification**: schedule:list command shows all registered tasks

### 11. Cleanup Jobs
- **OTP Cleanup**: Removes OTPs older than 1 hour (expired) - prevents database bloat
- **Old Job Cleanup**: Removes completed/failed/cancelled jobs older than 90 days - prevents database bloat
- **Safe Deletion**: Only deletes jobs in terminal states (completed/failed/cancelled) - never deletes pending/processing
- **S3 Cleanup**: Optionally deletes associated files from S3 (if configured)

### 12. System Health Recording
- **Frequency**: Every 5 minutes - balances freshness vs load
- **Metrics**: Queue length, failed jobs count, error rate, API latency, storage usage
- **Storage**: Can use system_health table (if exists) or log metrics
- **Failure Handling**: If recording fails, log error but don't crash scheduler

### 13. Observability Requirements
- **Structured Logging**: JSON format with consistent fields (job_id, user_id, status, duration, error)
- **Log Levels**: INFO for normal operations, WARNING for retries, ERROR for failures
- **Context**: Always include job_id, user_id, status in logs for traceability
- **No Secrets**: Never log API keys, passwords, tokens, sensitive data
- **Metrics**: Track job success rate, average duration, queue length, worker utilization

### 14. Production Readiness Checklist
- [ ] Supervisor config deployed and workers running
- [ ] Cron job configured for scheduler
- [ ] All scheduled tasks registered (verify with schedule:list)
- [ ] Failed job handling tested
- [ ] Token refund on failure verified
- [ ] Idempotency verified (retry doesn't double-process)
- [ ] Timeout configuration appropriate for job types
- [ ] Logging configured and tested
- [ ] No secrets in logs
- [ ] Monitoring/alerting configured for queue length, failed jobs

### 15. Failure Modes and Mitigations
- **Worker Crash**: Supervisor auto-restarts → Mitigation: Supervisor config with autorestart=true
- **Queue Backlog**: Jobs accumulate → Mitigation: Scale workers, monitor queue length
- **Database Connection Lost**: Jobs fail → Mitigation: Connection retry, proper error handling
- **External API Down**: Generation jobs fail → Mitigation: Retry with backoff, mark failed after max attempts
- **Disk Full**: Logs/jobs fail → Mitigation: Log rotation, disk monitoring
- **Memory Exhaustion**: Workers crash → Mitigation: Max-time limits, worker restarts
- **Cron Not Running**: Scheduled tasks don't execute → Mitigation: Monitoring, alerts, documentation

### 16. Safe Defaults
- **Retry Count**: 3 attempts (balance between reliability and avoiding infinite loops)
- **Timeout**: Job-type specific (images: 2min, videos: 10min, audio: 4min)
- **Worker Count**: 4 workers (can scale based on load)
- **Max-Time**: 1 hour (prevents memory leaks)
- **Sleep**: 3 seconds (prevents CPU spinning when queue empty)
- **Cleanup Age**: 90 days for jobs, 1 hour for OTPs (balance retention vs storage)

### 17. Idempotency Guarantees
- **Job Processing**: Check status before processing (skip if already completed/failed/cancelled)
- **Token Operations**: Use database transactions, check if already consumed/refunded
- **Status Updates**: Use lockForUpdate() to prevent race conditions
- **Scheduled Tasks**: Use withoutOverlapping() to prevent concurrent execution

### 18. Monitoring and Alerting
- **Queue Length**: Alert if queue length > threshold (e.g., 1000 jobs)
- **Failed Job Rate**: Alert if failed job rate > threshold (e.g., 10%)
- **Worker Status**: Alert if workers not running
- **Scheduled Task Failures**: Alert if scheduled task fails
- **Job Duration**: Alert if average job duration exceeds expected

### 19. Documentation Requirements
- **Supervisor Config**: Template with placeholders for paths
- **Cron Setup**: Exact cron line for production
- **Verification Commands**: Commands to verify workers and scheduler
- **Troubleshooting**: Common issues and solutions
- **Scaling Guide**: How to scale workers based on load

### 20. Testing Strategy
- **Unit Tests**: Test job idempotency, token operations, error handling
- **Integration Tests**: Test job processing end-to-end (with mocked external APIs)
- **Scheduled Task Tests**: Test task registration, execution, error handling
- **Failure Simulation**: Test retry behavior, timeout handling, failed job handling
- **Deterministic Tests**: Freeze time for scheduled task tests

### 21. Security Considerations
- **Worker User**: Run workers as non-root user (www-data)
- **File Permissions**: Worker logs readable by appropriate users only
- **No Secrets in Logs**: Sanitize logs to remove API keys, tokens, passwords
- **Queue Security**: Redis queue protected with authentication
- **Supervisor Access**: Supervisor config protected (read-only for workers)

### 22. Performance Considerations
- **Worker Count**: Balance between parallelism and resource usage
- **Queue Backend**: Redis for high performance, persistence
- **Batch Processing**: Consider batching for cleanup jobs (process in chunks)
- **Database Indexes**: Ensure indexes on job status, created_at for fast queries
- **Connection Pooling**: Reuse database connections across jobs

### 23. Disaster Recovery
- **Failed Job Recovery**: Manual retry capability for critical jobs
- **Queue Recovery**: Redis persistence ensures jobs not lost on restart
- **Scheduled Task Recovery**: Tasks can be manually triggered if missed
- **Data Backup**: Regular backups of generation_jobs, token_transactions
- **Rollback Plan**: Ability to rollback job changes if issues arise

### 24. Operational Runbooks
- **Worker Restart**: How to restart workers (supervisorctl restart)
- **Queue Clear**: How to clear stuck jobs (queue:clear)
- **Failed Job Retry**: How to retry failed jobs (queue:retry)
- **Scheduler Debug**: How to debug scheduler issues (schedule:list, schedule:run)
- **Log Analysis**: Where to find logs, how to analyze them

### 25. Continuous Improvement
- **Metrics Collection**: Track job success rate, duration, queue length over time
- **Performance Tuning**: Adjust worker count, timeout, retry count based on metrics
- **Error Analysis**: Analyze failed jobs to identify patterns, improve error handling
- **Capacity Planning**: Monitor queue growth, plan worker scaling
- **Documentation Updates**: Keep runbooks and docs up-to-date with learnings

---

## Critical Principles

1. **Reliability First**: Jobs must be idempotent, retries must be safe, failures must be handled gracefully
2. **Observability Always**: Log everything important, never log secrets, make failures visible
3. **Safe Defaults**: Conservative timeouts, retry counts, cleanup ages - can be tuned based on metrics
4. **Fail Fast**: Detect failures quickly, handle them gracefully, don't let failures cascade
5. **Idempotency**: Every operation must be safely retryable without side effects
6. **Token Safety**: Exactly-once token consumption semantics - no double charges, no lost tokens
7. **Monitoring**: Track everything that matters - queue length, failed jobs, job duration, worker status
8. **Documentation**: Clear setup instructions, troubleshooting guides, runbooks for operations

