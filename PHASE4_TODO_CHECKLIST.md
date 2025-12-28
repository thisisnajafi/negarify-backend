# Phase 4: Segmind API Integration - TODO Checklist

## Task 4.1: Segmind Base Service

### Subtask 4.1.1: Research Segmind API Documentation
**Files to Create/Modify:**
- Documentation notes (optional - internal docs)

**Queue Jobs Involved:**
- None (research only)

**Database Reads/Writes:**
- None

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Understand Segmind API authentication (API key in header)
- Understand request/response format
- Understand error codes and handling
- Understand rate limits
- Understand synchronous vs asynchronous patterns

**Failure and Recovery Scenarios:**
- N/A (research only)

---

### Subtask 4.1.2: Create BaseSegmindService Base Class
**Files to Create/Modify:**
- `app/Services/Segmind/BaseSegmindService.php` (new file)

**Queue Jobs Involved:**
- None (service only)

**Database Reads/Writes:**
- Read: `providers` table (get API key, base URL)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Exponential backoff: 1s, 2s, 4s delays
- Max retries: 3 attempts
- Timeout: 30s for images, 5min for videos, 2min for audio

**Acceptance Criteria:**
- Abstract base class exists
- Protected methods for common operations
- Constructor accepts Provider model
- API key retrieved securely (decrypted)
- Base URL from provider config

**Failure and Recovery Scenarios:**
- API key decryption failure: Log error, throw exception
- Provider not found: Throw exception
- Base URL invalid: Log error, throw exception

---

### Subtask 4.1.3: Implement API Authentication
**Files to Create/Modify:**
- `app/Services/Segmind/BaseSegmindService.php` (add authentication method)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- Read: `providers` table (get API key)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- API key added to Authorization header
- Header format: `Authorization: Bearer {api_key}`
- API key never logged (only presence logged)
- Authentication errors handled gracefully

**Failure and Recovery Scenarios:**
- Invalid API key: Return 401 error, log (without key), fail job
- Missing API key: Throw exception, fail job

---

### Subtask 4.1.4: Implement HTTP Client with Retry Logic
**Files to Create/Modify:**
- `app/Services/Segmind/BaseSegmindService.php` (add makeRequest method)

**Queue Jobs Involved:**
- None (used by queue jobs)

**Database Reads/Writes:**
- None

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Retry on: Network errors, 5xx errors, 429 (rate limit)
- No retry on: 4xx errors (client errors)
- Exponential backoff: 1s, 2s, 4s
- Max retries: 3 attempts
- Timeout: Configurable per request type

**Acceptance Criteria:**
- HTTP client with retry logic
- Exponential backoff implemented
- Timeout configuration
- Error classification (retryable vs non-retryable)
- Request/response logging (without API keys)

**Failure and Recovery Scenarios:**
- Network timeout: Retry with backoff
- 5xx error: Retry with backoff
- 429 rate limit: Retry with longer backoff
- 4xx error: No retry, fail immediately

---

### Subtask 4.1.5: Add Error Handling
**Files to Create/Modify:**
- `app/Services/Segmind/BaseSegmindService.php` (error handling throughout)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- None

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Error classification determines retry behavior

**Acceptance Criteria:**
- All exceptions caught and handled
- Error messages sanitized (no API keys, sensitive data)
- Error codes mapped to user-friendly messages
- Errors logged with context (without sensitive data)

**Failure and Recovery Scenarios:**
- API errors: Mapped to user-friendly messages
- Network errors: Retried automatically
- Invalid responses: Logged, fail job

---

### Subtask 4.1.6: Implement Rate Limiting Awareness
**Files to Create/Modify:**
- `app/Services/Segmind/BaseSegmindService.php` (rate limit handling)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- None

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- 429 errors: Extract Retry-After header, wait before retry
- Rate limit exceeded: Longer backoff (exponential)

**Acceptance Criteria:**
- 429 errors detected and handled
- Retry-After header respected
- Rate limit errors logged
- Jobs delayed when rate limited

**Failure and Recovery Scenarios:**
- Rate limit hit: Wait for Retry-After, retry
- Persistent rate limiting: Fail job after max retries

---

### Subtask 4.1.7: Add Request/Response Logging
**Files to Create/Modify:**
- `app/Services/Segmind/BaseSegmindService.php` (logging methods)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- None (logging only)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- All API requests logged (endpoint, method, params)
- All API responses logged (status, response data)
- API keys never logged (only presence)
- Sensitive data sanitized before logging
- Structured logging (JSON format)

**Failure and Recovery Scenarios:**
- Logging failure: Should not block API call (logging is non-critical)

---

