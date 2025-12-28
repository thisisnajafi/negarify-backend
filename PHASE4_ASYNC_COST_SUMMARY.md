# Phase 4: Segmind API Integration - Async & Cost Domain Summary

## Distributed Systems Design Notes

### 1. Why Generation Must Be Asynchronous
- **Long-running operations**: Image/video/audio generation takes seconds to minutes (not milliseconds)
- **Non-blocking user experience**: Users cannot wait for 30+ second HTTP requests
- **Resource utilization**: API workers can process multiple jobs concurrently
- **Scalability**: Queue-based architecture allows horizontal scaling of workers
- **Fault tolerance**: Failed jobs can be retried without user intervention
- **Cost control**: Token reservation prevents over-consumption during failures

### 2. Differences Between Job Types
- **Image jobs**: Typically 5-30 seconds, single file output, requires thumbnail generation
- **Video jobs**: Typically 1-5 minutes, single file output, requires thumbnail/preview frame extraction
- **Audio jobs**: Typically 10-60 seconds, single file output, may require metadata extraction
- **Common pattern**: All jobs follow same lifecycle (pending → processing → completed/failed/cancelled)
- **Storage requirements**: All outputs stored in S3, URLs stored in generation_jobs.result_url

### 3. Cost and Token Consumption Risks
- **Double consumption**: Job retries must not consume tokens twice (idempotency required)
- **Token drift**: Balance must match sum of transactions (reconciliation check)
- **Reservation leakage**: Reserved tokens must be refunded on failure/cancellation
- **Race conditions**: Concurrent job creation must not exceed balance
- **Partial failures**: If storage fails after API success, tokens already consumed (must handle gracefully)
- **Cost tracking**: Actual API costs must be recorded for profit analysis

### 4. Exactly-Once Token Consumption Semantics
- **Reservation phase**: Tokens deducted from balance when job created (pending status)
- **Consumption phase**: Tokens finalized when job completed (transaction log created)
- **Refund phase**: Reserved tokens refunded on failure/cancellation (balance restored, no transaction)
- **Idempotency**: Same job cannot consume tokens twice (status check prevents duplicate consumption)
- **Atomicity**: Reservation, consumption, and refund must be atomic (database transactions)
- **Reconciliation**: Balance = SUM(transactions) - SUM(reserved_tokens) (for pending jobs)

