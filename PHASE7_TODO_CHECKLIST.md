# Phase 7: Queue Jobs & Scheduled Tasks - TODO Checklist

## Task 7.1: Generation Job Workers

### Subtask 7.1.1: Verify Queue Worker Configuration
**Files to Check/Modify:**
- `config/queue.php` (verify Redis configuration)
- `app/Jobs/GenerateImageJob.php` (verify timeout, tries)
- `app/Jobs/GenerateVideoJob.php` (verify timeout, tries)
- `app/Jobs/GenerateAudioJob.php` (verify timeout, tries)

**Queue Configurations:**
- Default connection: Redis
- Retry after: 90 seconds
- Failed jobs: database-uuids driver

**Timeout/Retry Behavior:**
- Image jobs: timeout=120s, tries=3
- Video jobs: timeout=600s, tries=3
- Audio jobs: timeout=240s, tries=3
- All jobs: idempotent (check status before processing)

**Acceptance Criteria:**
- Queue configuration correct (Redis)
- All generation jobs have appropriate timeout and tries
- Jobs are idempotent (status check before processing)

**Failure Modes & Safe Fallback:**
- Queue connection fails → Jobs fail, retry with backoff
- Timeout exceeded → Job marked failed, tokens refunded
- Max retries exceeded → Job marked failed, tokens refunded

**Logging:**
- Log job start, completion, failure
- Never log API keys, tokens, sensitive data
- Include job_id, user_id, status, duration

---

### Subtask 7.1.2: Create Supervisor Configuration Template
**Files to Create:**
- `deployment/supervisor/negarify-worker.conf` (template file)

**Queue Configurations:**
- Command: `php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600`
- Number of processes: 4 (configurable)
- Auto-start: true
- Auto-restart: true
- User: www-data (or appropriate user)

**Timeout/Retry Behavior:**
- Worker max-time: 3600 seconds (1 hour) - prevents memory leaks
- Sleep: 3 seconds when queue empty
- Tries: 3 (handled by job class, not worker)

**Acceptance Criteria:**
- Supervisor config template exists
- Config uses environment-safe placeholders
- Includes all required settings (autostart, autorestart, logging)

**Failure Modes & Safe Fallback:**
- Worker crash → Supervisor auto-restarts
- Memory leak → Max-time restarts worker after 1 hour
- Log rotation → Supervisor handles log rotation

**Logging:**
- Worker stdout/stderr logged to files
- Log location: /var/log/negarify/worker.log (or configurable)

---

### Subtask 7.1.3: Verify Job Retry Logic
**Files to Check:**
- `app/Jobs/GenerateImageJob.php` (retry logic in handle/failed methods)
- `app/Jobs/GenerateVideoJob.php` (retry logic)
- `app/Jobs/GenerateAudioJob.php` (retry logic)

**Queue Configurations:**
- Retry handled by Laravel queue system
- Exponential backoff: 0s, 1s, 4s, 9s (Laravel default)

**Timeout/Retry Behavior:**
- Max attempts: 3 (configurable via $tries property)
- Retry only on transient failures (exceptions)
- Idempotency check on each retry (status check)

**Acceptance Criteria:**
- Jobs retry on failure (up to max attempts)
- Idempotency prevents double-processing
- Tokens refunded on final failure

**Failure Modes & Safe Fallback:**
- Retry succeeds → Job completes normally
- All retries fail → Job marked failed, tokens refunded
- Job already processed → Skip (idempotent)

**Logging:**
- Log retry attempts with attempt number
- Log final failure with error message (sanitized)

---

### Subtask 7.1.4: Verify Job Timeout Handling
**Files to Check:**
- `app/Jobs/GenerateImageJob.php` (timeout property)
- `app/Jobs/GenerateVideoJob.php` (timeout property)
- `app/Jobs/GenerateAudioJob.php` (timeout property)

**Queue Configurations:**
- Timeout handled by Laravel queue system
- Worker kills job if timeout exceeded

**Timeout/Retry Behavior:**
- Image: 120 seconds (2 minutes)
- Video: 600 seconds (10 minutes)
- Audio: 240 seconds (4 minutes)
- Timeout exceeded → Job marked failed, tokens refunded

**Acceptance Criteria:**
- Jobs have appropriate timeouts per type
- Timeout exceeded triggers failure handling
- Tokens refunded on timeout

**Failure Modes & Safe Fallback:**
- Timeout exceeded → Job killed, marked failed, tokens refunded
- Long-running job → Increase timeout if needed (based on metrics)

