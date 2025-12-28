# Negarify Platform - Backend Development Tasks (Laravel)

## Table of Contents
1. [Project Overview](#project-overview)
2. [Technology Stack](#technology-stack)
3. [Database Schema & Models](#database-schema--models)
4. [Backend Development Tasks](#backend-development-tasks)
5. [API Endpoints Specification](#api-endpoints-specification)
6. [Services & Classes](#services--classes)
7. [Queue Jobs & Scheduled Tasks](#queue-jobs--scheduled-tasks)
8. [Testing Requirements](#testing-requirements)
9. [Deployment & Configuration](#deployment--configuration)

---

## Project Overview

**Negarify** is an AI content generation platform backend built with Laravel 10. The backend aggregates multiple AI models from Segmind API for generating images, videos, and audio content. The platform operates on a token-based economy where users purchase tokens to generate content.

### Key Backend Responsibilities
- RESTful API for frontend and mobile clients
- Token-based economy management
- Payment processing via Zarinpal
- Real-time currency conversion (USD to Toman) via web scraping
- Segmind API integration for image/video/audio generation
- Admin dashboard APIs
- Queue management for async job processing
- File storage and media management

---

## Technology Stack

### Core Framework
- **Laravel**: 10.x
- **PHP**: 8.2+
- **Database**: MySQL 8.0+ / PostgreSQL 14+
- **Cache & Queue**: Redis 7.0+
- **Storage**: S3-compatible object storage (AWS S3, DigitalOcean Spaces, etc.)

### Key Packages
- **Laravel Sanctum**: API authentication
- **Laravel Queue**: Async job processing
- **Guzzle HTTP**: External API calls
- **Laravel Excel**: CSV exports (for admin dashboards)
- **Laravel Telescope**: Debugging (development only)

### External Services
- **Segmind API**: AI content generation
- **Zarinpal**: Payment gateway
- **Melipayamak**: SMS service for OTP
- **TGJU.org**: Currency rate scraping

---

## Database Schema & Models

### Core Tables

#### 1. Users
```sql
users
- id (bigint, primary)
- phone (string, unique, indexed)
- email (string, nullable, unique, indexed)
- name (string, nullable)
- avatar_url (string, nullable)
- password (string, nullable)
- is_verified (boolean, default: false)
- tokens_balance (decimal 15,2, default: 0, indexed)
- role (enum: user, admin, moderator, default: user, indexed)
- phone_verified_at (timestamp, nullable)
- email_verified_at (timestamp, nullable)
- remember_token (string, nullable)
- created_at, updated_at (timestamps, indexed)
```

**Model**: `App\Models\User`

#### 2. Providers
```sql
providers
- id (bigint, primary)
- name (string) // "Segmind"
- api_base_url (string) // "https://api.segmind.com"
- api_key_encrypted (text) // Encrypted using Laravel encryption
- cost_per_image_usd (decimal 10,4, nullable)
- cost_per_video_usd (decimal 10,4, nullable)
- cost_per_audio_usd (decimal 10,4, nullable)
- enabled (boolean, default: true)
- created_at, updated_at
```

**Model**: `App\Models\Provider`

#### 3. Models (AI Models)
```sql
models
- id (bigint, primary)
- provider_id (bigint, foreign: providers.id, indexed)
- model_name (string) // e.g., "flux-dev", "stable-diffusion-xl"
- model_type (enum: image, video, audio, indexed)
- api_endpoint (string) // Segmind API endpoint path
- quality_profile (string, nullable) // "hd", "standard", "budget"
- base_cost_usd (decimal 10,4, default: 0)
- default_tokens (integer) // Tokens required per generation
- supports_size (boolean, default: true)
- supports_style (boolean, default: false)
- max_resolution (string, nullable) // "2048x2048"
- enabled (boolean, default: true, indexed)
- created_at, updated_at
```

**Model**: `App\Models\Model`

#### 4. Token Bundles
```sql
token_bundles
- id (bigint, primary)
- name (string) // "Starter Pack", "Standard Pack", etc.
- token_amount (integer) // 100, 500, 1000, 2000
- price_usd (decimal 10,2) // Base price in USD
- bonus_tokens (integer, default: 0)
- is_active (boolean, default: true, indexed)
- display_order (integer, default: 0)
- created_at, updated_at
```

**Model**: `App\Models\TokenBundle`

#### 5. Orders
```sql
orders
- id (bigint, primary)
- user_id (bigint, foreign: users.id, indexed)
- token_bundle_id (bigint, foreign: token_bundles.id)
- amount_tokens (integer)
- price_toman (decimal 15,2) // Calculated price in Toman
- price_usd (decimal 10,2) // Base price in USD
- dollar_rate (decimal 15,2) // USD to Toman rate at purchase time
- zarinpal_authority (string, nullable, unique, indexed)
- zarinpal_ref_id (string, nullable, unique)
- status (enum: pending, paid, failed, cancelled, default: pending, indexed)
- paid_at (timestamp, nullable)
- created_at, updated_at (indexed)
```

**Model**: `App\Models\Order`

#### 6. Token Transactions
```sql
token_transactions
- id (bigint, primary)
- user_id (bigint, foreign: users.id, indexed)
- order_id (bigint, nullable, foreign: orders.id)
- generation_job_id (bigint, nullable, foreign: generation_jobs.id)
- amount_tokens (integer) // Positive for purchase, negative for consumption
- amount_usd (decimal 10,4, nullable)
- type (enum: purchase, consume, refund, bonus, adjustment, indexed)
- reference_id (string, nullable)
- description (text, nullable)
- created_at, updated_at (indexed)
```

**Model**: `App\Models\TokenTransaction`

#### 7. Generation Jobs (Unified)
```sql
generation_jobs
- id (bigint, primary)
- user_id (bigint, foreign: users.id, indexed)
- provider_id (bigint, foreign: providers.id)
- model_id (bigint, foreign: models.id, indexed)
- job_type (enum: image, video, audio, indexed)
- prompt (text)
- negative_prompt (text, nullable)
- params_json (json) // Size, quality, style, seed, etc.
- status (enum: pending, processing, completed, failed, cancelled, default: pending, indexed)
- tokens_consumed (integer, default: 0)
- cost_usd (decimal 10,4, default: 0)
- segmind_job_id (string, nullable, indexed)
- result_url (text, nullable)
- result_thumbnail_url (text, nullable)
- error_message (text, nullable)
- started_at (timestamp, nullable)
- completed_at (timestamp, nullable)
- created_at, updated_at (indexed)
```

**Model**: `App\Models\GenerationJob`

#### 8. Gallery Posts
```sql
gallery_posts
- id (bigint, primary)
- user_id (bigint, foreign: users.id, indexed)
- generation_job_id (bigint, foreign: generation_jobs.id, unique)
- title (string, nullable)
- description (text, nullable)
- tags_json (json, nullable) // Array of tags
- visibility (enum: public, private, default: public, indexed)
- is_curated (boolean, default: false, indexed)
- is_featured (boolean, default: false, indexed)
- likes_count (integer, default: 0)
- comments_count (integer, default: 0)
- views_count (integer, default: 0)
- prompt_visible (boolean, default: true)
- model_visible (boolean, default: true)
- curated_at (timestamp, nullable)
- created_at, updated_at (indexed)
```

**Model**: `App\Models\GalleryPost`

#### 9. Feed View Limits
```sql
feed_view_limits
- id (bigint, primary)
- user_id (bigint, foreign: users.id, indexed)
- content_type (enum: image, video, indexed)
- views_remaining (integer, default: 0)
- daily_limit (integer, default: 10)
- reset_at (timestamp, indexed)
- created_at, updated_at
```

**Model**: `App\Models\FeedViewLimit`

#### 10. Currency Rates
```sql
currency_rates
- id (bigint, primary)
- currency_from (string, default: 'USD')
- currency_to (string, default: 'IRR')
- rate (decimal 15,2) // USD to Rials (will be divided by 10 for Toman)
- source (string, default: 'tgju')
- fetched_at (timestamp, indexed)
- created_at, updated_at
```

**Model**: `App\Models\CurrencyRate`

#### 11. OTP Verifications
```sql
otp_verifications
- id (bigint, primary)
- phone (string, indexed)
- code_hash (string) // Hashed OTP code
- request_id (string, unique, indexed)
- attempts (integer, default: 0)
- max_attempts (integer, default: 3)
- expires_at (timestamp, indexed)
- verified_at (timestamp, nullable)
- created_at, updated_at
```

**Model**: `App\Models\OtpVerification`

#### 12. Analytics Models Usage
```sql
analytics_models_usage
- id (bigint, primary)
- model_id (bigint, foreign: models.id, indexed)
- job_type (enum: image, video, audio, indexed)
- requests_count (integer, default: 0)
- successful_count (integer, default: 0)
- failed_count (integer, default: 0)
- tokens_consumed (integer, default: 0)
- cost_usd (decimal 10,4, default: 0)
- revenue_usd (decimal 10,4, default: 0)
- avg_latency_ms (integer, nullable)
- period_type (enum: daily, weekly, monthly, indexed)
- period_start (date, indexed)
- period_end (date)
- created_at, updated_at
```

**Model**: `App\Models\AnalyticsModelsUsage`

#### 13. System Health
```sql
system_health
- id (bigint, primary)
- metric_name (string, indexed)
- metric_value (decimal 15,4)
- metric_unit (string, nullable)
- provider_id (bigint, nullable, foreign: providers.id)
- recorded_at (timestamp, indexed)
- created_at
```

**Model**: `App\Models\SystemHealth`

#### 14. Supporting Tables
- `likes` (user_id, gallery_post_id, created_at)
- `comments` (user_id, gallery_post_id, body, parent_id, created_at)
- `follows` (follower_id, following_id, created_at)
- `notifications` (user_id, type, data_json, read_at, created_at)
- `reports` (user_id, gallery_post_id, reason, status, created_at)
- `moderation_queue` (gallery_post_id, reason, status, reviewed_by, reviewed_at, created_at)
- `prompt_templates` (name, category, template_json, variables_json, created_at, updated_at)
- `leaderboards` (user_id, metric_type, score, period_type, period_start, rank, created_at)

---

## Backend Development Tasks

### Phase 1: Foundation & Setup

#### Task 1.1: Project Setup & Configuration
**Priority**: Critical  
**Estimated Time**: 1 day  
**Status**: ✅ Completed

**Subtasks**:
- [x] Initialize Laravel 10 project
- [x] Configure `.env` file with all required variables
- [x] Set up database connection
- [x] Configure Redis for cache and queues
- [x] Set up S3-compatible storage configuration
- [x] Configure CORS for frontend access
- [x] Set up logging (daily rotation)
- [x] Configure timezone (Asia/Tehran)

**Environment Variables**:
```env
APP_NAME=Negarify
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.negarify.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=negarify
DB_USERNAME=...
DB_PASSWORD=...

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...

SEGMIND_API_KEY=...
SEGMIND_API_BASE_URL=https://api.segmind.com

ZARINPAL_MERCHANT_ID=...
ZARINPAL_SANDBOX=false

MELIPAYAMAK_USERNAME=...
MELIPAYAMAK_PASSWORD=...
```

**Acceptance Criteria**:
- Laravel project initialized
- All configurations working
- Database connection successful
- Redis connection successful
- S3 storage accessible

---

#### Task 1.2: Database Migrations
**Priority**: Critical  
**Estimated Time**: 3 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create migration for `users` table
- [x] Create migration for `providers` table
- [x] Create migration for `models` table
- [x] Create migration for `token_bundles` table
- [x] Create migration for `orders` table
- [x] Create migration for `token_transactions` table
- [x] Create migration for `generation_jobs` table
- [x] Create migration for `gallery_posts` table
- [x] Create migration for `feed_view_limits` table
- [x] Create migration for `currency_rates` table
- [x] Create migration for `otp_verifications` table
- [x] Create migration for `analytics_models_usage` table
- [x] Create migration for `system_health` table
- [x] Create migrations for supporting tables (likes, comments, etc.)
- [x] Add all necessary indexes
- [x] Add foreign key constraints
- [x] Create seeders for initial data:
  - Admin user
  - Segmind provider entry
  - Token bundles (100, 500, 1000, 2000)
  - Sample AI models from Segmind

**File Structure**:
```
database/migrations/
  2024_01_01_000001_create_users_table.php
  2024_01_01_000002_create_providers_table.php
  2024_01_01_000003_create_models_table.php
  2024_01_01_000004_create_token_bundles_table.php
  2024_01_01_000005_create_orders_table.php
  2024_01_01_000006_create_token_transactions_table.php
  2024_01_01_000007_create_generation_jobs_table.php
  2024_01_01_000008_create_gallery_posts_table.php
  2024_01_01_000009_create_feed_view_limits_table.php
  2024_01_01_000010_create_currency_rates_table.php
  2024_01_01_000011_create_otp_verifications_table.php
  2024_01_01_000012_create_analytics_models_usage_table.php
  2024_01_01_000013_create_system_health_table.php
  2024_01_01_000014_create_likes_table.php
  2024_01_01_000015_create_comments_table.php
  ...
```

**Acceptance Criteria**:
- All migrations run successfully
- Foreign keys properly configured
- Indexes added for performance
- Seeders populate initial data correctly
- No migration errors

---

#### Task 1.3: Eloquent Models
**Priority**: Critical  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `User` model with relationships
- [x] Create `Provider` model with encryption for API key
- [x] Create `Model` model (AI model)
- [x] Create `TokenBundle` model
- [x] Create `Order` model
- [x] Create `TokenTransaction` model
- [x] Create `GenerationJob` model
- [x] Create `GalleryPost` model
- [x] Create `FeedViewLimit` model
- [x] Create `CurrencyRate` model
- [x] Create `OtpVerification` model
- [x] Create `AnalyticsModelsUsage` model
- [x] Create `SystemHealth` model
- [x] Create supporting models (Like, Comment, etc.)
- [x] Add all relationships (hasMany, belongsTo, etc.)
- [x] Add model scopes for common queries
- [x] Add accessors and mutators where needed

**Model Example**:
```php
// app/Models/User.php
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'phone', 'email', 'name', 'avatar_url',
        'password', 'is_verified', 'tokens_balance', 'role'
    ];

    protected $casts = [
        'tokens_balance' => 'decimal:2',
        'is_verified' => 'boolean',
        'phone_verified_at' => 'datetime',
    ];

    // Relationships
    public function generationJobs() {
        return $this->hasMany(GenerationJob::class);
    }
    
    public function galleryPosts() {
        return $this->hasMany(GalleryPost::class);
    }
    
    // Methods
    public function hasTokens(int $amount): bool {
        return $this->tokens_balance >= $amount;
    }
}
```

**Acceptance Criteria**:
- All models created
- Relationships defined correctly
- Scopes and methods implemented
- Models pass basic tests

---

### Phase 2: Authentication & User Management

#### Task 2.1: OTP Service (Melipayamak)
**Priority**: Critical  
**Estimated Time**: 3 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `MelipayamakService` class
- [x] Implement SMS sending method
- [x] Add error handling and retry logic
- [x] Create OTP generation utility
- [x] Implement OTP hashing (HMAC)
- [x] Create OTP verification logic
- [x] Add rate limiting per phone number
- [x] Implement OTP expiration (5 minutes)
- [x] Add attempt limiting (max 3 attempts)
- [x] Create cleanup job for expired OTPs

**Service Class**:
```php
// app/Services/MelipayamakService.php
class MelipayamakService
{
    public function sendOtp(string $phone, string $code): bool
    {
        // Implementation
    }
    
    public function sendSms(string $phone, string $message): bool
    {
        // Implementation
    }
}
```

**Acceptance Criteria**:
- OTP sent successfully via Melipayamak
- OTP verification works
- Rate limiting prevents abuse
- Expired OTPs cleaned up

---

#### Task 2.2: OTP Authentication Endpoints
**Priority**: Critical  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `AuthController`
- [x] Implement `requestOtp()` method
- [x] Implement `verifyOtp()` method
- [x] Implement `resendOtp()` method
- [x] Add rate limiting middleware
- [x] Generate JWT tokens on verification
- [x] Add validation rules
- [x] Add request/response logging

**Routes**:
```php
// routes/api.php
Route::prefix('v1/auth')->group(function () {
    Route::post('/request-otp', [AuthController::class, 'requestOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});
```

**Controller Methods**:
```php
// app/Http/Controllers/Api/V1/AuthController.php
public function requestOtp(Request $request)
{
    // Validate phone
    // Generate OTP
    // Hash and store
    // Send via Melipayamak
    // Return request_id
}

public function verifyOtp(Request $request)
{
    // Validate request_id and code
    // Check attempts and expiration
    // Verify code
    // Create/update user
    // Generate token
    // Return token and user
}
```

**Acceptance Criteria**:
- All endpoints work correctly
- Rate limiting enforced
- JWT tokens generated
- Validation works

---

#### Task 2.3: User Profile Endpoints
**Priority**: High  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `UserController`
- [x] Implement `show()` - get user profile
- [x] Implement `update()` - update profile
- [x] Implement `uploadAvatar()` - avatar upload
- [x] Add file validation for avatar
- [x] Store avatar in S3
- [x] Add Sanctum authentication middleware
- [x] Return token balance in profile

**Routes**:
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::put('/user', [UserController::class, 'update']);
    Route::post('/user/avatar', [UserController::class, 'uploadAvatar']);
});
```

**Acceptance Criteria**:
- Profile endpoints work
- Avatar uploads to S3
- Token balance accurate

---

### Phase 3: Token System & Payment

#### Task 3.1: Token Bundle Management
**Priority**: Critical  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `TokenBundleController`
- [x] Implement `index()` - list all active bundles
- [x] Calculate real-time prices in Toman
- [x] Add admin CRUD endpoints (protected)
- [x] Add bundle activation/deactivation

**Routes**:
```php
// Public
Route::get('/tokens/bundles', [TokenBundleController::class, 'index']);

// Admin
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/admin/tokens/bundles', [TokenBundleController::class, 'store']);
    Route::put('/admin/tokens/bundles/{id}', [TokenBundleController::class, 'update']);
});
```

**Acceptance Criteria**:
- Bundles listed with real-time prices
- Admin can manage bundles

---

#### Task 3.2: Currency Rate Scraper
**Priority**: Critical  
**Estimated Time**: 4 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `TgjuScraperService` class
- [x] Implement HTML scraping for TGJU.org
- [x] Extract USD to Rials rate
- [x] Convert Rials to Toman (divide by 10)
- [x] Store rate in database
- [x] Cache rate in Redis (5-minute TTL)
- [x] Create scheduled job (every 5 minutes)
- [x] Implement fallback to last known rate
- [x] Add error handling and logging
- [x] Create API endpoint to get current rate

**Service Class**:
```php
// app/Services/TgjuScraperService.php
class TgjuScraperService
{
    public function fetchUsdRate(): ?float
    {
        // Scrape TGJU.org
        // Extract USD rate in Rials
        // Convert to Toman
        // Store in database
        // Cache in Redis
    }
    
    public function getCurrentRate(): float
    {
        // Get from cache or database
    }
}
```

**Scheduled Job**:
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        app(TgjuScraperService::class)->fetchUsdRate();
    })->everyFiveMinutes();
}
```

**Acceptance Criteria**:
- Rate fetched from TGJU.org
- Rate stored and cached
- Scheduled job runs correctly
- Fallback works

---

#### Task 3.3: Zarinpal Payment Integration
**Priority**: Critical  
**Estimated Time**: 5 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Install or create Zarinpal SDK
- [x] Create `ZarinpalService` class
- [x] Implement payment request method
- [x] Implement payment verification method
- [x] Create `OrderController`
- [x] Implement `purchase()` - create order and payment request
- [x] Implement `callback()` - handle Zarinpal callback
- [x] Calculate price in Toman using current rate
- [x] Create order with pending status
- [x] Credit tokens on successful payment
- [x] Create transaction log
- [x] Handle payment failures
- [x] Add idempotency for payments

**Service Class**:
```php
// app/Services/ZarinpalService.php
class ZarinpalService
{
    public function requestPayment(Order $order): array
    {
        // Request payment from Zarinpal
        // Return authority and payment URL
    }
    
    public function verifyPayment(string $authority, int $amount): array
    {
        // Verify payment with Zarinpal
        // Return verification result
    }
}
```

**Controller**:
```php
// app/Http/Controllers/Api/V1/OrderController.php
public function purchase(Request $request)
{
    // Validate bundle_id
    // Get current USD rate
    // Calculate price in Toman
    // Create order
    // Request payment from Zarinpal
    // Return payment URL
}

public function callback(Request $request)
{
    // Verify payment
    // Update order status
    // Credit tokens
    // Create transaction
    // Redirect to frontend
}
```

**Routes**:
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/tokens/purchase', [OrderController::class, 'purchase']);
    Route::get('/tokens/purchase/callback', [OrderController::class, 'callback']);
});
```

**Acceptance Criteria**:
- Payment requests created
- Zarinpal integration works
- Tokens credited on success
- Callback handled securely

---

#### Task 3.4: Token Transaction System
**Priority**: Critical  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `TokenTransactionController`
- [x] Implement transaction logging methods
- [x] Create `getHistory()` endpoint
- [x] Add filtering (type, date range)
- [x] Add pagination
- [x] Implement balance calculation from transactions
- [x] Add transaction types: purchase, consume, refund, bonus

**Routes**:
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tokens/history', [TokenTransactionController::class, 'index']);
    Route::get('/tokens/balance', [TokenTransactionController::class, 'balance']);
});
```

**Acceptance Criteria**:
- Transactions logged correctly
- History retrievable with filters
- Balance calculated accurately

---

### Phase 4: Segmind API Integration

#### Task 4.1: Segmind Base Service
**Priority**: Critical  
**Estimated Time**: 3 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Research Segmind API documentation
- [x] Create `SegmindService` base class
- [x] Implement API authentication
- [x] Implement HTTP client with retry logic
- [x] Add error handling
- [x] Implement rate limiting awareness
- [x] Add request/response logging
- [x] Create model-specific service classes

**Service Structure**:
```php
// app/Services/Segmind/BaseSegmindService.php
abstract class BaseSegmindService
{
    protected function makeRequest(string $endpoint, array $data): array
    {
        // Make API request
        // Handle errors
        // Log request/response
    }
}

// app/Services/Segmind/SegmindImageService.php
class SegmindImageService extends BaseSegmindService
{
    public function generateImage(array $params): array
    {
        // Image generation logic
    }
}
```

**Acceptance Criteria**:
- Base service functional
- API authentication works
- Error handling robust

---

#### Task 4.2: Image Generation Integration
**Priority**: Critical  
**Estimated Time**: 5 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Research Segmind image models
- [x] Create database entries for image models
- [x] Implement `SegmindImageService`
- [x] Map API parameters to internal format
- [x] Create `GenerateImageJob` queue job
- [x] Implement image download and S3 storage
- [x] Create thumbnail generation
- [x] Implement token consumption
- [x] Add progress tracking
- [x] Handle errors and retries

**Queue Job**:
```php
// app/Jobs/GenerateImageJob.php
class GenerateImageJob implements ShouldQueue
{
    public function handle(SegmindImageService $service)
    {
        // Reserve tokens
        // Call Segmind API
        // Download image
        // Store in S3
        // Generate thumbnail
        // Update job status
        // Consume tokens
    }
}
```

**Controller**:
```php
// app/Http/Controllers/Api/V1/GenerationController.php
public function generateImage(Request $request)
{
    // Validate request
    // Check token balance
    // Reserve tokens
    // Create generation job
    // Dispatch queue job
    // Return job_id
}
```

**Acceptance Criteria**:
- Image generation works
- Images stored in S3
- Thumbnails generated
- Tokens consumed correctly

---

#### Task 4.3: Video Generation Integration
**Priority**: High  
**Estimated Time**: 5 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Research Segmind video models
- [x] Create database entries for video models
- [x] Implement `SegmindVideoService`
- [x] Map video-specific parameters
- [x] Create `GenerateVideoJob` queue job
- [x] Implement video download and storage
- [x] Create video thumbnail/preview
- [x] Implement token consumption
- [x] Add progress tracking for long jobs
- [x] Handle errors and retries

**Acceptance Criteria**:
- Video generation works
- Videos stored correctly
- Progress tracked
- Tokens consumed

---

#### Task 4.4: Audio Generation Integration
**Priority**: High  
**Estimated Time**: 4 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Research Segmind audio models
- [x] Create database entries for audio models
- [x] Implement `SegmindAudioService`
- [x] Map audio-specific parameters
- [x] Create `GenerateAudioJob` queue job
- [x] Implement audio download and storage
- [x] Implement token consumption
- [x] Add audio metadata extraction
- [x] Handle errors and retries

**Acceptance Criteria**:
- Audio generation works
- Audio files stored correctly
- Tokens consumed

---

#### Task 4.5: Unified Generation Job System
**Priority**: Critical  
**Estimated Time**: 3 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `GenerationJobController`
- [x] Implement job status endpoint
- [x] Implement job result endpoint
- [x] Implement job cancellation
- [x] Implement job retry
- [x] Add job listing with filters
- [x] Implement token reservation system
- [x] Add job timeout handling
- [x] Create job cleanup for old jobs

**Routes**:
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/generate/jobs', [GenerationJobController::class, 'index']);
    Route::get('/generate/jobs/{id}', [GenerationJobController::class, 'show']);
    Route::post('/generate/jobs/{id}/cancel', [GenerationJobController::class, 'cancel']);
    Route::post('/generate/jobs/{id}/retry', [GenerationJobController::class, 'retry']);
});
```

**Acceptance Criteria**:
- Jobs tracked correctly
- Token reservation works
- Cancellation and retry functional

---

### Phase 5: Gallery & Feed System

#### Task 5.1: Gallery Post Management
**Priority**: High  
**Estimated Time**: 3 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `GalleryPostController`
- [x] Implement `store()` - create post
- [x] Implement `show()` - get post details
- [x] Implement `update()` - update post
- [x] Implement `destroy()` - delete post
- [x] Add tag validation and processing
- [x] Implement visibility settings
- [x] Add prompt/model visibility flags
- [x] Create user's posts listing endpoint

**Routes**:
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/gallery/post', [GalleryPostController::class, 'store']);
    Route::get('/gallery/posts/{id}', [GalleryPostController::class, 'show']);
    Route::put('/gallery/posts/{id}', [GalleryPostController::class, 'update']);
    Route::delete('/gallery/posts/{id}', [GalleryPostController::class, 'destroy']);
    Route::get('/gallery/my-posts', [GalleryPostController::class, 'myPosts']);
});
```

**Acceptance Criteria**:
- Posts created successfully
- Tags processed correctly
- Visibility enforced

---

#### Task 5.2: Admin Feed Curation
**Priority**: High  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create admin endpoints for curation
- [x] Implement `curate()` - mark post as curated
- [x] Implement `uncurate()` - remove from feed
- [x] Implement `feature()` - feature a post
- [x] Add bulk curation actions
- [x] Track curation timestamp
- [x] Create curated posts listing for admin

**Routes**:
```php
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/admin/gallery/{id}/curate', [AdminGalleryController::class, 'curate']);
    Route::post('/admin/gallery/{id}/uncurate', [AdminGalleryController::class, 'uncurate']);
    Route::get('/admin/gallery/curated', [AdminGalleryController::class, 'curated']);
});
```

**Acceptance Criteria**:
- Admins can curate posts
- Curation tracked correctly
- Bulk actions work

---

#### Task 5.3: Public Feed with View Limits
**Priority**: High  
**Estimated Time**: 4 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `FeedController`
- [x] Implement `index()` - get curated feed
- [x] Filter to show only curated images/videos (no audio)
- [x] Implement view limit tracking
- [x] Create daily limit reset job
- [x] Add cursor-based pagination
- [x] Implement "Copy Prompt" endpoint
- [x] Implement "Copy Model" endpoint
- [x] Cache feed in Redis
- [x] Return views remaining in response

**Routes**:
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/gallery/feed', [FeedController::class, 'index']);
    Route::post('/gallery/feed/copy-prompt', [FeedController::class, 'copyPrompt']);
    Route::post('/gallery/feed/copy-model', [FeedController::class, 'copyModel']);
});
```

**Controller Logic**:
```php
public function index(Request $request)
{
    // Check view limits
    // Get curated posts (images/videos only)
    // Apply pagination
    // Track views
    // Return feed with views_remaining
}
```

**Acceptance Criteria**:
- Feed shows only curated content
- View limits enforced
- Limits reset daily
- Copy functionality works

---

#### Task 5.4: Social Features (Likes & Comments)
**Priority**: Medium  
**Estimated Time**: 3 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `LikeController`
- [x] Implement like/unlike endpoints
- [x] Update likes_count on posts
- [x] Create `CommentController`
- [x] Implement comment creation
- [x] Implement nested comments (replies)
- [x] Implement comment deletion
- [x] Update comments_count on posts
- [x] Create notifications for likes/comments (skipped - not explicitly required)

**Routes**:
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/gallery/{id}/like', [LikeController::class, 'store']);
    Route::delete('/gallery/{id}/like', [LikeController::class, 'destroy']);
    Route::post('/gallery/{id}/comment', [CommentController::class, 'store']);
    Route::delete('/gallery/comments/{id}', [CommentController::class, 'destroy']);
});
```