### 5. Failure, Retry, and Cancellation Behavior
- **Job failures**: Status → 'failed', tokens refunded, error_message stored, retry allowed
- **Retries**: Must check job status before processing (prevent duplicate execution)
- **Max retries**: Configurable limit (default 3), after which job marked failed permanently
- **Cancellation**: User-initiated, status → 'cancelled', tokens refunded, no retry allowed
- **Timeouts**: Long-running jobs must have timeout (configurable per job type)
- **Partial success**: If API succeeds but storage fails, retry storage only (don't re-call API)

### 6. Observability Requirements
- **Status tracking**: Real-time job status (pending, processing, completed, failed, cancelled)
- **Logging**: All API calls logged (request/response, without API keys)
- **Metrics**: Job success rate, average latency, cost per job type
- **Error tracking**: All failures logged with sanitized error messages
- **Progress tracking**: Long jobs may support progress updates (optional, depends on Segmind API)
- **Audit trail**: All token movements logged in token_transactions table

### 7. Service Boundaries and Responsibilities
- **Controllers**: Validate input, check balance, reserve tokens, create job, dispatch queue job
- **Queue Jobs**: Call Segmind API, poll for status, download results, store in S3, update job status
- **Services**: Abstract Segmind API calls, handle retries, map parameters, manage authentication
- **Models**: Represent job state, provide status checks, enforce state transitions

### 8. Race Condition Prevention
- **Token reservation**: Database transaction with row lock prevents concurrent over-reservation
- **Job status updates**: Atomic status transitions prevent duplicate processing
- **Balance updates**: Database transactions ensure atomicity with transaction log creation
- **Concurrent retries**: Status check prevents multiple workers processing same job

### 9. External API Failure Modes
- **Network failures**: Retry with exponential backoff (max 3 attempts)
- **API errors**: 4xx errors = user error (fail job, refund tokens), 5xx errors = retry
- **Rate limiting**: 429 errors = exponential backoff, retry after delay
- **Timeout**: Long-running requests must have timeout (30s for images, 5min for videos)
- **Partial responses**: If API returns partial data, fail job and retry

### 10. Token Reservation System
- **Reservation on creation**: When job created (status='pending'), tokens reserved (balance -= tokens)
- **No transaction log yet**: Reservation is temporary, no transaction record created
- **Consumption on success**: When job completed, transaction log created, reservation finalized
- **Refund on failure**: When job fails/cancelled, balance restored (balance += tokens), no transaction log
- **Reservation tracking**: Reserved tokens = SUM(tokens_consumed WHERE status IN ('pending', 'processing'))

### 11. Job State Machine
- **Initial state**: pending (job created, tokens reserved, queued)
- **Processing state**: processing (worker picked up job, calling Segmind API)
- **Success transition**: processing → completed (result stored, tokens consumed, transaction logged)
- **Failure transition**: processing → failed (error recorded, tokens refunded, retry allowed)
- **Cancellation**: pending/processing → cancelled (tokens refunded, no retry)
- **No backward transitions**: Once completed/failed/cancelled, job cannot return to pending/processing

### 12. Retry Strategy
- **Automatic retries**: Laravel queue retries failed jobs (max 3 attempts by default)
- **Exponential backoff**: Delay between retries increases (1s, 2s, 4s)
- **Idempotent retries**: Job must check status before processing (prevent duplicate execution)
- **Max retries**: After max retries, job marked failed permanently
- **Manual retry**: User can retry failed jobs (creates new job with same parameters)

### 13. Storage and File Management
- **S3 storage**: All generated content stored in S3-compatible storage
- **File organization**: Path structure: `generations/{job_type}/{user_id}/{job_id}.{ext}`
- **Thumbnail generation**: Images/videos require thumbnail generation (stored separately)
- **File cleanup**: Old failed jobs' files can be cleaned up (scheduled job)
- **CDN-ready URLs**: S3 URLs returned to users (public or signed URLs)

### 14. Cost Tracking and Analytics
- **Cost per job**: Actual API cost stored in generation_jobs.cost_usd
- **Token cost**: Tokens consumed stored in generation_jobs.tokens_consumed
- **Profit calculation**: Revenue (tokens sold) - Cost (API calls) = Profit
- **Analytics**: Job success rate, average cost, cost per model tracked in analytics_models_usage

### 15. Segmind API Integration Patterns
- **Authentication**: API key in Authorization header (never logged)
- **Request format**: JSON payload with prompt, parameters, model-specific options
- **Response format**: May be synchronous (image URL) or asynchronous (job ID for polling)
- **Polling pattern**: For async responses, poll status endpoint until completed
- **Download pattern**: Result URL provided, download and store in S3

### 16. Parameter Mapping and Validation
- **Internal → Segmind**: Map our parameter format to Segmind API format
- **Model capabilities**: Validate parameters against model capabilities (size, duration, format)
- **Default values**: Apply model defaults for missing parameters
- **Sanitization**: Sanitize prompts and parameters before API calls

### 17. Error Handling and Recovery
- **Transient errors**: Network timeouts, 5xx errors → retry
- **Permanent errors**: Invalid parameters, 4xx errors → fail job, refund tokens
- **Partial failures**: If storage fails after API success → retry storage, don't re-call API
- **Error messages**: Sanitize error messages (remove API keys, sensitive data) before storing

### 18. Job Timeout Handling
- **Configurable timeouts**: Different timeouts per job type (images: 30s, videos: 5min, audio: 2min)
- **Timeout detection**: Laravel queue timeout or manual timeout check
- **Timeout behavior**: Mark job as failed, refund tokens, log timeout error
- **Long-running jobs**: Videos may require progress tracking (if Segmind supports it)

### 19. Token Transaction Logging
- **Consumption transaction**: Created when job completed (type='consume', amount_tokens negative)
- **Transaction linking**: transaction.generation_job_id links to generation_jobs.id
- **Audit trail**: All token consumption logged with job reference
- **Reconciliation**: Balance must match SUM(transactions) (excluding reserved tokens)

### 20. Queue Job Design
- **Job class**: One job class per content type (GenerateImageJob, GenerateVideoJob, GenerateAudioJob)
- **Job properties**: Store job_id (not full job model) to avoid serialization issues
- **Idempotency**: Job must be idempotent (same job_id processed twice = same result)
- **Retry configuration**: Max attempts, timeout, backoff strategy configured per job type

### 21. Status Polling vs Callbacks
- **Polling pattern**: Segmind likely uses polling (check job status endpoint periodically)
- **Polling interval**: Check status every 2-5 seconds for active jobs
- **Polling timeout**: Stop polling after max duration (timeout)
- **Callback pattern**: If Segmind supports webhooks, implement callback handler (future enhancement)

### 22. File Download and Storage
- **Download strategy**: Stream download from Segmind URL to S3 (avoid memory issues)
- **File validation**: Validate file type, size before storing
- **Thumbnail generation**: Generate thumbnails for images/videos (using GD or ImageMagick)
- **Storage paths**: Organized paths for easy cleanup and CDN delivery

### 23. Cost Control Mechanisms
- **Token reservation**: Prevents over-consumption (balance checked before job creation)
- **Cost tracking**: Record actual API costs for profit analysis
- **Rate limiting**: Per-user rate limiting (prevent abuse)
- **Model availability**: Disable expensive models if needed (cost control)

### 24. System Reliability Requirements
- **Fault tolerance**: System must handle Segmind API failures gracefully
- **Data consistency**: Job status, token balance, transactions must be consistent
- **Observability**: All operations logged, metrics tracked
- **Recovery**: Failed jobs can be retried, cancelled jobs can be cleaned up

### 25. Testing and Mocking Strategy
- **Mock Segmind API**: Never call real API in tests (use HTTP fake or service mock)
- **Fake storage**: Use local disk or fake S3 in tests
- **Fake queues**: Use sync queue driver in tests (or test queue behavior explicitly)
- **Test scenarios**: Happy path, API failure, storage failure, timeout, cancellation, retry

---

## Critical Principles

1. **Never block users** - All generation is asynchronous
2. **Never lose tokens** - Reservation → consumption → refund flow is atomic
3. **Never consume twice** - Idempotent job processing prevents duplicate consumption
4. **Always log operations** - Full audit trail for debugging and analytics
5. **Always handle failures** - Graceful degradation, proper error messages
6. **Always validate inputs** - Parameter validation prevents API errors
7. **Always track costs** - Cost tracking enables profit analysis
8. **Always be observable** - Logging and metrics for system health