**Logging:**
- Log timeout events
- Include job_id, timeout duration, actual duration

---

### Subtask 7.1.5: Verify Failed Job Handling
**Files to Check:**
- `app/Jobs/GenerateImageJob.php` (failed() method)
- `app/Jobs/GenerateVideoJob.php` (failed() method)
- `app/Jobs/GenerateAudioJob.php` (failed() method)
- `database/migrations/*_create_failed_jobs_table.php` (verify exists)

**Queue Configurations:**
- Failed jobs stored in failed_jobs table
- Failed jobs can be retried manually

**Timeout/Retry Behavior:**
- Failed job → Recorded in failed_jobs table
- Job status → Updated to 'failed'
- Tokens → Refunded
- Error message → Sanitized and stored

**Acceptance Criteria:**
- Failed jobs recorded in failed_jobs table
- Job status updated to 'failed'
- Tokens refunded on failure
- Error message sanitized (no secrets)

**Failure Modes & Safe Fallback:**
- Failed job handling fails → Log error, job still in failed_jobs table
- Token refund fails → Log error, manual intervention needed

**Logging:**
- Log all failed jobs with error details (sanitized)
- Include job_id, user_id, error message, attempt number

---

### Subtask 7.1.6: Verify Job Progress Tracking
**Files to Check:**
- `app/Jobs/GenerateImageJob.php` (status updates)
- `app/Jobs/GenerateVideoJob.php` (status updates)
- `app/Jobs/GenerateAudioJob.php` (status updates)
- `app/Models/GenerationJob.php` (status transitions)

**Queue Configurations:**
- N/A (progress tracking is job-level)

**Timeout/Retry Behavior:**
- Status transitions: pending → processing → completed/failed
- Timestamps: started_at, completed_at
- Progress: Can be tracked via status and timestamps

**Acceptance Criteria:**
- Job status updated correctly (pending → processing → completed/failed)
- Timestamps recorded (started_at, completed_at)
- Status transitions are valid

**Failure Modes & Safe Fallback:**
- Status update fails → Log error, job may be stuck
- Concurrent processing → Lock prevents (lockForUpdate)

**Logging:**
- Log status transitions
- Include job_id, old_status, new_status, timestamp

---

### Subtask 7.1.7: Create Job Cleanup Command
**Files to Create:**
- `app/Console/Commands/CleanupOldGenerationJobs.php` (new file)

**Queue Configurations:**
- N/A (cleanup is a scheduled command, not a queue job)

**Timeout/Retry Behavior:**
- N/A (cleanup runs as scheduled task)

**Schedule Frequency:**
- Daily at 2:00 AM (after midnight reset, before peak hours)

**Acceptance Criteria:**
- Command deletes jobs older than 90 days
- Only deletes completed/failed/cancelled jobs (never pending/processing)
- Logs cleanup statistics (count deleted)
- Optionally deletes S3 files (if configured)

**Failure Modes & Safe Fallback:**
- Cleanup fails → Log error, continue (non-critical)
- S3 deletion fails → Log warning, continue (non-critical)
- Database deletion fails → Log error, stop (critical)

**Logging:**
- Log cleanup start, completion, records deleted
- Include date range, job statuses, count deleted
- Never log sensitive job data (prompts, etc.)

**Test Coverage:**
- Test cleanup deletes old jobs only
- Test cleanup never deletes pending/processing jobs
- Test cleanup logs correctly

---

## Task 7.2: Scheduled Tasks

### Subtask 7.2.1: Verify Laravel Scheduler Configuration
**Files to Check/Modify:**
- `routes/console.php` (scheduled tasks)
- Verify cron setup documentation

**Queue Configurations:**
- N/A (scheduler is separate from queue)

**Schedule Frequency:**
- Cron runs every minute: `* * * * * php artisan schedule:run`

**Acceptance Criteria:**
- All scheduled tasks registered in routes/console.php
- Cron setup documented
- schedule:list shows all tasks

**Failure Modes & Safe Fallback:**
- Cron not running → Tasks don't execute → Monitoring/alerting needed
- Scheduler fails → Log error, continue with other tasks

**Logging:**
- Log scheduler execution (Laravel default)
- Log task start, completion, duration

---

### Subtask 7.2.2: Verify Currency Rate Fetch Job
**Files to Check:**
- `routes/console.php` (currency rate fetch schedule)
- `app/Services/TgjuScraperService.php` (service exists)