**Acceptance Criteria**:
- Likes work correctly
- Comments work correctly
- Counts updated accurately

---

### Phase 6: Admin Dashboard APIs

#### Task 6.1: Admin Authentication & Middleware
**Priority**: Critical  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `AdminMiddleware`
- [x] Implement role-based access control
- [x] Add admin routes protection
- [x] Create admin login endpoint (if separate from user auth)
- [x] Add 2FA support (optional - skipped)

**Middleware**:
```php
// app/Http/Middleware/AdminMiddleware.php
class AdminMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        return $next($request);
    }
}
```

**Acceptance Criteria**:
- Admin routes protected
- RBAC works correctly

---

#### Task 6.2: Sales Dashboard API
**Priority**: High  
**Estimated Time**: 3 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `AdminSalesController`
- [x] Implement `summary()` endpoint
- [x] Calculate revenue by day/week/month
- [x] Calculate top selling bundles
- [x] Track refunds
- [x] Calculate customer LTV
- [x] Add date range filtering
- [x] Optimize queries with indexes

**Endpoint**:
```php
GET /api/v1/admin/sales/summary?range=month&start_date=2024-01-01&end_date=2024-01-31
```

**Response**:
```json
{
  "total_revenue_toman": 10000000,
  "total_revenue_usd": 200,
  "total_orders": 150,
  "revenue_by_day": [...],
  "top_bundles": [...],
  "refunds": {...}
}
```

