# Negarify Platform - Comprehensive Project Summary

## 1. Project Purpose

### Problem Solved
Negarify is an AI content generation SaaS platform that simplifies access to advanced AI models (images, videos, audio) through a unified interface. The platform bridges the gap between complex AI technology and everyday users by providing a simple, token-based payment model.

### Target Users
- **Content Creators**: Bloggers, social media managers, marketers needing visual content
- **Artists & Designers**: Digital artists and creative professionals
- **Businesses**: Small to medium businesses requiring visual content
- **Hobbyists**: Individuals interested in AI and content creation
- **Developers**: Those needing AI-generated content for applications

**Primary Market**: Iranian market (supporting Toman currency and Zarinpal payments) while maintaining international accessibility.

### Business Model
- **Primary Revenue**: Token sales - users purchase prepaid token packages (100, 500, 1000, 2000 tokens)
- **Pricing Strategy**: Base pricing in USD, converted to Toman using real-time exchange rates from TGJU.org
- **Profit Model**: Margin between token sales revenue and Segmind API costs
- **Pricing Updates**: Dynamic pricing that automatically adjusts with USD to Toman exchange rate changes
- **Volume Discounts**: Larger packages offer better value per token

---

## 2. High-Level Architecture

### Frontend Stack
- **Framework**: Vue.js 3.4+ with Composition API
- **Build Tool**: Vite 5.0+
- **State Management**: Pinia 2.x
- **Routing**: Vue Router 4.x
- **UI Framework**: Element Plus (recommended) or Vuetify/Tailwind
- **HTTP Client**: Axios with interceptors for auth and error handling

### Backend Stack
- **Framework**: Laravel 10.x (API-first architecture)
- **Language**: PHP 8.2+
- **Database**: MySQL 8.0+ / PostgreSQL 14+
- **Cache & Queue**: Redis 7.0+ (used for both caching and job queue)
- **Storage**: S3-compatible object storage (AWS S3, DigitalOcean Spaces)
- **Authentication**: Laravel Sanctum (JWT token-based, stateless)