**Queue Configurations:**
- N/A (scheduled task, not queue job)

**Schedule Frequency:**
- Every 5 minutes
- Use withoutOverlapping() to prevent concurrent execution

**Timeout/Retry Behavior:**
- N/A (scheduled task, not retried automatically)
- If fails, retries on next schedule (5 minutes later)

**Acceptance Criteria:**
- Task runs every 5 minutes
- Prevents concurrent execution (withoutOverlapping)
- Logs execution and results
- Handles failures gracefully

**Failure Modes & Safe Fallback:**
- Scraping fails → Log error, use cached rate (if available)
- Service unavailable → Log warning, retry next schedule
- Invalid data → Log error, skip update

**Logging:**
- Log fetch start, completion, rate value
- Log errors with context (URL, response status)
- Never log API keys or sensitive data

**Test Coverage:**
- Test task registration (schedule:list)
- Test task execution (schedule:run)
- Test error handling

---

### Subtask 7.2.3: Verify Feed View Limit Reset Job
**Files to Check:**
- `routes/console.php` (feed view limit reset schedule)
- `app/Console/Commands/ResetFeedViewLimits.php` (command exists)

**Queue Configurations:**
- N/A (scheduled command)

**Schedule Frequency:**
- Daily at midnight (00:00)
- Use withoutOverlapping() to prevent concurrent execution

**Timeout/Retry Behavior:**
- N/A (scheduled task)
- If fails, retries next day

**Acceptance Criteria:**
- Task runs daily at midnight
- Resets all users' view limits to daily_limit
- Updates reset_at timestamp
- Logs reset statistics

**Failure Modes & Safe Fallback:**
- Reset fails → Log error, some users may not get reset
- Partial reset → Log warning, continue (non-critical)
- Database error → Log error, stop (critical)

**Logging:**
- Log reset start, completion, records reset
- Include count reset, date range
- Never log user-specific data

**Test Coverage:**
- Test reset command works correctly
- Test reset only affects eligible records
- Test reset logs correctly

---

### Subtask 7.2.4: Create Analytics Aggregation Job
**Files to Create:**
- `app/Console/Commands/AggregateModelsUsage.php` (new file)

**Queue Configurations:**
- N/A (scheduled command)

**Schedule Frequency:**
- Daily at 1:00 AM (after midnight, before peak hours)
- Use withoutOverlapping() to prevent concurrent execution

**Timeout/Retry Behavior:**
- N/A (scheduled task)
- If fails, retries next day

**Acceptance Criteria:**
- Task aggregates generation_jobs into analytics_models_usage table
- Aggregates by model_id, job_type, period_type (daily/weekly/monthly)
- Calculates all metrics (requests, success, failure, tokens, cost, latency)
- Uses UPSERT to prevent duplicates

**Failure Modes & Safe Fallback:**
- Aggregation fails → Log error, analytics data not updated
- Partial aggregation → Log warning, continue (non-critical)
- Database error → Log error, stop (critical)

**Logging:**
- Log aggregation start, completion, records processed
- Include date range, models aggregated, records created/updated
- Never log sensitive data

**Test Coverage:**
- Test aggregation calculates correctly
- Test UPSERT prevents duplicates
- Test error handling

---

### Subtask 7.2.5: Verify Expired OTP Cleanup Job
**Files to Check:**
- `routes/console.php` (OTP cleanup schedule)
- `app/Console/Commands/CleanupExpiredOtps.php` (command exists)

**Queue Configurations:**
- N/A (scheduled command)

**Schedule Frequency:**
- Hourly
- Use withoutOverlapping() to prevent concurrent execution

**Timeout/Retry Behavior:**
- N/A (scheduled task)
- If fails, retries next hour

**Acceptance Criteria:**
- Task runs hourly
- Deletes OTPs older than 1 hour (or expired)
- Logs cleanup statistics

**Failure Modes & Safe Fallback:**
- Cleanup fails → Log error, OTPs accumulate (non-critical)
- Partial cleanup → Log warning, continue

**Logging:**
- Log cleanup start, completion, records deleted
- Include count deleted, age threshold
- Never log OTP codes or phone numbers

**Test Coverage:**
- Test cleanup deletes expired OTPs only
- Test cleanup logs correctly

---

### Subtask 7.2.6: Create Old Job Cleanup Command
**Files to Create:**
- `app/Console/Commands/CleanupOldGenerationJobs.php` (same as 7.1.7)
- Schedule in routes/console.php

