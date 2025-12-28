# Negarify Platform - Task-Based Development Document

## Table of Contents
1. [Project Overview](#project-overview)
2. [Architecture & Technology Stack](#architecture--technology-stack)
3. [Database Schema & Models](#database-schema--models)
4. [Core Features & Tasks](#core-features--tasks)
5. [API Endpoints](#api-endpoints)
6. [Admin Dashboard Features](#admin-dashboard-features)
7. [Implementation Priority](#implementation-priority)

---

## Project Overview

**Negarify** is an AI content generation platform that aggregates multiple AI models from Segmind API for generating images, videos, and audio content. The platform operates on a token-based economy where users purchase tokens to generate content.

### Key Characteristics
- **Backend**: Laravel 10 API
- **Frontend**: Vue.js 3 + Vite
- **Payment Gateway**: Zarinpal (Iranian payment gateway)
- **AI Provider**: Segmind API (https://www.segmind.com/models/all)
- **Content Types**: Images, Videos, Audio
- **Currency**: Toman (IRR) with real-time USD conversion from TGJU.org

### Platform Scope
- Token-based economy with fixed packages (100, 500, 1000, 2000 tokens)
- Real-time USD to Toman price conversion via web scraping
- Curated public feed (admin-selected images and videos only)
- Prompt and model copying from feed items
- Admin dashboard for comprehensive analytics and management
- OTP authentication via Melipayamak SMS service

---

## Architecture & Technology Stack

### Backend Stack
- **Framework**: Laravel 10
- **Database**: MySQL/PostgreSQL
- **Cache & Queue**: Redis
- **Storage**: S3-compatible object storage
- **Authentication**: Laravel Sanctum (JWT tokens)
- **Queue System**: Laravel Queues with Redis driver

### Frontend Stack
- **Framework**: Vue.js 3
- **Build Tool**: Vite
- **UI Library**: (To be determined - e.g., Vuetify, Element Plus, or Tailwind CSS)

### External Services
- **AI Provider**: Segmind API
- **SMS Service**: Melipayamak
- **Payment Gateway**: Zarinpal
- **Currency Data**: TGJU.org (web scraping)

---

## Database Schema & Models

### Core Tables

#### 1. Users
```sql
users
- id (bigint, primary)
- phone (string, unique)
- email (string, nullable, unique)
- name (string, nullable)
- avatar_url (string, nullable)
- password (string, nullable)
- is_verified (boolean, default: false)
- tokens_balance (decimal 15,2, default: 0)
- role (enum: user, admin, moderator, default: user)
- phone_verified_at (timestamp, nullable)
- email_verified_at (timestamp, nullable)
- remember_token (string, nullable)
- created_at, updated_at
```

#### 2. Providers
```sql
providers
- id (bigint, primary)
- name (string) // e.g., "Segmind"
- api_base_url (string) // e.g., "https://api.segmind.com"
- api_key_encrypted (text) // Encrypted API key
- cost_per_image_usd (decimal 10,4, nullable)
- cost_per_video_usd (decimal 10,4, nullable)
- cost_per_audio_usd (decimal 10,4, nullable)
- enabled (boolean, default: true)
- created_at, updated_at
```

#### 3. Models
```sql
models
- id (bigint, primary)
- provider_id (bigint, foreign: providers.id)
- model_name (string) // e.g., "flux-dev", "stable-diffusion-xl"
- model_type (enum: image, video, audio)
- api_endpoint (string) // Segmind API endpoint path
- quality_profile (string, nullable) // e.g., "hd", "standard", "budget"
- base_cost_usd (decimal 10,4, default: 0)
- default_tokens (integer) // Tokens required per generation
- supports_size (boolean, default: true)
- supports_style (boolean, default: false)
- max_resolution (string, nullable) // e.g., "2048x2048"
- enabled (boolean, default: true)
- created_at, updated_at
```

#### 4. Token Bundles
```sql
token_bundles
- id (bigint, primary)
- name (string) // e.g., "Starter Pack"
- token_amount (integer) // 100, 500, 1000, 2000
- price_usd (decimal 10,2) // Base price in USD
- bonus_tokens (integer, default: 0) // Optional bonus
- is_active (boolean, default: true)
- display_order (integer, default: 0)
- created_at, updated_at
```

#### 5. Orders
```sql
orders
- id (bigint, primary)
- user_id (bigint, foreign: users.id)
- token_bundle_id (bigint, foreign: token_bundles.id)
- amount_tokens (integer)
- price_toman (decimal 15,2) // Calculated price in Toman
- price_usd (decimal 10,2) // Base price in USD
- dollar_rate (decimal 15,2) // USD to Toman rate at purchase time
- zarinpal_authority (string, nullable) // Zarinpal payment authority
- zarinpal_ref_id (string, nullable) // Zarinpal reference ID
- status (enum: pending, paid, failed, cancelled, default: pending)
- paid_at (timestamp, nullable)
- created_at, updated_at
```

#### 6. Token Transactions
```sql
token_transactions
- id (bigint, primary)
- user_id (bigint, foreign: users.id)
- order_id (bigint, nullable, foreign: orders.id)
- generation_job_id (bigint, nullable) // Reference to image_jobs, video_jobs, or audio_jobs
- amount_tokens (integer) // Positive for purchase, negative for consumption
- amount_usd (decimal 10,4, nullable)
- type (enum: purchase, consume, refund, bonus, adjustment)
- reference_id (string, nullable) // External reference
- description (text, nullable)
- created_at, updated_at
```

#### 7. Generation Jobs (Unified)
```sql
generation_jobs
- id (bigint, primary)
- user_id (bigint, foreign: users.id)
- provider_id (bigint, foreign: providers.id)
- model_id (bigint, foreign: models.id)
- job_type (enum: image, video, audio)
- prompt (text)
- negative_prompt (text, nullable)
- params_json (json) // Size, quality, style, seed, etc.
- status (enum: pending, processing, completed, failed, cancelled, default: pending)
- tokens_consumed (integer, default: 0)
- cost_usd (decimal 10,4, default: 0)
- segmind_job_id (string, nullable) // Segmind API job ID
- result_url (text, nullable) // URL to generated content
- result_thumbnail_url (text, nullable)
- error_message (text, nullable)
- started_at (timestamp, nullable)
- completed_at (timestamp, nullable)
- created_at, updated_at
```

#### 8. Gallery Posts
```sql
gallery_posts
- id (bigint, primary)
- user_id (bigint, foreign: users.id)
- generation_job_id (bigint, foreign: generation_jobs.id)
- title (string, nullable)
- description (text, nullable)
- tags_json (json, nullable) // Array of tags
- visibility (enum: public, private, default: public)
- is_curated (boolean, default: false) // Admin-selected for feed
- is_featured (boolean, default: false)
- likes_count (integer, default: 0)
- comments_count (integer, default: 0)
- views_count (integer, default: 0)
- prompt_visible (boolean, default: true) // Allow prompt copying
- model_visible (boolean, default: true) // Allow model copying
- curated_at (timestamp, nullable) // When admin added to feed
- created_at, updated_at
```

#### 9. Feed View Limits
```sql
feed_view_limits
- id (bigint, primary)
- user_id (bigint, foreign: users.id)
- content_type (enum: image, video)
- views_remaining (integer, default: 0)
- daily_limit (integer, default: 10) // Configurable per user tier
- reset_at (timestamp) // When limit resets
- created_at, updated_at
```

#### 10. Currency Rates
```sql
currency_rates
- id (bigint, primary)
- currency_from (string, default: 'USD')
- currency_to (string, default: 'IRR')
- rate (decimal 15,2) // USD to Rials (will be divided by 10 for Toman)
- source (string, default: 'tgju')
- fetched_at (timestamp)
- created_at, updated_at
```

#### 11. OTP Verifications
```sql
otp_verifications
- id (bigint, primary)
- phone (string, indexed)
- code_hash (string) // Hashed OTP code
- request_id (string, unique) // Unique request identifier
- attempts (integer, default: 0)
- max_attempts (integer, default: 3)
- expires_at (timestamp)
- verified_at (timestamp, nullable)
- created_at, updated_at
```

#### 12. Analytics Models Usage
```sql
analytics_models_usage
- id (bigint, primary)
- model_id (bigint, foreign: models.id)
- job_type (enum: image, video, audio)
- requests_count (integer, default: 0)
- successful_count (integer, default: 0)
- failed_count (integer, default: 0)
- tokens_consumed (integer, default: 0)
- cost_usd (decimal 10,4, default: 0)
- revenue_usd (decimal 10,4, default: 0)
- avg_latency_ms (integer, nullable)
- period_type (enum: daily, weekly, monthly)
- period_start (date)
- period_end (date)
- created_at, updated_at
```

#### 13. System Health
```sql
system_health
- id (bigint, primary)
- metric_name (string) // e.g., "queue_length", "api_latency", "error_rate"
- metric_value (decimal 15,4)
- metric_unit (string, nullable) // e.g., "count", "ms", "percentage"
- provider_id (bigint, nullable, foreign: providers.id)
- recorded_at (timestamp)
- created_at
```

#### 14. Other Supporting Tables
- `likes` (user_id, gallery_post_id)
- `comments` (user_id, gallery_post_id, body, parent_id)
- `follows` (follower_id, following_id)
- `notifications` (user_id, type, data_json, read_at)
- `reports` (user_id, gallery_post_id, reason, status)
- `moderation_queue` (gallery_post_id, reason, status, reviewed_by, reviewed_at)
- `prompt_templates` (name, category, template_json, variables_json)
- `leaderboards` (user_id, metric_type, score, period_type, period_start, rank)

---

## Core Features & Tasks

### Phase 1: Foundation & Authentication

#### Task 1.1: Database Migrations
**Priority**: Critical  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create all migration files for core tables
- [ ] Add indexes for performance (user_id, status, created_at, etc.)
- [ ] Add foreign key constraints
- [ ] Create seeders for initial data (admin user, token bundles, default models)
- [ ] Test migrations on clean database

**Acceptance Criteria**:
- All migrations run successfully
- Foreign keys properly configured
- Indexes added for query optimization
- Seeders populate initial data correctly

---

#### Task 1.2: OTP Authentication with Melipayamak
**Priority**: Critical  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create `OtpVerification` model and migration
- [ ] Implement `MelipayamakService` class
- [ ] Create OTP request endpoint: `POST /api/v1/auth/request-otp`
- [ ] Create OTP verify endpoint: `POST /api/v1/auth/verify-otp`
- [ ] Create OTP resend endpoint: `POST /api/v1/auth/resend-otp`
- [ ] Implement rate limiting (max 3 requests per phone per 10 minutes)
- [ ] Hash OTP codes using HMAC with secret key
- [ ] Set OTP expiration (5 minutes)
- [ ] Implement attempt limiting (max 3 verification attempts)
- [ ] Generate JWT token on successful verification
- [ ] Add audit logging for OTP requests

**API Endpoints**:
```
POST /api/v1/auth/request-otp
Body: { "phone": "+989123456789" }
Response: { "request_id": "uuid", "expires_in": 300 }

POST /api/v1/auth/verify-otp
Body: { "request_id": "uuid", "code": "123456" }
Response: { "token": "jwt_token", "user": {...} }

POST /api/v1/auth/resend-otp
Body: { "request_id": "uuid" }
Response: { "request_id": "new_uuid", "expires_in": 300 }
```

**Acceptance Criteria**:
- OTP codes sent successfully via Melipayamak
- OTP verification works correctly
- Rate limiting prevents abuse
- JWT tokens issued on successful verification
- Failed attempts are logged and limited

---

#### Task 1.3: User Profile Management
**Priority**: High  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create user profile endpoint: `GET /api/v1/user`
- [ ] Create update profile endpoint: `PUT /api/v1/user`
- [ ] Implement avatar upload functionality
- [ ] Add email verification (optional, for future use)
- [ ] Implement token balance retrieval

**API Endpoints**:
```
GET /api/v1/user
Headers: Authorization: Bearer {token}
Response: { "id": 1, "phone": "...", "name": "...", "tokens_balance": 1000, ... }

PUT /api/v1/user
Body: { "name": "...", "email": "..." }
Response: { "user": {...} }
```

**Acceptance Criteria**:
- User can view their profile
- User can update profile information
- Avatar uploads work correctly
- Token balance is accurate

---

### Phase 2: Token System & Payment Integration

#### Task 2.1: Token Bundle Management
**Priority**: Critical  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create `TokenBundle` model and migration
- [ ] Create admin CRUD for token bundles
- [ ] Create API endpoint: `GET /api/v1/tokens/bundles`
- [ ] Implement bundle display with real-time pricing
- [ ] Add bonus token support

**API Endpoints**:
```
GET /api/v1/tokens/bundles
Response: [
  {
    "id": 1,
    "name": "Starter Pack",
    "token_amount": 100,
    "price_toman": 50000,
    "price_usd": 1.00,
    "bonus_tokens": 0
  },
  ...
]
```

**Acceptance Criteria**:
- Token bundles displayed correctly
- Prices calculated in real-time
- Admin can manage bundles

---

#### Task 2.2: USD to Toman Price Scraper
**Priority**: Critical  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create `CurrencyRate` model and migration
- [ ] Implement web scraper for TGJU.org
- [ ] Extract USD to Rials rate from TGJU.org
- [ ] Convert Rials to Toman (divide by 10)
- [ ] Create scheduled job to fetch rate every 5 minutes
- [ ] Implement fallback mechanism if scraping fails
- [ ] Cache rate in Redis (5-minute TTL)
- [ ] Create API endpoint: `GET /api/v1/currency/rate`
- [ ] Add error handling and logging

**Implementation Notes**:
- Use Laravel HTTP client or Guzzle for scraping
- Parse HTML to find USD price (look for specific CSS classes/IDs)
- Store rate in `currency_rates` table
- Use Redis cache to avoid excessive scraping
- Handle rate limit and blocking scenarios

**API Endpoints**:
```
GET /api/v1/currency/rate
Response: {
  "usd_to_toman": 50000.00,
  "usd_to_rials": 500000.00,
  "fetched_at": "2024-01-01T12:00:00Z",
  "source": "tgju"
}
```

**Acceptance Criteria**:
- USD rate fetched successfully from TGJU.org
- Rate converted correctly to Toman
- Scheduled job runs every 5 minutes
- Fallback mechanism works if scraping fails
- Rate cached in Redis

---

#### Task 2.3: Zarinpal Payment Integration
**Priority**: Critical  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Install Zarinpal PHP SDK or create custom integration
- [ ] Create `Order` model and migration
- [ ] Implement payment request: `POST /api/v1/tokens/purchase`
- [ ] Implement payment verification callback
- [ ] Create payment success/failure handlers
- [ ] Implement token credit on successful payment
- [ ] Create transaction logging
- [ ] Add payment retry mechanism
- [ ] Implement refund functionality (if needed)

**API Endpoints**:
```
POST /api/v1/tokens/purchase
Body: { "bundle_id": 1 }
Response: {
  "order_id": 123,
  "payment_url": "https://zarinpal.com/pg/StartPay/{authority}",
  "authority": "A00000000000000000000000000000000000"
}

GET /api/v1/tokens/purchase/callback
Query: ?Authority={authority}&Status=OK
Response: Redirect to success/failure page
```

**Payment Flow**:
1. User selects token bundle
2. Backend calculates price in Toman using current USD rate
3. Backend creates order with `pending` status
4. Backend requests payment from Zarinpal
5. User redirected to Zarinpal payment page
6. After payment, Zarinpal redirects to callback URL
7. Backend verifies payment with Zarinpal
8. If successful: update order status, credit tokens, create transaction log
9. If failed: update order status, log error

**Acceptance Criteria**:
- Payment requests created successfully
- Zarinpal integration works correctly
- Tokens credited on successful payment
- Transaction logs created
- Payment verification secure

---

#### Task 2.4: Token Transaction System
**Priority**: Critical  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create `TokenTransaction` model and migration
- [ ] Implement token purchase transaction logging
- [ ] Implement token consumption transaction logging
- [ ] Implement token refund transaction logging
- [ ] Create API endpoint: `GET /api/v1/tokens/history`
- [ ] Add transaction filtering and pagination
- [ ] Implement token balance calculation from transactions

**API Endpoints**:
```
GET /api/v1/tokens/history
Query: ?type=purchase&page=1&per_page=20
Response: {
  "data": [
    {
      "id": 1,
      "type": "purchase",
      "amount_tokens": 100,
      "description": "Token Bundle: Starter Pack",
      "created_at": "2024-01-01T12:00:00Z"
    },
    ...
  ],
  "meta": { "total": 50, "page": 1, "per_page": 20 }
}
```

**Acceptance Criteria**:
- All token transactions logged
- Transaction history retrievable
- Balance calculated correctly
- Pagination works

---

### Phase 3: Segmind API Integration

#### Task 3.1: Segmind Provider Setup
**Priority**: Critical  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Research Segmind API documentation
- [ ] Create `Provider` model entry for Segmind
- [ ] Implement API key encryption/decryption
- [ ] Create base Segmind service class
- [ ] Implement API authentication
- [ ] Add error handling and retry logic
- [ ] Implement rate limiting awareness

**Segmind API Base**:
- Base URL: `https://api.segmind.com`
- Authentication: API key in headers
- Rate limits: Check documentation

**Acceptance Criteria**:
- Segmind provider configured
- API keys encrypted in database
- Base service class functional

---

#### Task 3.2: Image Generation Models Integration
**Priority**: Critical  
**Estimated Time**: 5 days

**Subtasks**:
- [ ] Research Segmind image generation models
- [ ] Create model entries in database for each image model
- [ ] Implement image generation service: `SegmindImageService`
- [ ] Map Segmind API parameters to internal format
- [ ] Implement async job processing
- [ ] Handle image download and storage
- [ ] Create thumbnail generation
- [ ] Implement token consumption per model
- [ ] Add error handling and retry logic

**Segmind Image Models** (Examples - verify with actual API):
- Flux models
- Stable Diffusion models
- Other image generation models

**API Endpoints**:
```
POST /api/v1/generate/image
Body: {
  "model_id": 1,
  "prompt": "A beautiful landscape",
  "negative_prompt": "blurry, low quality",
  "size": "1024x1024",
  "num_images": 1,
  "guidance_scale": 7.5,
  "seed": null
}
Response: {
  "job_id": 123,
  "status": "pending",
  "estimated_tokens": 10
}

GET /api/v1/generate/image/{job_id}/status
Response: {
  "job_id": 123,
  "status": "processing",
  "progress": 50
}

GET /api/v1/generate/image/{job_id}/result
Response: {
  "job_id": 123,
  "status": "completed",
  "result_url": "https://...",
  "thumbnail_url": "https://..."
}
```

**Acceptance Criteria**:
- Image generation works for all Segmind image models
- Images stored correctly
- Thumbnails generated
- Tokens consumed correctly
- Error handling robust

---

#### Task 3.3: Video Generation Models Integration
**Priority**: High  
**Estimated Time**: 5 days

**Subtasks**:
- [ ] Research Segmind video generation models
- [ ] Create model entries for video models
- [ ] Implement video generation service: `SegmindVideoService`
- [ ] Map video-specific parameters
- [ ] Implement async job processing for videos
- [ ] Handle video download and storage
- [ ] Create video thumbnail/preview generation
- [ ] Implement token consumption per video model
- [ ] Add progress tracking for long-running jobs

**API Endpoints**:
```
POST /api/v1/generate/video
Body: {
  "model_id": 10,
  "prompt": "A cat walking",
  "duration": 5,
  "fps": 24,
  "resolution": "720p"
}
Response: {
  "job_id": 124,
  "status": "pending",
  "estimated_tokens": 50
}

GET /api/v1/generate/video/{job_id}/status
Response: {
  "job_id": 124,
  "status": "processing",
  "progress": 30,
  "estimated_completion": "2024-01-01T12:05:00Z"
}
```

**Acceptance Criteria**:
- Video generation works for Segmind video models
- Videos stored correctly
- Progress tracking functional
- Tokens consumed correctly

---

#### Task 3.4: Audio Generation Models Integration
**Priority**: High  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Research Segmind audio generation models
- [ ] Create model entries for audio models
- [ ] Implement audio generation service: `SegmindAudioService`
- [ ] Map audio-specific parameters (duration, format, etc.)
- [ ] Implement async job processing for audio
- [ ] Handle audio file download and storage
- [ ] Implement token consumption per audio model
- [ ] Add audio preview/playback support

**API Endpoints**:
```
POST /api/v1/generate/audio
Body: {
  "model_id": 20,
  "prompt": "Generate a relaxing piano melody",
  "duration": 30,
  "format": "mp3"
}
Response: {
  "job_id": 125,
  "status": "pending",
  "estimated_tokens": 15
}
```

**Acceptance Criteria**:
- Audio generation works for Segmind audio models
- Audio files stored correctly
- Tokens consumed correctly
- Preview functionality works

---

#### Task 3.5: Unified Generation Job System
**Priority**: Critical  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create unified `GenerationJob` model (replaces separate image_jobs, video_jobs, audio_jobs)
- [ ] Implement job status tracking
- [ ] Create job queue workers
- [ ] Implement job retry mechanism
- [ ] Add job timeout handling
- [ ] Implement token reservation system
- [ ] Create job cancellation endpoint
- [ ] Add job history and filtering

**Token Reservation Flow**:
1. User requests generation
2. System reserves tokens (deduct from balance temporarily)
3. Job created and queued
4. On completion: finalize token consumption
5. On failure: refund reserved tokens

**API Endpoints**:
```
POST /api/v1/generate/{type} // type: image, video, audio
GET /api/v1/generate/jobs
GET /api/v1/generate/jobs/{job_id}
POST /api/v1/generate/jobs/{job_id}/cancel
POST /api/v1/generate/jobs/{job_id}/retry
```

**Acceptance Criteria**:
- Jobs tracked correctly
- Token reservation works
- Retry mechanism functional
- Job cancellation works

---

### Phase 4: Gallery & Feed System

#### Task 4.1: Gallery Post Management
**Priority**: High  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create `GalleryPost` model and migration
- [ ] Implement post creation: `POST /api/v1/gallery/post`
- [ ] Implement post update and deletion
- [ ] Add tag system (JSON array)
- [ ] Implement visibility settings (public/private)
- [ ] Add prompt and model visibility flags
- [ ] Create post detail endpoint: `GET /api/v1/gallery/posts/{id}`
- [ ] Implement user's own posts listing

**API Endpoints**:
```
POST /api/v1/gallery/post
Body: {
  "generation_job_id": 123,
  "title": "My Artwork",
  "description": "A beautiful landscape",
  "tags": ["landscape", "nature"],
  "visibility": "public",
  "prompt_visible": true,
  "model_visible": true
}
Response: { "post": {...} }

GET /api/v1/gallery/posts/{id}
Response: {
  "id": 1,
  "title": "...",
  "prompt": "...", // if prompt_visible
  "model_name": "...", // if model_visible
  "result_url": "...",
  "user": {...}
}
```

**Acceptance Criteria**:
- Posts created successfully
- Tags work correctly
- Visibility settings enforced
- Prompt/model visibility controlled

---

#### Task 4.2: Admin Curated Feed System
**Priority**: High  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Add `is_curated` and `is_featured` fields to gallery_posts
- [ ] Create admin endpoint: `POST /api/v1/admin/gallery/{post_id}/curate`
- [ ] Create admin endpoint: `POST /api/v1/admin/gallery/{post_id}/uncurate`
- [ ] Implement feed filtering (only curated items)
- [ ] Add curation timestamp
- [ ] Create admin UI for feed management
- [ ] Implement bulk curation actions

**API Endpoints**:
```
POST /api/v1/admin/gallery/{post_id}/curate
Response: { "success": true, "post": {...} }

POST /api/v1/admin/gallery/{post_id}/uncurate
Response: { "success": true }

GET /api/v1/admin/gallery/curated
Query: ?type=image&page=1
Response: { "data": [...], "meta": {...} }
```

**Acceptance Criteria**:
- Admins can curate posts
- Feed shows only curated items
- Curation timestamp tracked
- Bulk actions work

---

#### Task 4.3: Public Feed with View Limits
**Priority**: High  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Create `FeedViewLimit` model and migration
- [ ] Implement feed endpoint: `GET /api/v1/gallery/feed`
- [ ] Filter feed to show only curated images and videos (no audio)
- [ ] Implement view limit tracking per user
- [ ] Add daily limit reset mechanism
- [ ] Create limit configuration system
- [ ] Implement "Copy Prompt" and "Copy Model" functionality
- [ ] Add feed pagination (cursor-based)
- [ ] Cache feed in Redis

**View Limit Logic**:
- Each user has a daily limit (e.g., 10 views)
- Limit resets at midnight (configurable)
- Only images and videos count toward limit
- Audio not shown in feed
- Admins have unlimited views

**API Endpoints**:
```
GET /api/v1/gallery/feed
Query: ?type=image&cursor=123&limit=20
Response: {
  "data": [
    {
      "id": 1,
      "type": "image",
      "result_url": "...",
      "thumbnail_url": "...",
      "prompt": "...", // if prompt_visible
      "model_name": "...", // if model_visible
      "user": {...}
    },
    ...
  ],
  "meta": {
    "cursor": 456,
    "has_more": true,
    "views_remaining": 5
  }
}

POST /api/v1/gallery/feed/copy-prompt
Body: { "post_id": 1 }
Response: { "prompt": "..." }

POST /api/v1/gallery/feed/copy-model
Body: { "post_id": 1 }
Response: { "model_id": 1, "model_name": "..." }
```

**Acceptance Criteria**:
- Feed shows only curated images/videos
- View limits enforced
- Limits reset daily
- Prompt/model copying works
- Pagination functional

---

#### Task 4.4: Social Features (Likes, Comments)
**Priority**: Medium  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Implement like functionality: `POST /api/v1/gallery/{post_id}/like`
- [ ] Implement unlike functionality: `DELETE /api/v1/gallery/{post_id}/like`
- [ ] Implement comment creation: `POST /api/v1/gallery/{post_id}/comment`
- [ ] Implement comment replies (nested comments)
- [ ] Implement comment deletion
- [ ] Add like/comment counts to posts
- [ ] Create notifications for likes/comments

**API Endpoints**:
```
POST /api/v1/gallery/{post_id}/like
Response: { "liked": true, "likes_count": 10 }

DELETE /api/v1/gallery/{post_id}/like
Response: { "liked": false, "likes_count": 9 }

POST /api/v1/gallery/{post_id}/comment
Body: { "body": "Great work!", "parent_id": null }
Response: { "comment": {...} }
```

**Acceptance Criteria**:
- Likes work correctly
- Comments work correctly
- Nested comments supported
- Counts updated correctly

---

### Phase 5: Admin Dashboard

#### Task 5.1: Admin Authentication & Authorization
**Priority**: Critical  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create admin middleware
- [ ] Implement role-based access control (RBAC)
- [ ] Create admin login endpoint (separate from user auth)
- [ ] Add 2FA support for admin (optional)
- [ ] Implement admin session management

**Acceptance Criteria**:
- Admin routes protected
- RBAC works correctly
- Admin authentication secure

---

#### Task 5.2: Sales Dashboard
**Priority**: High  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Create sales summary endpoint: `GET /api/v1/admin/sales/summary`
- [ ] Implement revenue by day/week/month
- [ ] Calculate top selling token bundles
- [ ] Track refunds and chargebacks
- [ ] Calculate customer lifetime value (LTV)
- [ ] Create Vue.js dashboard component
- [ ] Add charts (revenue over time, bundle sales)
- [ ] Implement CSV export functionality

**API Endpoints**:
```
GET /api/v1/admin/sales/summary
Query: ?range=month&start_date=2024-01-01&end_date=2024-01-31
Response: {
  "total_revenue_toman": 10000000,
  "total_revenue_usd": 200,
  "total_orders": 150,
  "revenue_by_day": [...],
  "top_bundles": [
    { "bundle_id": 1, "sales_count": 50, "revenue": 5000000 }
  ],
  "refunds": { "count": 2, "amount": 100000 }
}
```

**Acceptance Criteria**:
- Sales data accurate
- Charts display correctly
- CSV export works
- Date filtering functional

---

#### Task 5.3: Users Dashboard
**Priority**: High  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Create users summary endpoint: `GET /api/v1/admin/users/summary`
- [ ] Calculate DAU/WAU/MAU metrics
- [ ] Implement top users by generation count
- [ ] Implement top users by spending
- [ ] Create cohort analysis
- [ ] Track churn and reactivation
- [ ] Create Vue.js dashboard component
- [ ] Add user search and filtering

**API Endpoints**:
```
GET /api/v1/admin/users/summary
Query: ?range=month
Response: {
  "total_users": 1000,
  "active_users": {
    "dau": 100,
    "wau": 300,
    "mau": 800
  },
  "top_generators": [...],
  "top_spenders": [...],
  "cohort_analysis": [...]
}

GET /api/v1/admin/users
Query: ?search=john&role=user&page=1
Response: { "data": [...], "meta": {...} }
```

**Acceptance Criteria**:
- User metrics accurate
- Cohort analysis functional
- Search and filtering work
- Dashboard displays correctly

---

#### Task 5.4: Models Usage Dashboard
**Priority**: High  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Create models usage endpoint: `GET /api/v1/admin/models/usage`
- [ ] Track requests per model (image/video/audio)
- [ ] Calculate average cost per model
- [ ] Track tokens consumed per model
- [ ] Calculate average latency per model
- [ ] Track failure rates per model
- [ ] Create Vue.js dashboard component
- [ ] Add model comparison charts
- [ ] Implement date range filtering

**API Endpoints**:
```
GET /api/v1/admin/models/usage
Query: ?range=month&model_id=1&type=image
Response: {
  "model_id": 1,
  "model_name": "flux-dev",
  "job_type": "image",
  "requests_count": 1000,
  "successful_count": 950,
  "failed_count": 50,
  "tokens_consumed": 10000,
  "cost_usd": 50.00,
  "revenue_usd": 100.00,
  "avg_latency_ms": 2500,
  "success_rate": 0.95
}
```

**Acceptance Criteria**:
- Usage data accurate
- Charts display correctly
- Filtering works
- Model comparison functional

---

#### Task 5.5: Token Analytics Dashboard
**Priority**: High  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create token summary endpoint: `GET /api/v1/admin/tokens/summary`
- [ ] Track tokens consumed per provider
- [ ] Track tokens consumed by feature (image/video/audio)
- [ ] Calculate cost and profit per provider
- [ ] Create time-series charts
- [ ] Implement date range filtering

**API Endpoints**:
```
GET /api/v1/admin/tokens/summary
Query: ?range=month&provider_id=1
Response: {
  "provider_id": 1,
  "tokens_consumed": 50000,
  "cost_usd": 250.00,
  "revenue_usd": 500.00,
  "profit_usd": 250.00,
  "by_type": {
    "image": 30000,
    "video": 15000,
    "audio": 5000
  }
}
```

**Acceptance Criteria**:
- Token analytics accurate
- Charts display correctly
- Provider breakdown functional

---

#### Task 5.6: Cost and Profit Dashboard
**Priority**: High  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Create cost-profit summary endpoint: `GET /api/v1/admin/cost-profit/summary`
- [ ] Calculate COGS per model
- [ ] Calculate profit per model
- [ ] Track profit margins over time
- [ ] Break down by bundle, campaign, etc.
- [ ] Create Vue.js dashboard component
- [ ] Add profit margin charts
- [ ] Implement historical comparison

**API Endpoints**:
```
GET /api/v1/admin/cost-profit/summary
Query: ?range=month
Response: {
  "total_revenue_usd": 1000.00,
  "total_cost_usd": 500.00,
  "total_profit_usd": 500.00,
  "profit_margin": 0.50,
  "by_model": [...],
  "by_provider": [...],
  "historical": [...]
}
```

**Acceptance Criteria**:
- Cost/profit calculations accurate
- Charts display correctly
- Historical data available

---

#### Task 5.7: System Health Dashboard
**Priority**: Medium  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create system health endpoint: `GET /api/v1/admin/system-health`
- [ ] Track queue lengths
- [ ] Monitor worker status
- [ ] Track failed jobs
- [ ] Calculate error rates
- [ ] Monitor API latency to Segmind
- [ ] Track storage usage
- [ ] Monitor bandwidth usage
- [ ] Create Vue.js dashboard component
- [ ] Add alerting thresholds

**API Endpoints**:
```
GET /api/v1/admin/system-health
Response: {
  "queue_length": 10,
  "workers_active": 5,
  "failed_jobs_24h": 2,
  "error_rate": 0.01,
  "api_latency_ms": 1500,
  "storage_used_gb": 100,
  "bandwidth_24h_gb": 50
}
```

**Acceptance Criteria**:
- System metrics accurate
- Dashboard displays correctly
- Alerting works

---

#### Task 5.8: Feed Management Dashboard
**Priority**: Medium  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create feed management interface
- [ ] Implement post selection for curation
- [ ] Add bulk curation actions
- [ ] Implement featured post management
- [ ] Add feed preview
- [ ] Create Vue.js admin component

**Acceptance Criteria**:
- Admins can manage feed easily
- Bulk actions work
- Feed preview functional

---

### Phase 6: Additional Features

#### Task 6.1: Prompt Templates System
**Priority**: Medium  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create `PromptTemplate` model and migration
- [ ] Implement template categories
- [ ] Create template variables system
- [ ] Build prompt builder UI
- [ ] Add template preview
- [ ] Implement template saving

**Acceptance Criteria**:
- Templates work correctly
- Variables replaced properly
- UI functional

---

#### Task 6.2: Leaderboard System
**Priority**: Low  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create leaderboard calculation job
- [ ] Implement daily/weekly/monthly leaderboards
- [ ] Track metrics: generations, likes, earnings
- [ ] Create leaderboard API endpoints
- [ ] Add badges and achievements
- [ ] Create Vue.js leaderboard component

**Acceptance Criteria**:
- Leaderboards calculated correctly
- Rankings accurate
- Badges awarded properly

---

#### Task 6.3: Notification System
**Priority**: Medium  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create notification model
- [ ] Implement in-app notifications
- [ ] Add notification types (like, comment, generation complete)
- [ ] Create notification API endpoints
- [ ] Implement notification read/unread status
- [ ] Add notification preferences

**Acceptance Criteria**:
- Notifications created correctly
- Read/unread status works
- Preferences functional

---

#### Task 6.4: Content Moderation
**Priority**: Medium  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create moderation queue
- [ ] Implement automated content filtering
- [ ] Create admin moderation interface
- [ ] Add reporting system
- [ ] Implement content removal
- [ ] Add user blocking

**Acceptance Criteria**:
- Moderation queue functional
- Automated filtering works
- Admin interface usable

---

## API Endpoints Summary

### Authentication
- `POST /api/v1/auth/request-otp` - Request OTP
- `POST /api/v1/auth/verify-otp` - Verify OTP and get token
- `POST /api/v1/auth/resend-otp` - Resend OTP
- `POST /api/v1/auth/logout` - Logout

### User
- `GET /api/v1/user` - Get user profile
- `PUT /api/v1/user` - Update user profile
- `POST /api/v1/user/avatar` - Upload avatar

### Tokens & Payment
- `GET /api/v1/tokens/bundles` - List token bundles
- `GET /api/v1/tokens/balance` - Get token balance
- `GET /api/v1/tokens/history` - Get transaction history
- `POST /api/v1/tokens/purchase` - Purchase tokens
- `GET /api/v1/tokens/purchase/callback` - Zarinpal callback
- `GET /api/v1/currency/rate` - Get USD to Toman rate

### Generation
- `POST /api/v1/generate/image` - Generate image
- `POST /api/v1/generate/video` - Generate video
- `POST /api/v1/generate/audio` - Generate audio
- `GET /api/v1/generate/jobs` - List user's jobs
- `GET /api/v1/generate/jobs/{id}` - Get job details
- `POST /api/v1/generate/jobs/{id}/cancel` - Cancel job
- `POST /api/v1/generate/jobs/{id}/retry` - Retry job

### Gallery & Feed
- `POST /api/v1/gallery/post` - Create gallery post
- `GET /api/v1/gallery/posts/{id}` - Get post details
- `PUT /api/v1/gallery/posts/{id}` - Update post
- `DELETE /api/v1/gallery/posts/{id}` - Delete post
- `GET /api/v1/gallery/feed` - Get curated feed
- `POST /api/v1/gallery/feed/copy-prompt` - Copy prompt from post
- `POST /api/v1/gallery/feed/copy-model` - Copy model from post
- `POST /api/v1/gallery/{id}/like` - Like post
- `DELETE /api/v1/gallery/{id}/like` - Unlike post
- `POST /api/v1/gallery/{id}/comment` - Add comment
- `DELETE /api/v1/gallery/comments/{id}` - Delete comment

### Admin
- `GET /api/v1/admin/sales/summary` - Sales dashboard
- `GET /api/v1/admin/users/summary` - Users dashboard
- `GET /api/v1/admin/models/usage` - Models usage dashboard
- `GET /api/v1/admin/tokens/summary` - Token analytics
- `GET /api/v1/admin/cost-profit/summary` - Cost/profit dashboard
- `GET /api/v1/admin/system-health` - System health
- `POST /api/v1/admin/gallery/{id}/curate` - Curate post
- `POST /api/v1/admin/gallery/{id}/uncurate` - Uncurate post
- `GET /api/v1/admin/gallery/curated` - List curated posts

---

## Implementation Priority

### Phase 1: Foundation (Week 1-2)
1. Database migrations
2. OTP authentication
3. User profile management

### Phase 2: Token & Payment (Week 3-4)
1. Token bundle system
2. USD to Toman scraper
3. Zarinpal integration
4. Token transaction system

### Phase 3: Generation (Week 5-8)
1. Segmind provider setup
2. Image generation
3. Video generation
4. Audio generation
5. Unified job system

### Phase 4: Gallery & Feed (Week 9-10)
1. Gallery posts
2. Admin curation
3. Public feed with limits
4. Social features

### Phase 5: Admin Dashboard (Week 11-14)
1. Admin auth
2. Sales dashboard
3. Users dashboard
4. Models usage dashboard
5. Token analytics
6. Cost/profit dashboard
7. System health
8. Feed management

### Phase 6: Additional Features (Week 15+)
1. Prompt templates
2. Leaderboards
3. Notifications
4. Content moderation

---

## Technical Notes

### Token System Details

**Token Packages**:
- Starter Pack: 100 tokens
- Standard Pack: 500 tokens
- Premium Pack: 1000 tokens
- Ultimate Pack: 2000 tokens

**Token Consumption**:
- Tokens are consumed per generation job
- Consumption amount varies by model and quality
- Tokens are reserved when job is created
- Reserved tokens are finalized on job completion
- Reserved tokens are refunded on job failure

**Token Pricing**:
- Base price in USD per bundle
- Real-time conversion to Toman using TGJU.org rate
- Price calculated at purchase time
- Price stored in order for audit trail

### Segmind API Integration

**Authentication**:
- API key stored encrypted in database
- Key passed in request headers
- Rate limiting handled per Segmind documentation

**Job Processing**:
- All generation jobs processed asynchronously
- Jobs queued in Redis
- Workers process jobs from queue
- Status updates via polling or webhooks (if supported)

**Error Handling**:
- Retry failed requests (max 3 attempts)
- Exponential backoff for retries
- Log all errors for debugging
- Notify user on permanent failure

### Feed System Details

**Curation**:
- Only admin-curated posts appear in feed
- Admins select posts via dashboard
- Curation timestamp tracked
- Featured posts can be prioritized

**View Limits**:
- Users have daily view limits (configurable)
- Limits apply to images and videos only
- Audio not shown in feed
- Limits reset at midnight (configurable timezone)
- Admins have unlimited views

**Prompt/Model Copying**:
- Users can copy prompt text from feed items
- Users can copy model information
- Copied data can be used to generate similar content
- Copying tracked for analytics (optional)

### Currency Scraping

**TGJU.org Scraping**:
- Scrape USD to Rials rate
- Convert Rials to Toman (divide by 10)
- Cache rate in Redis (5-minute TTL)
- Scheduled job runs every 5 minutes
- Fallback to last known rate if scraping fails
- Log all scraping attempts and failures

**Rate Storage**:
- Store rates in `currency_rates` table
- Keep historical rates for analysis
- Use latest rate for price calculations
- Display rate source and timestamp

### Security Considerations

**API Keys**:
- Encrypt provider API keys in database
- Use Laravel's encryption or KMS
- Never log API keys
- Rotate keys periodically

**Payment Security**:
- Verify Zarinpal callbacks
- Never trust client-side payment data
- Log all payment transactions
- Implement idempotency for payments

**Rate Limiting**:
- Limit OTP requests per phone
- Limit generation requests per user
- Limit API calls per IP
- Use Laravel rate limiting middleware

---

## Testing Strategy

### Unit Tests
- Token calculation logic
- Currency conversion
- OTP generation and verification
- Token transaction processing

### Integration Tests
- Payment flow (mock Zarinpal)
- Generation job processing (mock Segmind)
- Feed view limit enforcement
- Admin curation workflow

### End-to-End Tests
- User registration → Token purchase → Generation → Gallery post
- Admin curation → Feed display → User viewing
- Payment → Token credit → Generation

---

## Deployment Checklist

- [ ] Database migrations run
- [ ] Environment variables configured
- [ ] Redis configured and running
- [ ] Queue workers running
- [ ] S3 storage configured
- [ ] Zarinpal credentials set
- [ ] Segmind API keys configured
- [ ] Melipayamak credentials set
- [ ] Scheduled jobs configured
- [ ] Monitoring and logging set up
- [ ] Backup strategy implemented

---

## Maintenance & Operations

### Daily Tasks
- Monitor queue lengths
- Check failed jobs
- Review error logs
- Monitor API rate limits

### Weekly Tasks
- Review sales and revenue
- Analyze model usage
- Check system health metrics
- Review user feedback

### Monthly Tasks
- Reconcile provider invoices
- Calculate profit margins
- Review and update token pricing
- Analyze user retention

---

## Future Enhancements

- User tiers/subscription plans
- Advanced prompt builder with AI suggestions
- Image editing and remix features
- Social sharing and embedding
- API for third-party integrations
- Mobile app (Flutter) - future phase
- Multi-language support
- Advanced analytics and insights

---

**Document Version**: 1.0  
**Last Updated**: 2024-01-01  
**Status**: Active Development