### Subtask 4.1.8: Create Model-Specific Service Classes
**Files to Create/Modify:**
- `app/Services/Segmind/SegmindImageService.php` (new - extends BaseSegmindService)
- `app/Services/Segmind/SegmindVideoService.php` (new - extends BaseSegmindService)
- `app/Services/Segmind/SegmindAudioService.php` (new - extends BaseSegmindService)

**Queue Jobs Involved:**
- None (services used by jobs)

**Database Reads/Writes:**
- Read: `models` table (get model-specific config)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Inherited from BaseSegmindService

**Acceptance Criteria:**
- Each service extends BaseSegmindService
- Service-specific methods implemented
- Parameter mapping implemented
- Model capabilities validated

**Failure and Recovery Scenarios:**
- Model not found: Throw exception
- Invalid parameters: Validate and throw exception

**Test Coverage Expectations:**
- Unit test: Base service authentication
- Unit test: Retry logic with exponential backoff
- Unit test: Error handling and classification
- Unit test: Rate limit handling
- Unit test: Request/response logging (API keys not logged)

---

## Task 4.2: Image Generation Integration

### Subtask 4.2.1: Research Segmind Image Models
**Files to Create/Modify:**
- Documentation notes (optional)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- None

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Understand Segmind image generation API
- Understand supported models (SDXL, etc.)
- Understand parameters (size, style, quality, etc.)
- Understand response format (URL or base64)

**Failure and Recovery Scenarios:**
- N/A

---

### Subtask 4.2.2: Create Database Entries for Image Models
**Files to Create/Modify:**
- `database/seeders/ModelSeeder.php` (update - already exists, verify image models)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- Write: `models` table (INSERT image models)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Image models created in database
- Models linked to Segmind provider
- Model capabilities set (supports_size, supports_style, max_resolution)
- Default tokens set per model

**Failure and Recovery Scenarios:**
- Seeder failure: Rollback, fix data, re-run

---

### Subtask 4.2.3: Implement SegmindImageService
**Files to Create/Modify:**
- `app/Services/Segmind/SegmindImageService.php` (implement generateImage method)

**Queue Jobs Involved:**
- None (used by GenerateImageJob)

**Database Reads/Writes:**
- Read: `models` table (get model config)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Inherited from BaseSegmindService

**Acceptance Criteria:**
- generateImage() method implemented
- Parameters mapped to Segmind API format
- Model capabilities validated
- API endpoint constructed correctly
- Response parsed correctly

**Failure and Recovery Scenarios:**
- Invalid parameters: Validate and throw exception
- API error: Handled by base service retry logic

---

### Subtask 4.2.4: Map API Parameters to Internal Format
**Files to Create/Modify:**
- `app/Services/Segmind/SegmindImageService.php` (parameter mapping methods)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- None

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Internal params → Segmind params mapping
- Size validation (against model.max_resolution)
- Style validation (against model.supported_styles)
- Default values applied for missing params
- Negative prompt handling

**Failure and Recovery Scenarios:**
- Invalid size: Validate and throw exception
- Invalid style: Validate and throw exception

---

### Subtask 4.2.5: Create GenerateImageJob Queue Job
**Files to Create/Modify:**
- `app/Jobs/GenerateImageJob.php` (new file)

**Queue Jobs Involved:**
- GenerateImageJob (self)

**Database Reads/Writes:**
- Read: `generation_jobs` (get job details)
- Write: `generation_jobs` (update status, result_url, error_message)
- Write: `users` (update tokens_balance)
- Write: `token_transactions` (create consumption transaction)

**Token Reservation/Consumption Points:**
- Consumption: On job completion (status='completed')
- Refund: On job failure/cancellation

**Retry and Timeout Behavior:**
- Max attempts: 3
- Timeout: 60 seconds (configurable)
- Backoff: Exponential (1s, 2s, 4s)
- Idempotent: Check job status before processing

**Acceptance Criteria:**
- Job implements ShouldQueue
- Job accepts generation_job_id
- Job is idempotent (status check prevents duplicate processing)
- Job handles all failure modes
- Job updates status correctly
- Job consumes/refunds tokens correctly

**Failure and Recovery Scenarios:**
- Job already processed: Skip (idempotent)
- API failure: Retry (max 3 attempts), then fail and refund
- Storage failure: Retry storage, don't re-call API
- Timeout: Mark failed, refund tokens

---

### Subtask 4.2.6: Implement Image Download and S3 Storage
**Files to Create/Modify:**
- `app/Jobs/GenerateImageJob.php` (download and storage logic)

**Queue Jobs Involved:**
- GenerateImageJob

**Database Reads/Writes:**
- Write: `generation_jobs` (update result_url)

