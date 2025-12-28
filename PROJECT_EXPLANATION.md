# Negarify Platform - Project Explanation

## Table of Contents
1. [What is Negarify?](#what-is-negarify)
2. [Project Overview](#project-overview)
3. [Core Concept](#core-concept)
4. [Key Features](#key-features)
5. [How It Works](#how-it-works)
6. [Architecture](#architecture)
7. [Technology Stack](#technology-stack)
8. [Business Model](#business-model)
9. [User Flows](#user-flows)
10. [Key Components](#key-components)
11. [Security & Privacy](#security--privacy)
12. [Scalability & Performance](#scalability--performance)

---

## What is Negarify?

**Negarify** is an AI-powered content generation platform that enables users to create high-quality images, videos, and audio content using advanced artificial intelligence models. The platform operates on a token-based economy, where users purchase tokens to generate content through a simple, user-friendly interface.

### Vision
To democratize AI content creation by providing easy access to state-of-the-art AI models for generating images, videos, and audio, making professional-quality content creation accessible to everyone.

### Mission
To bridge the gap between complex AI technology and everyday users, offering a seamless platform where creativity meets artificial intelligence.

---

## Project Overview

### Platform Type
Negarify is a **Software as a Service (SaaS)** platform that aggregates multiple AI content generation models from Segmind API, providing a unified interface for users to generate various types of media content.

### Target Audience
- **Content Creators**: Bloggers, social media managers, marketers
- **Artists & Designers**: Digital artists, graphic designers, creative professionals
- **Businesses**: Small to medium businesses needing visual content
- **Hobbyists**: Individuals interested in AI and content creation
- **Developers**: Those needing AI-generated content for applications

### Market Position
Negarify positions itself as an affordable, user-friendly alternative to complex AI model APIs, with a focus on the Iranian market (supporting Toman currency and Zarinpal payments) while maintaining international accessibility.

---

## Core Concept

### Token-Based Economy

Negarify operates on a **token-based economy** where:

1. **Users Purchase Tokens**: Users buy token packages (100, 500, 1000, or 2000 tokens) using Iranian Toman currency
2. **Tokens Are Consumed**: Each content generation (image, video, or audio) consumes a specific number of tokens based on:
   - The AI model used
   - Content quality/size
   - Content type (image/video/audio)
3. **Real-Time Pricing**: Token prices are calculated in real-time based on current USD to Toman exchange rates

### Why Tokens?
- **Simplified Pricing**: Users don't need to understand complex API pricing structures
- **Predictable Costs**: Users know exactly how much each generation costs
- **Flexibility**: Tokens can be used for any type of content (image/video/audio)
- **Prepaid Model**: Users pay upfront, ensuring no surprise charges

---

## Key Features

### 1. Multi-Format Content Generation

#### Image Generation
- Generate high-quality images from text prompts
- Multiple AI models available (Flux, Stable Diffusion, etc.)
- Customizable parameters:
  - Image size (512x512, 1024x1024, etc.)
  - Quality settings
  - Style preferences
  - Negative prompts (what to exclude)

#### Video Generation
- Create videos from text descriptions
- Configurable duration, resolution, and frame rate
- Support for various video formats

#### Audio Generation
- Generate music, sound effects, and audio content
- Customizable duration and format
- Multiple audio generation models

### 2. Curated Public Feed

- **Admin-Curated Content**: Only high-quality, admin-selected content appears in the public feed
- **Inspiration Source**: Users can browse curated images and videos for inspiration
- **Copy & Regenerate**: Users can copy prompts and model information from feed items to generate similar content
- **View Limits**: Free users have daily view limits to encourage token purchases
- **No Audio in Feed**: Audio content is not displayed in the public feed (only images and videos)

### 3. Gallery & Social Features

- **Personal Gallery**: Users can publish their generated content to their personal gallery
- **Public Sharing**: Option to make posts public or private
- **Social Interactions**: Like and comment on posts
- **Tags & Categories**: Organize content with tags
- **Prompt Visibility**: Users can choose to show or hide their prompts
- **Model Visibility**: Users can choose to show or hide which model was used

### 4. Smart Token System

#### Token Packages
- **Starter Pack**: 100 tokens - For occasional users
- **Standard Pack**: 500 tokens - For regular users
- **Premium Pack**: 1000 tokens - For power users
- **Ultimate Pack**: 2000 tokens - For heavy users

#### Real-Time Pricing
- Prices calculated dynamically based on current USD to Toman exchange rate
- Exchange rate fetched from TGJU.org every 5 minutes
- Prices displayed in Iranian Toman for local users

### 5. Secure Authentication

- **OTP-Based Login**: Phone number authentication via SMS
- **No Password Required**: Secure OTP verification through Melipayamak SMS service
- **Rate Limiting**: Protection against abuse and spam
- **Session Management**: Secure JWT token-based sessions

### 6. Comprehensive Admin Dashboard

Admins have access to detailed analytics and management tools:

#### Sales Dashboard
- Revenue tracking (daily, weekly, monthly)
- Top-selling token bundles
- Refund and chargeback monitoring
- Customer lifetime value (LTV) analysis

#### Users Dashboard
- Active user metrics (DAU, WAU, MAU)
- Top users by generation count
- Top users by spending
- Cohort analysis and retention metrics
- User search and filtering

#### Models Usage Dashboard
- Usage statistics per AI model
- Success/failure rates
- Average latency per model
- Cost and revenue per model
- Model performance comparison

#### Token Analytics Dashboard
- Token consumption by provider
- Token consumption by content type (image/video/audio)
- Cost and profit analysis per provider
- Time-series consumption trends

#### Cost & Profit Dashboard
- Total revenue, cost, and profit
- Profit margins over time
- Breakdown by model and provider
- Historical profit analysis

#### System Health Dashboard
- Queue length monitoring
- Worker status
- Failed jobs tracking
- API latency monitoring
- Storage and bandwidth usage

#### Feed Management Dashboard
- Curate/uncurate posts for public feed
- Bulk curation actions
- Featured post management
- Feed preview

### 7. Asynchronous Job Processing

- **Non-Blocking**: Generation requests are processed asynchronously
- **Status Tracking**: Real-time job status updates
- **Progress Monitoring**: Progress indicators for long-running jobs
- **Retry Mechanism**: Automatic retry for failed jobs
- **Token Reservation**: Tokens are reserved when job starts, consumed on completion

---

## How It Works

### User Journey

#### 1. Registration & Authentication
```
User enters phone number
    ↓
System sends OTP via SMS (Melipayamak)
    ↓
User enters OTP code
    ↓
Account created/authenticated
    ↓
JWT token issued
```

#### 2. Token Purchase
```
User selects token package (100, 500, 1000, or 2000 tokens)
    ↓
System fetches current USD to Toman rate (from TGJU.org)
    ↓
Price calculated in Toman
    ↓
User redirected to Zarinpal payment gateway
    ↓
Payment completed
    ↓
Tokens credited to user account
```

#### 3. Content Generation
```
User selects content type (Image/Video/Audio)
    ↓
User selects AI model
    ↓
User enters prompt and parameters
    ↓
System checks token balance
    ↓
System reserves tokens
    ↓
Job created and queued
    ↓
Background worker processes job via Segmind API
    ↓
Content generated and stored in S3
    ↓
Thumbnail created (for images/videos)
    ↓
Tokens consumed
    ↓
User notified of completion
```

#### 4. Gallery Publishing
```
User views generated content
    ↓
User clicks "Publish to Gallery"
    ↓
User adds title, description, tags
    ↓
User sets visibility (public/private)
    ↓
User sets prompt/model visibility
    ↓
Post created in gallery
    ↓
Admin can curate post for public feed
```

#### 5. Feed Browsing
```
User opens curated feed
    ↓
System checks daily view limit
    ↓
Feed displays curated images/videos only
    ↓
User can view, like, comment
    ↓
User can copy prompt/model to regenerate
    ↓
View count decremented
    ↓
When limit reached, user sees limit message
```

---

## Architecture

### System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Frontend (Vue.js 3)                    │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │   User    │  │  Admin   │  │ Gallery  │  │ Generate │   │
│  │  Pages    │  │ Dashboard│  │  Feed    │  │  Pages   │   │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
└───────────────────────────┬─────────────────────────────────┘
                            │
                            │ HTTP/REST API
                            │
┌───────────────────────────▼─────────────────────────────────┐
│              Backend API (Laravel 10)                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │   Controllers │  │   Services  │  │  Middleware   │    │
│  └──────────────┘  └──────────────┘  └──────────────┘    │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              Queue System (Redis)                    │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐         │   │
│  │  │ Generate │  │ Generate │  │ Generate │         │   │
│  │  │  Image  │  │  Video   │  │  Audio   │         │   │
│  │  │   Job   │  │   Job    │  │   Job    │         │   │
│  │  └──────────┘  └──────────┘  └──────────┘         │   │
│  └──────────────────────────────────────────────────────┘   │
└───────────────────────────┬─────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
┌───────▼────────┐  ┌───────▼────────┐  ┌───────▼────────┐
│   Database     │  │      Redis      │  │  S3 Storage    │
│  (MySQL/PostgreSQL)│  │   (Cache/Queue)│  │  (Media Files)│
└────────────────┘  └────────────────┘  └────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
┌───────▼────────┐  ┌───────▼────────┐  ┌───────▼────────┐
│  Segmind API   │  │   Zarinpal      │  │  Melipayamak    │
│  (AI Models)   │  │  (Payments)     │  │   (SMS/OTP)     │
└────────────────┘  └────────────────┘  └────────────────┘
```

### Data Flow

#### Request Flow
1. **User Request**: User submits generation request from frontend
2. **API Validation**: Backend validates request and checks token balance
3. **Token Reservation**: Tokens are reserved (deducted temporarily)
4. **Job Creation**: Generation job created and queued
5. **Async Processing**: Queue worker picks up job
6. **API Call**: Worker calls Segmind API
7. **Content Storage**: Generated content downloaded and stored in S3
8. **Token Consumption**: Tokens are consumed (finalized)
9. **Notification**: User notified of completion
10. **Result Delivery**: Frontend fetches and displays result

#### Payment Flow
1. **Bundle Selection**: User selects token bundle
2. **Price Calculation**: Backend fetches current USD rate and calculates Toman price
3. **Order Creation**: Order created with pending status
4. **Payment Request**: Backend requests payment from Zarinpal
5. **User Redirect**: User redirected to Zarinpal payment page
6. **Payment Completion**: User completes payment
7. **Callback**: Zarinpal redirects to callback URL
8. **Verification**: Backend verifies payment with Zarinpal
9. **Token Credit**: Tokens credited to user account
10. **Transaction Log**: Transaction logged for audit

---

## Technology Stack

### Backend
- **Framework**: Laravel 10.x
- **Language**: PHP 8.2+
- **Database**: MySQL 8.0+ / PostgreSQL 14+
- **Cache & Queue**: Redis 7.0+
- **Storage**: S3-compatible object storage
- **Authentication**: Laravel Sanctum (JWT tokens)

### Frontend
- **Framework**: Vue.js 3.4+
- **Build Tool**: Vite 5.0+
- **State Management**: Pinia 2.x
- **Routing**: Vue Router 4.x
- **UI Framework**: Element Plus (or Vuetify/Tailwind)
- **HTTP Client**: Axios

### External Services
- **AI Provider**: Segmind API (https://www.segmind.com)
- **Payment Gateway**: Zarinpal (Iranian payment gateway)
- **SMS Service**: Melipayamak (OTP delivery)
- **Currency Data**: TGJU.org (web scraping for USD rates)

### Infrastructure
- **Web Server**: Nginx / Apache
- **Queue Workers**: Supervisor (process management)
- **Scheduler**: Laravel Scheduler (cron)
- **Monitoring**: Laravel Telescope (dev), Logging

---

## Business Model

### Revenue Streams

#### Primary Revenue: Token Sales
- Users purchase token packages
- Tokens are consumed per generation
- Profit margin: Difference between token price and Segmind API costs

#### Pricing Strategy
- **Base Pricing**: Token packages priced in USD
- **Local Pricing**: Converted to Toman using real-time exchange rates
- **Dynamic Pricing**: Prices update automatically with exchange rate changes
- **Volume Discounts**: Larger packages offer better value per token

### Cost Structure

#### Variable Costs
- **Segmind API Costs**: Pay-per-use for each generation
- **SMS Costs**: OTP delivery via Melipayamak
- **Storage Costs**: S3 storage for generated content
- **Payment Processing**: Zarinpal transaction fees

#### Fixed Costs
- **Infrastructure**: Servers, databases, Redis
- **Development**: Ongoing development and maintenance
- **Support**: Customer support and operations

### Profitability Model
- **Token Markup**: Tokens priced higher than API costs
- **Volume Optimization**: Bulk API usage may reduce per-unit costs
- **Efficiency**: Automated processes reduce operational costs
- **Analytics**: Data-driven optimization of token pricing

---

## User Flows

### New User Flow
```
1. Visit website
2. Click "Sign Up" or "Get Started"
3. Enter phone number
4. Receive OTP via SMS
5. Enter OTP code
6. Account created
7. Redirected to token purchase page
8. Purchase token package
9. Redirected to generation page
10. Generate first content
```

### Returning User Flow
```
1. Login with phone number + OTP
2. View dashboard (token balance, recent generations)
3. Choose action:
   - Generate new content
   - Browse gallery feed
   - View own gallery
   - Purchase more tokens
```

### Content Generation Flow
```
1. Navigate to generation page
2. Select content type (Image/Video/Audio)
3. Select AI model
4. Enter prompt
5. Configure parameters (size, quality, etc.)
6. View estimated token cost
7. Click "Generate"
8. Job created, tokens reserved
9. Monitor job status (pending → processing → completed)
10. View generated content
11. Option to:
    - Download content
    - Publish to gallery
    - Generate variations
    - Regenerate with different parameters
```

### Gallery & Feed Flow
```
1. Navigate to gallery feed
2. Browse curated images/videos
3. View content details
4. Option to:
    - Like content
    - Comment
    - Copy prompt
    - Copy model info
    - Generate similar content
5. View own gallery posts
6. Manage own posts (edit, delete, visibility)
```

### Admin Flow
```
1. Login to admin dashboard
2. View overview metrics
3. Navigate to specific dashboard:
    - Sales: Monitor revenue and orders
    - Users: Manage users and view analytics
    - Models: Monitor AI model usage and performance
    - Tokens: Track token consumption
    - Cost/Profit: Analyze profitability
    - System Health: Monitor infrastructure
    - Feed Management: Curate public feed
4. Take actions:
    - Curate posts for feed
    - Manage users
    - View detailed analytics
    - Export reports
```

---

## Key Components

### 1. Token System

#### Token Packages
- Fixed packages: 100, 500, 1000, 2000 tokens
- Prices calculated dynamically based on USD to Toman rate
- Bonus tokens possible (configurable by admin)

#### Token Consumption
- Varies by model, quality, and content type
- Reserved when job starts
- Consumed on successful completion
- Refunded on job failure

#### Token Transactions
- All token movements logged
- Transaction types: purchase, consume, refund, bonus, adjustment
- Full audit trail for financial transparency

### 2. Currency Conversion System

#### Real-Time Rate Fetching
- Scrapes TGJU.org every 5 minutes
- Extracts USD to Rials rate
- Converts to Toman (Rials ÷ 10)
- Caches rate in Redis (5-minute TTL)
- Falls back to last known rate if scraping fails

#### Price Calculation
- Base price in USD per token package
- Multiplied by current USD to Toman rate
- Displayed in Toman to users
- Stored in order for audit trail

### 3. Content Generation System

#### Job Processing
- Asynchronous queue-based processing
- Multiple workers process jobs in parallel
- Status tracking: pending → processing → completed/failed
- Progress updates for long-running jobs
- Automatic retry on failure (max 3 attempts)

#### Content Storage
- Generated content stored in S3-compatible storage
- Thumbnails generated for images/videos
- CDN-ready URLs for fast delivery
- Organized by user and date

### 4. Curated Feed System

#### Curation Process
- Admins review user-generated content
- Select high-quality content for feed
- Mark posts as "curated"
- Featured posts can be prioritized
- Bulk curation actions available

#### Feed Display
- Only curated content appears in public feed
- Images and videos only (no audio)
- Cursor-based pagination
- Infinite scroll support
- View limits enforced per user

### 5. Admin Dashboard System

#### Analytics Aggregation
- Scheduled jobs aggregate metrics daily
- Cached results for fast dashboard loading
- Historical data for trend analysis
- Exportable reports (CSV)

#### Real-Time Monitoring
- System health metrics updated every 5 minutes
- Queue length monitoring
- API latency tracking
- Error rate calculation

---

## Security & Privacy

### Authentication Security
- **OTP-Based**: No password storage required
- **Rate Limiting**: Prevents brute force attacks
- **Token Expiration**: OTP codes expire after 5 minutes
- **Attempt Limiting**: Max 3 verification attempts per OTP
- **JWT Tokens**: Secure, stateless authentication

### Data Security
- **API Key Encryption**: Provider API keys encrypted in database
- **Sensitive Data**: User data encrypted at rest
- **HTTPS**: All communications encrypted in transit
- **Input Validation**: All user inputs validated and sanitized
- **SQL Injection Protection**: Laravel's Eloquent ORM prevents SQL injection

### Payment Security
- **PCI Compliance**: No card data stored (handled by Zarinpal)
- **Payment Verification**: All payments verified server-side
- **Idempotency**: Payment requests are idempotent
- **Audit Trail**: All transactions logged immutably

### Privacy
- **User Data**: Minimal data collection (phone, name, email optional)
- **Content Privacy**: Users control content visibility
- **Prompt Privacy**: Users can hide prompts from public view
- **GDPR Ready**: Data export and deletion capabilities

---

## Scalability & Performance

### Scalability Strategies

#### Horizontal Scaling
- **API Servers**: Multiple Laravel instances behind load balancer
- **Queue Workers**: Scale workers based on queue length
- **Database**: Read replicas for analytics queries
- **Cache**: Redis cluster for distributed caching

#### Vertical Scaling
- **Database Optimization**: Indexes, query optimization
- **Caching**: Aggressive caching of frequently accessed data
- **CDN**: Content delivery network for media files
- **Database Partitioning**: Partition large tables by date

### Performance Optimizations

#### Frontend
- **Code Splitting**: Lazy load routes and components
- **Image Optimization**: Lazy loading, WebP format
- **Caching**: Browser caching for static assets
- **Bundle Optimization**: Tree shaking, minification

#### Backend
- **Query Optimization**: Eager loading, select specific columns
- **Caching**: Cache frequently accessed data (token bundles, rates)
- **Queue Processing**: Async processing prevents blocking
- **Database Indexing**: Strategic indexes on frequently queried columns

### Monitoring & Alerting

#### Metrics Tracked
- Request rates and latency
- Token consumption rates
- Queue lengths and processing times
- Error rates and types
- API response times
- Storage usage

#### Alerts
- High error rates
- Queue backup
- API failures
- Payment issues
- System resource exhaustion

---

## Future Enhancements

### Planned Features
- **Mobile App**: Native Flutter app for iOS and Android
- **Advanced Prompt Builder**: AI-assisted prompt suggestions
- **Image Editing**: In-app image editing and remix features
- **Batch Generation**: Generate multiple variations at once
- **API Access**: RESTful API for third-party integrations
- **Subscription Plans**: Monthly subscription options
- **Affiliate Program**: Referral system for user acquisition
- **Multi-Language Support**: Internationalization
- **Advanced Analytics**: User behavior analytics
- **Content Templates**: Pre-built prompt templates

### Technical Improvements
- **WebSocket Support**: Real-time job status updates
- **GraphQL API**: Alternative to REST API
- **Microservices**: Split into smaller services
- **Kubernetes**: Container orchestration
- **Machine Learning**: Predictive analytics for token pricing

---

## Conclusion

Negarify is a comprehensive AI content generation platform that simplifies access to advanced AI models through a token-based economy. With support for images, videos, and audio generation, a curated feed for inspiration, and comprehensive admin tools, Negarify provides a complete solution for both content creators and platform administrators.

The platform's focus on the Iranian market (with Toman pricing and Zarinpal payments) while maintaining international accessibility positions it well for growth in the Middle East region and beyond.

---

**Document Version**: 1.0  
**Last Updated**: 2024-01-01  
**Project Status**: Active Development  
**Contact**: For questions or clarifications, please refer to the development team.