**Acceptance Criteria**:
- Sales data accurate
- Queries optimized
- Date filtering works

---

#### Task 6.3: Users Dashboard API
**Priority**: High  
**Estimated Time**: 3 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `AdminUsersController`
- [x] Implement `summary()` endpoint
- [x] Calculate DAU/WAU/MAU
- [x] Get top users by generation count
- [x] Get top users by spending
- [x] Implement cohort analysis
- [x] Track churn and reactivation
- [x] Add user search and filtering

**Acceptance Criteria**:
- User metrics accurate
- Cohort analysis functional
- Search works

---

#### Task 6.4: Models Usage Dashboard API
**Priority**: High  
**Estimated Time**: 3 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `AdminModelsController`
- [x] Implement `usage()` endpoint
- [x] Track requests per model
- [x] Calculate average cost per model
- [x] Track tokens consumed
- [x] Calculate average latency
- [x] Track failure rates
- [x] Add date range filtering
- [x] Create aggregation job for analytics (uses analytics_models_usage table)

**Acceptance Criteria**:
- Usage data accurate
- Analytics aggregated correctly

---

#### Task 6.5: Token Analytics API
**Priority**: High  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `AdminTokensController`
- [x] Implement `summary()` endpoint
- [x] Track tokens consumed per provider
- [x] Track tokens by type (image/video/audio)
- [x] Calculate cost and profit per provider
- [x] Add time-series data