**Token Reservation/Consumption Points:**
- None (tokens already reserved)

**Retry and Timeout Behavior:**
- Download timeout: 30 seconds
- Storage retry: 3 attempts

**Acceptance Criteria:**
- Image downloaded from Segmind URL
- File validated (type, size)
- Stored in S3: `generations/image/{user_id}/{job_id}.{ext}`
- result_url updated in job
- Public or signed URL returned

**Failure and Recovery Scenarios:**
- Download failure: Retry download (max 3 attempts)
- Storage failure: Retry storage (max 3 attempts)
- Invalid file: Fail job, refund tokens

---

### Subtask 4.2.7: Create Thumbnail Generation
**Files to Create/Modify:**
- `app/Jobs/GenerateImageJob.php` (thumbnail generation)
- Or separate job: `app/Jobs/GenerateThumbnailJob.php` (optional)

**Queue Jobs Involved:**
- GenerateImageJob (or GenerateThumbnailJob)

**Database Reads/Writes:**
- Write: `generation_jobs` (update result_thumbnail_url)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Thumbnail generation timeout: 10 seconds
- Retry: 2 attempts

**Acceptance Criteria:**
- Thumbnail generated (256x256 or 512x512)
- Thumbnail stored in S3: `generations/image/{user_id}/{job_id}_thumb.{ext}`
- result_thumbnail_url updated in job
- Thumbnail generation failure is non-critical (job can complete without thumbnail)

**Failure and Recovery Scenarios:**
- Thumbnail generation failure: Log warning, continue (non-critical)
- Storage failure: Log warning, continue (non-critical)

---

### Subtask 4.2.8: Implement Token Consumption
**Files to Create/Modify:**
- `app/Jobs/GenerateImageJob.php` (token consumption logic)

**Queue Jobs Involved:**
- GenerateImageJob

**Database Reads/Writes:**
- Write: `users` (update tokens_balance - finalize consumption)
- Write: `token_transactions` (create consumption transaction)
- Write: `generation_jobs` (update tokens_consumed)

**Token Reservation/Consumption Points:**
- Consumption: On job completion (atomic: balance update + transaction log)

**Retry and Timeout Behavior:**
- Atomic operation (database transaction)

**Acceptance Criteria:**
- Tokens consumed only on successful completion
- Transaction log created (type='consume', amount_tokens negative)
- Balance updated atomically with transaction
- tokens_consumed field updated in job

**Failure and Recovery Scenarios:**
- Balance update failure: Transaction rollback, job remains processing
- Transaction creation failure: Transaction rollback, job remains processing

---

### Subtask 4.2.9: Add Progress Tracking
**Files to Create/Modify:**
- `app/Jobs/GenerateImageJob.php` (progress updates)
- `app/Models/GenerationJob.php` (add progress field if needed)

**Queue Jobs Involved:**
- GenerateImageJob

**Database Reads/Writes:**
- Write: `generation_jobs` (update progress if supported)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Progress tracked if Segmind API supports it
- Progress stored in job (optional field)
- Progress updates logged

**Failure and Recovery Scenarios:**
- Progress tracking failure: Non-critical, continue job

---

### Subtask 4.2.10: Handle Errors and Retries
**Files to Create/Modify:**
- `app/Jobs/GenerateImageJob.php` (error handling throughout)

**Queue Jobs Involved:**
- GenerateImageJob

**Database Reads/Writes:**
- Write: `generation_jobs` (update status='failed', error_message)
- Write: `users` (refund tokens)

**Token Reservation/Consumption Points:**
- Refund: On failure (balance restored, no transaction log)

**Retry and Timeout Behavior:**
- Laravel queue retries failed jobs (max 3 attempts)
- Job checks status before processing (idempotent)

**Acceptance Criteria:**
- All errors caught and handled
- Error messages sanitized before storing
- Tokens refunded on failure
- Job status updated to 'failed'
- Retries are idempotent

**Failure and Recovery Scenarios:**
- API error: Retry (max 3), then fail and refund
- Storage error: Retry storage (max 3), then fail and refund
- Validation error: Fail immediately, refund

**Test Coverage Expectations:**
- Feature test: Image generation request creates job
- Feature test: Job processes successfully, tokens consumed
- Feature test: Job failure refunds tokens
- Feature test: Job retry is idempotent
- Feature test: Image stored in S3
- Feature test: Thumbnail generated
- Feature test: Token consumption creates transaction log

---

## Task 4.3: Video Generation Integration

### Subtask 4.3.1: Research Segmind Video Models
**Files to Create/Modify:**
- Documentation notes (optional)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- None

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Understand Segmind video generation API
- Understand supported models
- Understand parameters (duration, resolution, fps, etc.)
- Understand async pattern (polling required)