### External Services & Integrations
- **AI Provider**: Segmind API (https://api.segmind.com) - aggregates multiple AI models for image/video/audio generation
- **Payment Gateway**: Zarinpal - Iranian payment gateway handling token purchases
- **SMS Service**: Melipayamak - OTP delivery for phone-based authentication
- **Currency Data**: TGJU.org - web scraping for USD to Toman exchange rates (fetched every 5 minutes)

### Overall System Flow

**User Registration/Authentication Flow:**
1. User enters phone number → OTP sent via Melipayamak SMS
2. User submits OTP code → System verifies → JWT token issued → User account created/authenticated

**Token Purchase Flow:**
1. User selects token bundle → System fetches current USD rate → Price calculated in Toman
2. Order created → Payment requested from Zarinpal → User redirected to payment gateway
3. Payment completed → Zarinpal callback → Payment verified server-side → Tokens credited → Transaction logged

**Content Generation Flow:**
1. User submits generation request (image/video/audio) → System validates and checks token balance
2. Tokens reserved (deducted from balance) → Generation job created with 'pending' status → Job queued in Redis
3. Queue worker picks up job → Calls Segmind API → Downloads generated content → Stores in S3
4. Thumbnail generated (for images/videos) → Job status updated to 'completed' → Tokens consumed (transaction log created)
5. User notified → Result URL available via API

**Gallery/Feed Flow:**
1. User publishes generated content to gallery → Admin curates high-quality posts
2. Curated posts appear in public feed → Users browse with daily view limits
3. Users can copy prompts/models from feed → Use to generate similar content

---

## 3. Core Domain Concepts

### Token System
**Token Lifecycle:**
- **Purchase**: Users buy token bundles → Tokens credited to balance → Transaction logged (type='purchase')
- **Reservation**: When generation job created → Tokens temporarily deducted from balance (not yet consumed) → No transaction log yet
- **Consumption**: When generation job completes successfully → Transaction log created (type='consume', negative amount) → Reservation finalized
- **Refund**: When generation job fails/cancelled → Reserved tokens restored to balance → No transaction log (was never consumed)

**Financial Consistency Rules:**
- User balance must equal sum of all token transactions (audit requirement)
- Balance never directly mutated - only changed through transaction records (append-only ledger)
- Token reservation = SUM(tokens_consumed WHERE status IN ('pending', 'processing'))
- All token movements logged for full audit trail

### Generation Jobs
**Unified Job System:**
- Single `generation_jobs` table handles images, videos, and audio (job_type enum)
- Job states: pending → processing → completed/failed/cancelled (linear state machine)
- Asynchronous processing via Redis queue workers
- Token cost varies by model, quality, and content type (stored in generation_jobs.tokens_consumed)
- Actual API cost tracked in generation_jobs.cost_usd for profit analysis

**Job Types:**
- **Image jobs**: 5-30 seconds, requires thumbnail generation
- **Video jobs**: 1-5 minutes, requires thumbnail/preview frame extraction
- **Audio jobs**: 10-60 seconds, may require metadata extraction

### Gallery, Feed, and Curation Logic
**Gallery System:**
- Users publish generated content to personal gallery
- Posts include title, description, tags, visibility (public/private)
- Users control prompt/model visibility (can hide from public view)
- Social features: likes, comments (with nested replies)

**Curated Feed:**
- Only admin-curated posts appear in public feed (is_curated flag)
- Feed shows images and videos only (no audio)
- View limits: Users have daily view limits (default 10, configurable)
- Limits reset daily at midnight
- Admins have unlimited views
- Prompt/model copying: Users can copy prompts and model info from feed items

### Authentication and User Roles
**OTP-Based Authentication:**
- Phone number + OTP replaces traditional passwords
- OTP sent via Melipayamak SMS service
- OTP expires after 5 minutes, max 3 verification attempts
- Rate limiting: Max 3 OTP requests per phone per 15 minutes
- Request ID (UUID) prevents phone enumeration attacks
- User account auto-created on first successful verification

**User Roles:**
- **user**: Regular users (default)
- **admin**: Full access to admin dashboards and moderation
- **moderator**: Limited admin access (for content moderation)

---

## 4. Key Backend Responsibilities

### Core Responsibilities
1. **RESTful API**: Provides endpoints for frontend and future mobile clients
2. **Token Economy Management**: Handles token purchase, reservation, consumption, and refund lifecycle
3. **Payment Processing**: Integrates with Zarinpal for secure token purchases
4. **Currency Conversion**: Real-time USD to Toman rate fetching and price calculation
5. **AI Integration**: Integrates with Segmind API for content generation (image/video/audio)
6. **Admin Dashboard APIs**: Provides analytics and management endpoints
7. **Queue Management**: Handles asynchronous job processing with Redis queues
8. **File Storage**: Manages S3 storage for generated content and thumbnails

### Critical Services and Systems

**Financial Services:**
- `ZarinpalService`: Payment gateway integration with idempotent callbacks
- `TgjuScraperService`: Currency rate scraping with fallback mechanisms
- `CurrencyRateService`: Rate caching and retrieval
- Token transaction logging with full audit trail

**AI Services:**
- `BaseSegmindService`: Base class for Segmind API integration
- `SegmindImageService`: Image generation with parameter mapping
- `SegmindVideoService`: Video generation with progress tracking
- `SegmindAudioService`: Audio generation with metadata extraction

**Authentication Services:**
- `MelipayamakService`: SMS/OTP delivery with retry logic
- OTP generation and verification with cryptographic security
- Rate limiting and abuse prevention

**Content Services:**
- `ModerationService`: Content moderation and reporting
- `NotificationService`: User notifications for events (likes, comments, generation complete)
- Feed view limit management

### Financially Sensitive Flows

**Payment Flow (Must Be Correct):**
- Order creation → Payment request → Callback verification → Token credit (all atomic operations)
- Idempotent callbacks prevent double-crediting
- Price calculation server-side only (never trust client prices)
- Exchange rate snapshot stored in order for audit trail

**Token Operations (Must Be Correct):**
- Reservation: Atomic balance deduction + job creation
- Consumption: Atomic transaction log creation + status update
- Refund: Atomic balance restoration on failure/cancellation
- Balance reconciliation: Balance = SUM(transactions) - SUM(reserved tokens)

---

## 5. Data Model Overview

### Main Database Entities

**Core Entities:**
- **users**: User accounts with phone-based auth, token balance, role
- **providers**: AI provider configuration (Segmind) with encrypted API keys
- **models**: AI models (image/video/audio) with cost and token requirements
- **token_bundles**: Predefined token packages (100, 500, 1000, 2000) with USD base prices
- **orders**: Payment orders with Zarinpal integration, status, and currency rate snapshot
- **token_transactions**: Append-only ledger of all token movements (purchase, consume, refund, bonus, adjustment)
- **generation_jobs**: Unified job system for all content types with status, cost, and result URLs
- **gallery_posts**: User-published content with curation status, visibility, and metadata
- **currency_rates**: Historical USD to Toman exchange rates (for audit trail)
- **otp_verifications**: OTP codes with expiration and attempt tracking

**Supporting Entities:**
- **likes**: User likes on gallery posts
- **comments**: Nested comments on gallery posts
- **feed_view_limits**: Daily view limits per user
- **notifications**: User notifications for events
- **reports**: User reports on content
- **moderation_queue**: Admin moderation queue
- **analytics_models_usage**: Pre-aggregated model usage statistics (for performance)
- **system_health**: System health metrics (queue length, latency, etc.)

### Important Relationships

**User Relationships:**
- User hasMany GenerationJobs, GalleryPosts, TokenTransactions, Orders
- User belongsToMany GalleryPosts (through likes)
- User hasMany Comments

**Generation Job Relationships:**
- GenerationJob belongsTo User, Provider, Model
- GenerationJob hasOne GalleryPost (optional - if published)
- GenerationJob hasOne TokenTransaction (consumption transaction)

**Order Relationships:**
- Order belongsTo User, TokenBundle
- Order hasOne TokenTransaction (purchase transaction)

**Gallery Post Relationships:**
- GalleryPost belongsTo User, GenerationJob
- GalleryPost hasMany Likes, Comments
- GalleryPost can beCurated (admin action)

### Most Critical Entities

**Financial Integrity (Audit-Grade):**
- `token_transactions`: Append-only ledger, never modified/deleted
- `orders`: Immutable after creation, status transitions are linear
- `currency_rates`: Historical records for price audit trail

**Operational Core:**
- `generation_jobs`: Central to content generation, must maintain state consistency
- `users`: Token balance must reconcile with transactions
- `providers` & `models`: Configuration for AI generation

**User Experience:**
- `gallery_posts`: User content and curation
- `feed_view_limits`: Feed access control
- `otp_verifications`: Authentication state

---

## 6. Asynchronous & Background Processing

### Queue Usage

**Queue System:**
- Redis-backed Laravel queue system
- Multiple workers (default 4) process jobs in parallel
- Workers managed by Supervisor (auto-restart on crash)
- Job timeout per type: Images (2min), Videos (10min), Audio (4min)
- Worker max-time: 1 hour (prevents memory leaks, auto-restarts)

**Queue Jobs:**
- `GenerateImageJob`: Image generation with Segmind API, S3 storage, thumbnail generation
- `GenerateVideoJob`: Video generation with progress tracking
- `GenerateAudioJob`: Audio generation with metadata extraction

**Job Lifecycle:**
1. Job created with 'pending' status → Tokens reserved → Queued in Redis
2. Worker picks up job → Status → 'processing' → Calls Segmind API
3. Content downloaded → Stored in S3 → Thumbnail generated → Status → 'completed' → Tokens consumed
4. On failure: Status → 'failed' → Tokens refunded → Error logged → Retry allowed (max 3 attempts)

**Retry Strategy:**
- Max 3 retry attempts with exponential backoff
- Idempotent processing: Check job status before processing (prevent duplicate execution)
- Failed jobs logged in Laravel's failed_jobs table
- Manual retry capability via admin API

### Scheduled Tasks

**Frequency and Purpose:**
- **Currency Rate Fetch**: Every 5 minutes → Fetches USD rate from TGJU.org, stores in database, caches in Redis
- **Feed View Limit Reset**: Daily at 00:00 → Resets daily view limits for all users
- **Analytics Aggregation**: Daily at 01:00 → Pre-computes model usage statistics into analytics_models_usage table
- **OTP Cleanup**: Hourly → Removes expired OTP verification records (older than 1 hour)
- **Old Job Cleanup**: Daily at 02:00 → Deletes completed/failed/cancelled jobs older than 90 days
- **System Health Recording**: Every 5 minutes → Records queue length, failed jobs, API latency, error rates

**Reliability Mechanisms:**
- `withoutOverlapping()`: Prevents concurrent execution (mutex locks)
- Error handling: All tasks wrapped in try-catch, errors logged
- Idempotency: Tasks can be safely re-run if they fail mid-execution
- Structured logging: All tasks log start, completion, duration, records processed

### Background Processing Patterns

**Token Reservation Pattern:**
- Reservation happens synchronously in controller (atomic with job creation)
- Consumption happens asynchronously in queue job (on success)
- Refund happens in job's `failed()` method (on failure/cancellation)

**External API Integration Pattern:**
- Queue job calls external API (Segmind)
- Retry on transient failures (network, timeouts)
- Fail permanently on 4xx errors (user errors, refund tokens)
- Progress tracking for long-running jobs (videos)

**Storage Pattern:**
- Generated content streamed from API to S3 (avoid memory issues)
- Thumbnails generated server-side using GD/ImageMagick
- File paths organized: `generations/{job_type}/{user_id}/{job_id}.{ext}`

---

## 7. Admin & Analytics Capabilities

### Admin Dashboard Sections

**Sales Dashboard:**
- Revenue tracking (daily/weekly/monthly, in Toman and USD)
- Top-selling token bundles
- Refund and chargeback monitoring
- Customer lifetime value (LTV) analysis
- Date range filtering and CSV export

**Users Dashboard:**
- Active user metrics: DAU (Daily Active Users), WAU (Weekly), MAU (Monthly)
- Top users by generation count
- Top users by spending
- Cohort analysis and retention metrics
- User search and filtering

**Models Usage Dashboard:**
- Usage statistics per AI model (requests, success rate, failure rate)
- Average latency per model
- Token consumption per model
- Cost and revenue per model
- Model performance comparison

**Token Analytics Dashboard:**
- Token consumption by provider (Segmind)
- Token consumption by content type (image/video/audio)
- Cost and profit analysis per provider
- Time-series consumption trends

**Cost & Profit Dashboard:**
- Total revenue, cost, and profit
- Profit margins over time
- Breakdown by model and provider
- Historical profit analysis

**System Health Dashboard:**
- Queue length monitoring (alert if > 1000)
- Worker status and utilization
- Failed jobs tracking
- API latency monitoring (to Segmind)
- Storage and bandwidth usage
- Error rate calculation

**Feed Management Dashboard:**
- Curate/uncurate posts for public feed
- Bulk curation actions
- Featured post management
- Feed preview

### Metrics Tracked

**Financial Metrics:**
- Revenue: SUM(orders.price_toman WHERE status='paid')
- Cost: SUM(generation_jobs.cost_usd WHERE status='completed')
- Profit: Revenue - Cost
- Token sales: SUM(token_transactions.amount_tokens WHERE type='purchase')
- Token consumption: SUM(token_transactions.amount_tokens WHERE type='consume')

**Operational Metrics:**
- DAU/WAU/MAU: Users with at least one completed generation in period
- Generation success rate: COUNT(completed) / COUNT(total) per model
- Average job latency: AVG(completed_at - started_at) per model
- Queue length: COUNT(pending jobs)
- Failed job rate: COUNT(failed) / COUNT(total) in last 24 hours

**Performance Optimizations:**
- Aggregated tables (analytics_models_usage) for historical data
- Caching: 5-15 minute TTL for summary endpoints
- Indexes on date columns (created_at, paid_at, completed_at)
- Pagination: Max 100 items per page
- Query optimization: Date range filtering first, then aggregation

---

## 8. Non-Functional Requirements

### Security Considerations

**Authentication Security:**
- OTP-based auth eliminates password storage risks
- OTP codes: 6-digit cryptographically secure random numbers
- OTP storage: HMAC-SHA256 hash (short-lived, not bcrypt)
- Rate limiting: Prevents brute-force and SMS spam (3 requests per 15 min per phone)
- Request IDs (UUID) prevent phone enumeration attacks
- OTP expiration: 5 minutes, max 3 verification attempts

**Data Security:**
- API keys encrypted in database (Laravel encryption)
- Sensitive data encrypted at rest
- HTTPS for all communications
- Input validation and sanitization
- SQL injection protection via Eloquent ORM
- No secrets in logs (API keys, tokens, passwords never logged)

**Payment Security:**
- PCI compliance: No card data stored (handled by Zarinpal)
- Server-to-server payment verification (never trust client data)
- Idempotent payment requests and callbacks
- Immutable audit trail for all transactions

**Authorization:**
- Admin middleware protects admin endpoints
- Role-based access control (RBAC)
- Users can only access their own data
- Admin-only access to analytics and moderation

### Performance and Scalability Goals

**Horizontal Scaling:**
- Multiple Laravel API instances behind load balancer
- Queue workers scale independently (adjust numprocs in Supervisor)
- Database read replicas for analytics queries
- Redis cluster for distributed caching

**Vertical Scaling:**
- Database optimization: Strategic indexes on frequently queried columns
- Aggressive caching: Token bundles, currency rates, aggregated analytics
- CDN for media files (S3 with CloudFront)
- Database partitioning by date for large tables (future consideration)

**Performance Targets:**
- API response time: < 200ms for non-generation endpoints
- Generation job latency: Images (5-30s), Videos (1-5min), Audio (10-60s)
- Dashboard load time: < 2s with caching
- Queue processing: Keep backlog < 1000 jobs

**Caching Strategy:**
- Currency rates: Redis cache (5-minute TTL)
- Token bundles: Cache until updated
- Analytics summaries: Cache 5-15 minutes
- Feed data: Cache with invalidation on new curation

### Reliability Expectations

**Uptime Targets:**
- 99.9% uptime (43 minutes downtime per month)
- Graceful degradation: System continues operating if non-critical services fail

**Failure Handling:**
- Automatic retries for transient failures (network, API timeouts)
- Failed job handling with manual retry capability
- Fallback mechanisms: Currency rate fallback to last known rate
- Idempotent operations: Safe to retry without side effects

**Data Integrity:**
- Atomic operations: Database transactions ensure consistency
- Token balance reconciliation: Balance must match transaction sum
- Immutable records: Financial records never modified/deleted
- Audit trail: Full history of all token movements

**Monitoring and Alerting:**
- Queue length alerts (if > 1000 jobs)
- Failed job rate alerts (if > 10%)
- Worker status monitoring
- Scheduled task failure alerts
- API latency monitoring
- Error rate tracking

**Disaster Recovery:**
- Regular database backups (especially token_transactions, orders, generation_jobs)
- Redis persistence for queue jobs
- S3 storage redundancy
- Rollback capability for critical changes

---

## 9. Development Roadmap

### Major Implementation Phases

**Phase 1: Foundation & Authentication (Weeks 1-2)**
- Database migrations and models
- OTP authentication system
- User profile management
- Critical for: User onboarding and security foundation

**Phase 2: Token System & Payment (Weeks 3-4)**
- Token bundle management
- Currency rate scraper (TGJU.org)
- Zarinpal payment integration
- Token transaction system
- Critical for: Revenue generation and financial correctness

**Phase 3: Segmind API Integration (Weeks 5-8)**
- Base Segmind service
- Image generation integration
- Video generation integration
- Audio generation integration
- Unified generation job system
- Critical for: Core product functionality

**Phase 4: Gallery & Feed System (Weeks 9-10)**
- Gallery post management
- Admin feed curation
- Public feed with view limits
- Social features (likes, comments)
- Critical for: User engagement and content discovery

**Phase 5: Admin Dashboard (Weeks 11-14)**
- Admin authentication and authorization
- Sales dashboard
- Users dashboard
- Models usage dashboard
- Token analytics dashboard
- Cost & profit dashboard
- System health dashboard
- Feed management dashboard
- Critical for: Business operations and decision-making

**Phase 6: Queue Jobs & Scheduled Tasks (Parallel to other phases)**
- Queue worker configuration
- Scheduled tasks (currency fetch, cleanup, analytics aggregation)
- Critical for: System reliability and operational efficiency

**Phase 7: Additional Features (Weeks 15+)**
- Notification system
- Content moderation and reporting
- User blocking (if specified)
- Critical for: Trust & safety and user experience

### What Should Be Built First vs Later

**Must Build First (Critical Path):**
1. Authentication system (Phase 1) - Users cannot access platform without this
2. Token system & payment (Phase 2) - Revenue generation requires this
3. Image generation (Phase 3) - Core product feature
4. Basic gallery (Phase 4) - Users need to see their generated content
5. Admin authentication (Phase 5) - Admins need to manage platform

**Can Build Later (Important but Not Blocking):**
- Video and audio generation (can start with images only)
- Advanced analytics dashboards (basic sales tracking sufficient initially)
- Content moderation (can start with manual admin review)
- Notification system (can add after core features stable)
- Feed view limits (can launch without limits initially)
- System health dashboard (can use logs initially)

**Parallel Development (Non-Blocking):**
- Queue workers and scheduled tasks (can develop alongside other features)
- Database indexes and optimization (can add after identifying bottlenecks)
- Caching layers (can add after performance issues identified)
- Advanced admin features (can iterate after basic admin access)

**Future Enhancements:**
- Mobile app (Flutter) - separate project
- Advanced prompt builder with AI suggestions
- Image editing and remix features
- Batch generation
- API access for third-party integrations
- Subscription plans (alternative to token bundles)
- Multi-language support

---

## Summary of Critical Principles

1. **Financial Correctness**: Token balance must reconcile with transactions, all financial operations must be atomic and idempotent
2. **Token Safety**: Exactly-once consumption semantics - reservation → consumption → refund lifecycle is critical
3. **Security First**: OTP-based auth, encrypted API keys, server-side price calculation, no secrets in logs
4. **Reliability**: Idempotent operations, retry strategies, graceful failure handling, audit trails
5. **Observability**: Structured logging, metrics tracking, no secrets in logs, comprehensive error handling
6. **Performance**: Caching, indexing, query optimization, horizontal scaling capability
7. **User Experience**: Asynchronous processing, real-time status updates, non-blocking API responses
8. **Admin Capabilities**: Comprehensive analytics, moderation tools, system health monitoring