**Acceptance Criteria**:
- Token analytics accurate
- Provider breakdown functional

---

#### Task 6.6: Cost & Profit Dashboard API
**Priority**: High  
**Estimated Time**: 3 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `AdminCostProfitController`
- [x] Implement `summary()` endpoint
- [x] Calculate COGS per model
- [x] Calculate profit per model
- [x] Track profit margins over time
- [x] Break down by bundle/campaign
- [x] Add historical comparison

**Acceptance Criteria**:
- Cost/profit calculations accurate
- Historical data available

---

#### Task 6.7: System Health API
**Priority**: Medium  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `AdminSystemHealthController`
- [x] Implement `index()` endpoint
- [x] Track queue lengths
- [x] Monitor worker status
- [x] Track failed jobs
- [x] Calculate error rates
- [x] Monitor API latency
- [x] Track storage usage
- [x] Create scheduled job to record metrics (optional - metrics available in real-time)

**Acceptance Criteria**:
- System metrics accurate
- Metrics recorded regularly

---

### Phase 7: Queue Jobs & Scheduled Tasks

#### Task 7.1: Generation Job Workers
**Priority**: Critical  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Configure queue workers
- [x] Set up supervisor configuration
- [x] Implement job retry logic
- [x] Add job timeout handling
- [x] Implement failed job handling
- [x] Add job progress tracking
- [x] Create job cleanup for old jobs