**Failure and Recovery Scenarios:**
- N/A

---

### Subtask 4.3.2: Create Database Entries for Video Models
**Files to Create/Modify:**
- `database/seeders/ModelSeeder.php` (add video models)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- Write: `models` table (INSERT video models)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Video models created in database
- Models linked to Segmind provider
- Model capabilities set (max_resolution, max_duration)
- Default tokens set per model

**Failure and Recovery Scenarios:**
- Seeder failure: Rollback, fix data, re-run

---

### Subtask 4.3.3: Implement SegmindVideoService
**Files to Create/Modify:**
- `app/Services/Segmind/SegmindVideoService.php` (implement generateVideo method)

**Queue Jobs Involved:**
- None (used by GenerateVideoJob)

**Database Reads/Writes:**
- Read: `models` table (get model config)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Inherited from BaseSegmindService
- Polling interval: 5 seconds
- Max polling duration: 5 minutes

**Acceptance Criteria:**
- generateVideo() method implemented
- Parameters mapped to Segmind API format
- Polling logic implemented (if async)
- Model capabilities validated
- Response parsed correctly

**Failure and Recovery Scenarios:**
- Invalid parameters: Validate and throw exception
- Polling timeout: Fail job, refund tokens

---

### Subtask 4.3.4: Map Video-Specific Parameters
**Files to Create/Modify:**
- `app/Services/Segmind/SegmindVideoService.php` (parameter mapping)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- None

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Duration validation (min/max)
- Resolution validation (against model.max_resolution)
- FPS validation
- Format validation
- Default values applied

**Failure and Recovery Scenarios:**
- Invalid duration: Validate and throw exception
- Invalid resolution: Validate and throw exception

---

### Subtask 4.3.5: Create GenerateVideoJob Queue Job
**Files to Create/Modify:**
- `app/Jobs/GenerateVideoJob.php` (new file)

**Queue Jobs Involved:**
- GenerateVideoJob (self)

**Database Reads/Writes:**
- Read: `generation_jobs` (get job details)
- Write: `generation_jobs` (update status, result_url, error_message)
- Write: `users` (update tokens_balance)
- Write: `token_transactions` (create consumption transaction)

**Token Reservation/Consumption Points:**
- Consumption: On job completion
- Refund: On job failure/cancellation

**Retry and Timeout Behavior:**
- Max attempts: 3
- Timeout: 300 seconds (5 minutes)
- Backoff: Exponential
- Idempotent: Status check before processing

**Acceptance Criteria:**
- Job implements ShouldQueue
- Job handles long-running operations
- Job polls for status (if async)
- Job is idempotent
- Job handles all failure modes

**Failure and Recovery Scenarios:**
- Job already processed: Skip (idempotent)
- Polling timeout: Mark failed, refund tokens
- API failure: Retry (max 3), then fail and refund

---

### Subtask 4.3.6: Implement Video Download and Storage
**Files to Create/Modify:**
- `app/Jobs/GenerateVideoJob.php` (download and storage logic)

**Queue Jobs Involved:**
- GenerateVideoJob

**Database Reads/Writes:**
- Write: `generation_jobs` (update result_url)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Download timeout: 120 seconds (videos are large)
- Storage retry: 3 attempts

**Acceptance Criteria:**
- Video downloaded from Segmind URL
- File validated (type, size)
- Stored in S3: `generations/video/{user_id}/{job_id}.{ext}`
- result_url updated in job
- Large file handling (streaming download)

**Failure and Recovery Scenarios:**
- Download failure: Retry download (max 3 attempts)
- Storage failure: Retry storage (max 3 attempts)
- File too large: Fail job, refund tokens

---

### Subtask 4.3.7: Create Video Thumbnail/Preview
**Files to Create/Modify:**
- `app/Jobs/GenerateVideoJob.php` (thumbnail generation)

**Queue Jobs Involved:**
- GenerateVideoJob

**Database Reads/Writes:**
- Write: `generation_jobs` (update result_thumbnail_url)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Thumbnail generation timeout: 30 seconds
- Retry: 2 attempts

**Acceptance Criteria:**
- Thumbnail extracted from video (first frame or middle frame)
- Thumbnail stored in S3: `generations/video/{user_id}/{job_id}_thumb.{ext}`
- result_thumbnail_url updated in job
- Thumbnail generation failure is non-critical

**Failure and Recovery Scenarios:**
- Thumbnail generation failure: Log warning, continue (non-critical)
- Storage failure: Log warning, continue (non-critical)

---

### Subtask 4.3.8: Implement Token Consumption
**Files to Create/Modify:**
- `app/Jobs/GenerateVideoJob.php` (token consumption logic)