**Queue Configurations:**
- N/A (scheduled command)

**Schedule Frequency:**
- Daily at 2:00 AM
- Use withoutOverlapping() to prevent concurrent execution

**Timeout/Retry Behavior:**
- N/A (scheduled task)
- If fails, retries next day

**Acceptance Criteria:**
- Task runs daily
- Deletes jobs older than 90 days
- Only deletes completed/failed/cancelled jobs
- Optionally deletes S3 files
- Logs cleanup statistics

**Failure Modes & Safe Fallback:**
- Cleanup fails → Log error, jobs accumulate (non-critical)
- S3 deletion fails → Log warning, continue
- Database deletion fails → Log error, stop

**Logging:**
- Log cleanup start, completion, records deleted
- Include date range, job statuses, count deleted
- Never log sensitive job data

**Test Coverage:**
- Test cleanup deletes old jobs only
- Test cleanup never deletes pending/processing jobs
- Test cleanup logs correctly

---

### Subtask 7.2.7: Create System Health Recording Job
**Files to Create:**
- `app/Console/Commands/RecordSystemHealth.php` (new file)

**Queue Configurations:**
- N/A (scheduled command)

**Schedule Frequency:**
- Every 5 minutes
- Use withoutOverlapping() to prevent concurrent execution

**Timeout/Retry Behavior:**
- N/A (scheduled task)
- If fails, retries next schedule (5 minutes later)

**Acceptance Criteria:**
- Task runs every 5 minutes
- Records: queue_length, failed_jobs_count, error_rate, avg_latency_ms
- Stores in system_health table (if exists) or logs metrics
- Handles missing table gracefully

**Failure Modes & Safe Fallback:**
- Recording fails → Log error, metrics not recorded (non-critical)
- Table missing → Log warning, use alternative storage (logs)
- Database error → Log error, skip recording

**Logging:**
- Log recording start, completion, metrics recorded
- Include all metric values
- Never log sensitive data

**Test Coverage:**
- Test recording works correctly
- Test handles missing table gracefully
- Test error handling

---

### Subtask 7.2.8: Document Cron Setup
**Files to Create/Modify:**
- `README.md` or `DEPLOYMENT.md` (add cron setup section)

**Queue Configurations:**
- N/A (documentation)

**Schedule Frequency:**
- Cron runs every minute: `* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`

**Timeout/Retry Behavior:**
- N/A (documentation)

**Acceptance Criteria:**
- Cron setup documented
- Includes exact cron line
- Includes verification commands
- Includes troubleshooting tips

**Failure Modes & Safe Fallback:**
- N/A (documentation)

**Logging:**
- N/A (documentation)

---

## Summary

**Total Files to Create:** 4
- `deployment/supervisor/negarify-worker.conf` (Supervisor config template)
- `app/Console/Commands/CleanupOldGenerationJobs.php` (cleanup command)
- `app/Console/Commands/AggregateModelsUsage.php` (analytics aggregation)
- `app/Console/Commands/RecordSystemHealth.php` (system health recording)

**Total Files to Modify:** 2
- `routes/console.php` (add missing scheduled tasks)
- `README.md` or `DEPLOYMENT.md` (add cron/supervisor setup docs)

**Files to Verify:** 6
- `app/Jobs/GenerateImageJob.php` (timeout, tries, idempotency)
- `app/Jobs/GenerateVideoJob.php` (timeout, tries, idempotency)
- `app/Jobs/GenerateAudioJob.php` (timeout, tries, idempotency)
- `config/queue.php` (Redis configuration)
- `routes/console.php` (existing scheduled tasks)
- `app/Console/Commands/CleanupExpiredOtps.php` (verify exists)

**Dependencies:**
- Supervisor (for worker management)
- Cron (for scheduler)
- Redis (for queue)
- Database (for failed_jobs, analytics_models_usage, system_health tables)

**Verification Checklist:**
- [ ] Supervisor config deployed and workers running
- [ ] Cron job configured for scheduler
- [ ] All scheduled tasks registered (schedule:list)
- [ ] Failed job handling tested
- [ ] Token refund on failure verified
- [ ] Idempotency verified
- [ ] Timeout configuration appropriate
- [ ] Logging configured and tested
- [ ] No secrets in logs

**Test Coverage Minimum:**
- Unit tests for cleanup commands
- Integration tests for scheduled tasks
- Idempotency tests for generation jobs
- Error handling tests