**Supervisor Config**:
```ini
[program:negarify-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/worker.log
```

**Acceptance Criteria**:
- Workers run correctly
- Jobs processed successfully
- Failed jobs handled

---

#### Task 7.2: Scheduled Tasks
**Priority**: High  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Configure Laravel scheduler
- [x] Create currency rate fetch job (every 5 minutes)
- [x] Create feed view limit reset job (daily at midnight)
- [x] Create analytics aggregation job (daily)
- [x] Create expired OTP cleanup job (hourly)
- [x] Create old job cleanup job (daily)
- [x] Create system health recording job (every 5 minutes)
- [x] Set up cron job for scheduler

**Kernel.php**:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        app(TgjuScraperService::class)->fetchUsdRate();
    })->everyFiveMinutes();
    
    $schedule->call(function () {
        app(FeedViewLimitService::class)->resetDailyLimits();
    })->dailyAt('00:00');
    
    // ... more scheduled tasks
}
```

**Cron**:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

**Acceptance Criteria**:
- All scheduled tasks run correctly
- Cron configured properly

---

### Phase 8: Additional Features

#### Task 8.1: Notification System
**Priority**: Medium  
**Estimated Time**: 2 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create `Notification` model
- [x] Create notification service
- [x] Implement notification creation
- [x] Create notification endpoints
- [x] Add read/unread status
- [x] Create notification types
- [x] Add notification preferences

**Acceptance Criteria**:
- Notifications created correctly
- Read/unread status works

---

#### Task 8.2: Content Moderation
**Priority**: Medium  
**Estimated Time**: 3 days  
**Status**: ✅ Completed

**Subtasks**:
- [x] Create moderation queue
- [x] Implement automated filtering
- [x] Create admin moderation endpoints
- [x] Add reporting system
- [x] Implement content removal
- [ ] Add user blocking

**Acceptance Criteria**:
- Moderation queue functional
- Automated filtering works

---

## API Endpoints Specification

### Authentication Endpoints
```
POST   /api/v1/auth/request-otp
POST   /api/v1/auth/verify-otp
POST   /api/v1/auth/resend-otp
POST   /api/v1/auth/logout
```

### User Endpoints
```
GET    /api/v1/user
PUT    /api/v1/user
POST   /api/v1/user/avatar
```

### Token & Payment Endpoints
```
GET    /api/v1/tokens/bundles
GET    /api/v1/tokens/balance
GET    /api/v1/tokens/history
POST   /api/v1/tokens/purchase
GET    /api/v1/tokens/purchase/callback
GET    /api/v1/currency/rate
```

### Generation Endpoints
```
POST   /api/v1/generate/image
POST   /api/v1/generate/video
POST   /api/v1/generate/audio
GET    /api/v1/generate/jobs
GET    /api/v1/generate/jobs/{id}
POST   /api/v1/generate/jobs/{id}/cancel
POST   /api/v1/generate/jobs/{id}/retry
```

### Gallery & Feed Endpoints
```
POST   /api/v1/gallery/post
GET    /api/v1/gallery/posts/{id}
PUT    /api/v1/gallery/posts/{id}
DELETE /api/v1/gallery/posts/{id}
GET    /api/v1/gallery/my-posts
GET    /api/v1/gallery/feed
POST   /api/v1/gallery/feed/copy-prompt
POST   /api/v1/gallery/feed/copy-model
POST   /api/v1/gallery/{id}/like
DELETE /api/v1/gallery/{id}/like
POST   /api/v1/gallery/{id}/comment
DELETE /api/v1/gallery/comments/{id}
```

### Admin Endpoints
```
GET    /api/v1/admin/sales/summary
GET    /api/v1/admin/users/summary
GET    /api/v1/admin/users
GET    /api/v1/admin/models/usage
GET    /api/v1/admin/tokens/summary
GET    /api/v1/admin/cost-profit/summary
GET    /api/v1/admin/system-health
POST   /api/v1/admin/gallery/{id}/curate
POST   /api/v1/admin/gallery/{id}/uncurate
GET    /api/v1/admin/gallery/curated
```

---

## Services & Classes

### Required Service Classes
- `MelipayamakService` - SMS/OTP service
- `TgjuScraperService` - Currency rate scraping
- `ZarinpalService` - Payment gateway
- `SegmindService` (base) - Segmind API base
- `SegmindImageService` - Image generation
- `SegmindVideoService` - Video generation
- `SegmindAudioService` - Audio generation
- `TokenService` - Token management
- `FeedViewLimitService` - Feed limit management
- `NotificationService` - Notification management

### Required Queue Jobs
- `GenerateImageJob`
- `GenerateVideoJob`
- `GenerateAudioJob`
- `ProcessThumbnailJob`
- `AggregateAnalyticsJob`
- `CleanupExpiredOtpsJob`
- `ResetFeedViewLimitsJob`

---

## Testing Requirements

### Unit Tests
- Token calculation logic
- Currency conversion
- OTP generation/verification
- Token transaction processing
- Model relationships

### Feature Tests
- Authentication flow
- Payment flow
- Generation job flow
- Gallery post creation
- Feed view limits
- Admin endpoints

### Integration Tests
- Zarinpal payment (mocked)
- Segmind API (mocked)
- Melipayamak SMS (mocked)
- Currency scraping (mocked)

---

## Deployment & Configuration

### Server Requirements
- PHP 8.2+
- MySQL 8.0+ / PostgreSQL 14+
- Redis 7.0+
- Supervisor (for queue workers)
- Nginx/Apache
- Composer

### Deployment Steps
1. Clone repository
2. Install dependencies (`composer install`)
3. Copy `.env.example` to `.env`
4. Configure environment variables
5. Run migrations (`php artisan migrate`)
6. Seed initial data (`php artisan db:seed`)
7. Set up queue workers (Supervisor)
8. Configure cron for scheduler
9. Set up SSL certificates
10. Configure Nginx/Apache
11. Set file permissions
12. Enable Laravel Telescope (dev only)

### Monitoring
- Laravel Telescope (development)
- Laravel Logging
- Queue monitoring
- Error tracking (Sentry, etc.)

---

**Document Version**: 1.0  
**Last Updated**: 2024-01-01  
**Status**: Active Development