**Queue Jobs Involved:**
- GenerateVideoJob

**Database Reads/Writes:**
- Write: `users` (update tokens_balance)
- Write: `token_transactions` (create consumption transaction)
- Write: `generation_jobs` (update tokens_consumed)

**Token Reservation/Consumption Points:**
- Consumption: On job completion (atomic)

**Retry and Timeout Behavior:**
- Atomic operation (database transaction)

**Acceptance Criteria:**
- Tokens consumed only on successful completion
- Transaction log created
- Balance updated atomically
- tokens_consumed field updated

**Failure and Recovery Scenarios:**
- Balance update failure: Transaction rollback
- Transaction creation failure: Transaction rollback

---

### Subtask 4.3.9: Add Progress Tracking for Long Jobs
**Files to Create/Modify:**
- `app/Jobs/GenerateVideoJob.php` (progress tracking)
- `app/Models/GenerationJob.php` (add progress field if needed)

**Queue Jobs Involved:**
- GenerateVideoJob

**Database Reads/Writes:**
- Write: `generation_jobs` (update progress)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Progress updates every 10-30 seconds during polling

**Acceptance Criteria:**
- Progress tracked during polling
- Progress stored in job
- Progress updates logged
- Progress available via API

**Failure and Recovery Scenarios:**
- Progress tracking failure: Non-critical, continue job

---

### Subtask 4.3.10: Handle Errors and Retries
**Files to Create/Modify:**
- `app/Jobs/GenerateVideoJob.php` (error handling)

**Queue Jobs Involved:**
- GenerateVideoJob

**Database Reads/Writes:**
- Write: `generation_jobs` (update status='failed', error_message)
- Write: `users` (refund tokens)

**Token Reservation/Consumption Points:**
- Refund: On failure

**Retry and Timeout Behavior:**
- Laravel queue retries (max 3 attempts)
- Job is idempotent

**Acceptance Criteria:**
- All errors caught and handled
- Error messages sanitized
- Tokens refunded on failure
- Retries are idempotent

**Failure and Recovery Scenarios:**
- API error: Retry (max 3), then fail and refund
- Polling timeout: Fail and refund
- Storage error: Retry storage (max 3), then fail and refund

**Test Coverage Expectations:**
- Feature test: Video generation request creates job
- Feature test: Job processes successfully, tokens consumed
- Feature test: Job failure refunds tokens
- Feature test: Polling works correctly
- Feature test: Video stored in S3
- Feature test: Thumbnail extracted

---

## Task 4.4: Audio Generation Integration

### Subtask 4.4.1: Research Segmind Audio Models
**Files to Create/Modify:**
- Documentation notes (optional)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- None

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Understand Segmind audio generation API
- Understand supported models
- Understand parameters (duration, format, sample rate, etc.)

**Failure and Recovery Scenarios:**
- N/A

---

### Subtask 4.4.2: Create Database Entries for Audio Models
**Files to Create/Modify:**
- `database/seeders/ModelSeeder.php` (add audio models)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- Write: `models` table (INSERT audio models)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Audio models created in database
- Models linked to Segmind provider
- Model capabilities set (max_duration, supported_formats)
- Default tokens set per model

**Failure and Recovery Scenarios:**
- Seeder failure: Rollback, fix data, re-run

---

### Subtask 4.4.3: Implement SegmindAudioService
**Files to Create/Modify:**
- `app/Services/Segmind/SegmindAudioService.php` (implement generateAudio method)

**Queue Jobs Involved:**
- None (used by GenerateAudioJob)

**Database Reads/Writes:**
- Read: `models` table (get model config)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Inherited from BaseSegmindService
- Polling interval: 3 seconds (audio is faster)
- Max polling duration: 2 minutes

**Acceptance Criteria:**
- generateAudio() method implemented
- Parameters mapped to Segmind API format
- Polling logic implemented (if async)
- Model capabilities validated
- Response parsed correctly

**Failure and Recovery Scenarios:**
- Invalid parameters: Validate and throw exception
- Polling timeout: Fail job, refund tokens

---

### Subtask 4.4.4: Map Audio-Specific Parameters
**Files to Create/Modify:**
- `app/Services/Segmind/SegmindAudioService.php` (parameter mapping)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- None

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Duration validation (min/max)
- Format validation (mp3, wav, etc.)
- Sample rate validation
- Default values applied

**Failure and Recovery Scenarios:**
- Invalid duration: Validate and throw exception
- Invalid format: Validate and throw exception

---

### Subtask 4.4.5: Create GenerateAudioJob Queue Job
**Files to Create/Modify:**
- `app/Jobs/GenerateAudioJob.php` (new file)

**Queue Jobs Involved:**
- GenerateAudioJob (self)

**Database Reads/Writes:**
- Read: `generation_jobs` (get job details)
- Write: `generation_jobs` (update status, result_url, error_message)
- Write: `users` (update tokens_balance)
- Write: `token_transactions` (create consumption transaction)

**Token Reservation/Consumption Points:**
- Consumption: On job completion
- Refund: On job failure/cancellation

**Retry and Timeout Behavior:**
- Max attempts: 3
- Timeout: 120 seconds (2 minutes)
- Backoff: Exponential
- Idempotent: Status check before processing

**Acceptance Criteria:**
- Job implements ShouldQueue
- Job handles audio generation
- Job polls for status (if async)
- Job is idempotent
- Job handles all failure modes

**Failure and Recovery Scenarios:**
- Job already processed: Skip (idempotent)
- Polling timeout: Mark failed, refund tokens
- API failure: Retry (max 3), then fail and refund

---

### Subtask 4.4.6: Implement Audio Download and Storage
**Files to Create/Modify:**
- `app/Jobs/GenerateAudioJob.php` (download and storage logic)

**Queue Jobs Involved:**
- GenerateAudioJob

**Database Reads/Writes:**
- Write: `generation_jobs` (update result_url)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Download timeout: 60 seconds
- Storage retry: 3 attempts

**Acceptance Criteria:**
- Audio downloaded from Segmind URL
- File validated (type, size)
- Stored in S3: `generations/audio/{user_id}/{job_id}.{ext}`
- result_url updated in job

**Failure and Recovery Scenarios:**
- Download failure: Retry download (max 3 attempts)
- Storage failure: Retry storage (max 3 attempts)

---

### Subtask 4.4.7: Implement Token Consumption
**Files to Create/Modify:**
- `app/Jobs/GenerateAudioJob.php` (token consumption logic)

**Queue Jobs Involved:**
- GenerateAudioJob

**Database Reads/Writes:**
- Write: `users` (update tokens_balance)
- Write: `token_transactions` (create consumption transaction)
- Write: `generation_jobs` (update tokens_consumed)

**Token Reservation/Consumption Points:**
- Consumption: On job completion (atomic)

**Retry and Timeout Behavior:**
- Atomic operation (database transaction)

**Acceptance Criteria:**
- Tokens consumed only on successful completion
- Transaction log created
- Balance updated atomically
- tokens_consumed field updated

**Failure and Recovery Scenarios:**
- Balance update failure: Transaction rollback
- Transaction creation failure: Transaction rollback

---

### Subtask 4.4.8: Add Audio Metadata Extraction
**Files to Create/Modify:**
- `app/Jobs/GenerateAudioJob.php` (metadata extraction)

**Queue Jobs Involved:**
- GenerateAudioJob

**Database Reads/Writes:**
- Write: `generation_jobs` (store metadata in params_json if needed)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- Metadata extraction timeout: 10 seconds
- Non-critical (job can complete without metadata)

**Acceptance Criteria:**
- Audio metadata extracted (duration, format, bitrate, etc.)
- Metadata stored in job (params_json or separate field)
- Metadata extraction failure is non-critical

**Failure and Recovery Scenarios:**
- Metadata extraction failure: Log warning, continue (non-critical)

---

### Subtask 4.4.9: Handle Errors and Retries
**Files to Create/Modify:**
- `app/Jobs/GenerateAudioJob.php` (error handling)

**Queue Jobs Involved:**
- GenerateAudioJob

**Database Reads/Writes:**
- Write: `generation_jobs` (update status='failed', error_message)
- Write: `users` (refund tokens)

**Token Reservation/Consumption Points:**
- Refund: On failure

**Retry and Timeout Behavior:**
- Laravel queue retries (max 3 attempts)
- Job is idempotent

**Acceptance Criteria:**
- All errors caught and handled
- Error messages sanitized
- Tokens refunded on failure
- Retries are idempotent

**Failure and Recovery Scenarios:**
- API error: Retry (max 3), then fail and refund
- Polling timeout: Fail and refund
- Storage error: Retry storage (max 3), then fail and refund

**Test Coverage Expectations:**
- Feature test: Audio generation request creates job
- Feature test: Job processes successfully, tokens consumed
- Feature test: Job failure refunds tokens
- Feature test: Audio stored in S3
- Feature test: Metadata extracted

---

## Task 4.5: Unified Generation Job System

### Subtask 4.5.1: Create GenerationJobController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GenerationJobController.php` (new file)

**Queue Jobs Involved:**
- None (controller only)

**Database Reads/Writes:**
- Read: `generation_jobs` (list, show, filter)

**Token Reservation/Consumption Points:**
- None (read-only operations)

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Controller in Api/V1 namespace
- All methods require authentication
- Thin controller (business logic in services/jobs)

**Failure and Recovery Scenarios:**
- Unauthorized: Return 401

---

### Subtask 4.5.2: Implement Job Status Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GenerationJobController.php` (implement show method)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- Read: `generation_jobs` (get job by ID)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- GET /api/v1/generate/jobs/{id} returns job status
- Response includes: id, status, job_type, prompt, result_url, error_message, created_at, started_at, completed_at
- HTTP 200 on success, 404 if job not found
- User can only view own jobs (authorization check)

**Failure and Recovery Scenarios:**
- Job not found: Return 404
- Unauthorized access: Return 403

---

### Subtask 4.5.3: Implement Job Result Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GenerationJobController.php` (result in show method or separate method)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- Read: `generation_jobs` (get job result URLs)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Returns result_url and result_thumbnail_url (if available)
- Only returns results for completed jobs
- HTTP 200 on success, 400 if job not completed

**Failure and Recovery Scenarios:**
- Job not completed: Return 400 with message
- Job not found: Return 404

---

### Subtask 4.5.4: Implement Job Cancellation
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GenerationJobController.php` (implement cancel method)

**Queue Jobs Involved:**
- None (cancellation handled in controller)

**Database Reads/Writes:**
- Read: `generation_jobs` (get job)
- Write: `generation_jobs` (update status='cancelled')
- Write: `users` (refund tokens)

**Token Reservation/Consumption Points:**
- Refund: On cancellation (balance restored, no transaction log)

**Retry and Timeout Behavior:**
- N/A (cancellation is immediate)

**Acceptance Criteria:**
- POST /api/v1/generate/jobs/{id}/cancel cancels job
- Only pending/processing jobs can be cancelled
- Tokens refunded on cancellation
- Job status updated to 'cancelled'
- HTTP 200 on success, 400 if job cannot be cancelled

**Failure and Recovery Scenarios:**
- Job already completed: Return 400 (cannot cancel)
- Job already cancelled: Return 400 (already cancelled)
- Job not found: Return 404
- Refund failure: Transaction rollback, return 500

---

### Subtask 4.5.5: Implement Job Retry
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GenerationJobController.php` (implement retry method)

**Queue Jobs Involved:**
- GenerateImageJob, GenerateVideoJob, or GenerateAudioJob (dispatched based on job_type)

**Database Reads/Writes:**
- Read: `generation_jobs` (get failed job)
- Write: `generation_jobs` (create new job with same parameters)
- Write: `users` (reserve tokens for new job)

**Token Reservation/Consumption Points:**
- Reservation: On retry (new job created, tokens reserved)

**Retry and Timeout Behavior:**
- Creates new job (fresh retry, not same job)

**Acceptance Criteria:**
- POST /api/v1/generate/jobs/{id}/retry creates new job
- Only failed jobs can be retried
- New job created with same parameters
- Tokens reserved for new job
- HTTP 200 on success, 400 if job cannot be retried

**Failure and Recovery Scenarios:**
- Job not failed: Return 400 (cannot retry)
- Job not found: Return 404
- Insufficient tokens: Return 400
- Job creation failure: Return 500

---

### Subtask 4.5.6: Add Job Listing with Filters
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GenerationJobController.php` (implement index method)
- `app/Http/Requests/Api/V1/GenerationJobListRequest.php` (new - validation)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- Read: `generation_jobs` (list with filters)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- GET /api/v1/generate/jobs returns user's jobs
- Filters: type (image/video/audio), status (pending/processing/completed/failed/cancelled), date range
- Pagination (15 per page)
- Ordered by created_at DESC
- HTTP 200 on success

**Failure and Recovery Scenarios:**
- Invalid filter: Return 422 with validation errors

---

### Subtask 4.5.7: Implement Token Reservation System
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GenerationController.php` (reservation in generate methods)
- `app/Services/TokenReservationService.php` (new - optional service)

**Queue Jobs Involved:**
- None (reservation happens before job dispatch)

**Database Reads/Writes:**
- Read: `users` (check balance, lock row)
- Write: `users` (reserve tokens - balance -= tokens)
- Write: `generation_jobs` (create job with status='pending')

**Token Reservation/Consumption Points:**
- Reservation: On job creation (balance -= tokens, no transaction log yet)

**Retry and Timeout Behavior:**
- Atomic operation (database transaction with row lock)

**Acceptance Criteria:**
- Tokens reserved when job created
- Reservation is atomic (balance update + job creation in transaction)
- Row lock prevents concurrent over-reservation
- Reserved tokens = SUM(tokens_consumed WHERE status IN ('pending', 'processing'))

**Failure and Recovery Scenarios:**
- Insufficient balance: Return 400, no job created
- Concurrent reservation: Row lock prevents, return 400
- Job creation failure: Transaction rollback, balance restored

---

### Subtask 4.5.8: Add Job Timeout Handling
**Files to Create/Modify:**
- `app/Jobs/GenerateImageJob.php`, `GenerateVideoJob.php`, `GenerateAudioJob.php` (timeout configuration)
- `app/Console/Commands/CleanupTimedOutJobs.php` (new - optional scheduled job)

**Queue Jobs Involved:**
- All generation jobs

**Database Reads/Writes:**
- Write: `generation_jobs` (update status='failed', error_message='Timeout')

**Token Reservation/Consumption Points:**
- Refund: On timeout (balance restored)

**Retry and Timeout Behavior:**
- Timeout configured per job type (images: 60s, videos: 300s, audio: 120s)
- Laravel queue timeout or manual timeout check

**Acceptance Criteria:**
- Jobs have configurable timeout
- Timeout detected and handled
- Job marked failed on timeout
- Tokens refunded on timeout
- Timeout logged

**Failure and Recovery Scenarios:**
- Timeout detected: Mark failed, refund tokens, log error

---

### Subtask 4.5.9: Create Job Cleanup for Old Jobs
**Files to Create/Modify:**
- `app/Console/Commands/CleanupOldGenerationJobs.php` (new file)
- `routes/console.php` (schedule cleanup job)

**Queue Jobs Involved:**
- None

**Database Reads/Writes:**
- Read: `generation_jobs` (find old jobs)
- Write: `generation_jobs` (soft delete or archive old jobs)
- Write: S3 (delete old files - optional)

**Token Reservation/Consumption Points:**
- None

**Retry and Timeout Behavior:**
- N/A

**Acceptance Criteria:**
- Command deletes jobs older than 90 days (configurable)
- Only deletes completed/failed/cancelled jobs
- Optionally deletes associated S3 files
- Logs cleanup statistics
- Scheduled to run daily

**Failure and Recovery Scenarios:**
- Cleanup failure: Log error, continue (non-critical)
- S3 deletion failure: Log warning, continue (non-critical)

**Test Coverage Expectations:**
- Feature test: Get job status
- Feature test: List jobs with filters
- Feature test: Cancel pending job (refunds tokens)
- Feature test: Retry failed job (creates new job)
- Feature test: Token reservation on job creation
- Feature test: Timeout handling
- Feature test: Job cleanup

---

## Summary

**Total Files to Create:** 20+
- 4 Services (BaseSegmindService, SegmindImageService, SegmindVideoService, SegmindAudioService)
- 3 Queue Jobs (GenerateImageJob, GenerateVideoJob, GenerateAudioJob)
- 2 Controllers (GenerationController, GenerationJobController)
- 4 Form Requests (GenerateImageRequest, GenerateVideoRequest, GenerateAudioRequest, GenerationJobListRequest)
- 1 Optional Service (TokenReservationService)
- 1 Optional Command (CleanupOldGenerationJobs)

**Total Files to Modify:** 5
- routes/api.php (add generation routes)
- routes/console.php (add cleanup job)
- database/seeders/ModelSeeder.php (add video/audio models)
- app/Models/GenerationJob.php (add helper methods if needed)
- app/Models/User.php (add reservation methods if needed)

**Dependencies:**
- Environment variables: SEGMIND_API_KEY, SEGMIND_API_BASE_URL (already configured)
- Packages: Guzzle (via Laravel HTTP), GD/ImageMagick (for thumbnails)
- Infrastructure: Redis (for queues), S3 (for storage)

**Token Flow Checklist:**
- [ ] Tokens reserved on job creation (balance -= tokens, no transaction)
- [ ] Tokens consumed on completion (transaction log created, reservation finalized)
- [ ] Tokens refunded on failure/cancellation (balance += tokens, no transaction)
- [ ] Reservation is atomic (transaction with row lock)
- [ ] Consumption is atomic (transaction + balance update)
- [ ] Refund is atomic (transaction with balance update)
- [ ] No double consumption (status check prevents duplicates)
- [ ] Balance reconciliation possible (balance = SUM(transactions) - SUM(reserved))

**Test Coverage Minimum:**
- 15+ feature tests for image generation
- 10+ feature tests for video generation
- 10+ feature tests for audio generation
- 10+ feature tests for job management
- Mock Segmind API in all tests
- Fake S3 storage in all tests
- Test token reservation/consumption/refund flows
- Test idempotency and retry behavior

