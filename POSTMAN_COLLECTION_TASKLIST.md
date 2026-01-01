# Postman Collection Creation Task List

**Objective:** Create a comprehensive Postman collection JSON file for Negarify Backend API with complete test data for all endpoints.

**Date Created:** 2025-01-01  
**Status:** In Progress

---

## Task Breakdown

### Phase 1: Analysis & Planning

- [x] **Task 1.1**: Review all API routes from `routes/api.php`
- [x] **Task 1.2**: Analyze request validation rules from FormRequest classes
- [x] **Task 1.3**: Identify authentication requirements (Bearer token via Sanctum)
- [x] **Task 1.4**: Identify public vs protected endpoints
- [x] **Task 1.5**: Map all endpoint categories for folder organization

**Findings:**
- Base URL: `/api/v1`
- Authentication: Bearer token (Sanctum JWT)
- Public endpoints: Auth (request-otp, verify-otp, resend-otp), Token bundles, Currency rate, Payment callback
- Protected endpoints: All other endpoints require `Authorization: Bearer {token}`
- Admin endpoints: Require admin role in addition to authentication

---

### Phase 2: Collection Structure Design

- [x] **Task 2.1**: Define collection structure and folder hierarchy
- [x] **Task 2.2**: Design environment variables
- [x] **Task 2.3**: Design authentication setup (collection-level auth)
- [x] **Task 2.4**: Plan test data structure for each endpoint category

**Collection Structure:**
```
Negarify API Collection
├── 01. Authentication (Public)
│   ├── Request OTP
│   ├── Verify OTP
│   ├── Resend OTP
│   └── Logout
├── 02. User Profile
│   ├── Get Profile
│   ├── Update Profile
│   └── Upload Avatar
├── 03. Tokens & Payments
│   ├── List Token Bundles (Public)
│   ├── Get Currency Rate (Public)
│   ├── Purchase Tokens
│   ├── Get Balance
│   ├── Get Transaction History
│   └── Payment Callback (Public)
├── 04. Generation
│   ├── Generate Image
│   ├── Generate Video
│   ├── Generate Audio
│   ├── List Jobs
│   ├── Get Job Details
│   ├── Cancel Job
│   └── Retry Job
├── 05. Gallery & Feed
│   ├── Create Gallery Post
│   ├── Get Post Details
│   ├── Update Post
│   ├── Delete Post
│   ├── Get My Posts
│   ├── Get Feed
│   ├── Copy Prompt
│   ├── Copy Model
│   ├── Like Post
│   ├── Unlike Post
│   ├── Add Comment
│   ├── Delete Comment
│   └── Report Post
├── 06. Notifications
│   ├── List Notifications
│   ├── Get Notification
│   ├── Mark as Read
│   ├── Mark as Unread
│   └── Mark All as Read
└── 07. Admin (Requires Admin Role)
    ├── Token Bundles Management
    │   ├── Create Bundle
    │   ├── Update Bundle
    │   ├── Delete Bundle
    │   ├── Activate Bundle
    │   └── Deactivate Bundle
    ├── Gallery Curation
    │   ├── Curate Post
    │   ├── Uncurate Post
    │   ├── Feature Post
    │   ├── Unfeature Post
    │   ├── Bulk Curate
    │   ├── Bulk Uncurate
    │   └── List Curated Posts
    ├── Analytics
    │   ├── Sales Summary
    │   ├── Users Summary
    │   ├── Users List
    │   ├── Models Usage
    │   ├── Tokens Summary
    │   ├── Cost Profit Summary
    │   └── System Health
    └── Moderation
        ├── Get Moderation Queue
        ├── Get Reports
        ├── Approve Post
        ├── Reject Post
        ├── Resolve Report
        └── Dismiss Report
```

**Environment Variables:**
- `base_url`: Base API URL (e.g., `http://localhost:8000/api/v1` or `https://api.negarify.com/api/v1`)
- `auth_token`: Bearer token (auto-set after OTP verification)
- `request_id`: OTP request ID (used for OTP verification)
- `user_id`: Current user ID (for reference)
- `admin_token`: Admin user token (for admin endpoints)
- `generation_job_id`: Generation job ID (used in multiple endpoints)
- `gallery_post_id`: Gallery post ID (used in multiple endpoints)
- `comment_id`: Comment ID (for comment operations)
- `token_bundle_id`: Token bundle ID (for purchase)

---

### Phase 3: Request Data Design

- [x] **Task 3.1**: Create test data for Authentication endpoints
- [x] **Task 3.2**: Create test data for User Profile endpoints
- [x] **Task 3.3**: Create test data for Token & Payment endpoints
- [x] **Task 3.4**: Create test data for Generation endpoints
- [x] **Task 3.5**: Create test data for Gallery & Feed endpoints
- [x] **Task 3.6**: Create test data for Notification endpoints
- [x] **Task 3.7**: Create test data for Admin endpoints

**Test Data Requirements:**

1. **Authentication:**
   - Phone numbers (Iranian format: `09123456789`, `+989123456789`)
   - OTP codes (6-digit: `123456`)
   - Request IDs (UUID format)

2. **User Profile:**
   - Names, emails
   - Avatar files (multipart/form-data)

3. **Tokens:**
   - Token bundle IDs (1, 2, 3, 4 for standard bundles)
   - Transaction types, date ranges

4. **Generation:**
   - Model IDs (image, video, audio models)
   - Prompts (creative, descriptive)
   - Parameters (size, style, seed, steps, guidance_scale)
   - Negative prompts

5. **Gallery:**
   - Titles, descriptions
   - Tags (arrays)
   - Visibility settings
   - Parent comment IDs for nested comments

6. **Admin:**
   - Date ranges (daily, weekly, monthly)
   - Filter parameters
   - Bulk operation data

---

### Phase 4: Collection JSON Generation

- [ ] **Task 4.1**: Create Postman collection JSON structure
- [ ] **Task 4.2**: Add collection metadata (name, description, schema)
- [ ] **Task 4.3**: Add authentication setup (Bearer token)
- [ ] **Task 4.4**: Add environment variables
- [ ] **Task 4.5**: Add all folders with proper hierarchy
- [ ] **Task 4.6**: Add all requests with:
  - HTTP method and URL
  - Headers (Content-Type, Authorization where needed)
  - Request bodies (JSON or form-data)
  - Query parameters
  - Path variables
- [ ] **Task 4.7**: Add pre-request scripts (if needed for dynamic data)
- [ ] **Task 4.8**: Add test scripts (if needed for response validation)
- [ ] **Task 4.9**: Add response examples (optional but helpful)
- [ ] **Task 4.10**: Validate JSON structure and syntax

---

### Phase 5: Documentation & Verification

- [ ] **Task 5.1**: Add collection description with usage instructions
- [ ] **Task 5.2**: Add folder descriptions
- [ ] **Task 5.3**: Add request descriptions where needed
- [ ] **Task 5.4**: Create README or usage guide
- [ ] **Task 5.5**: Test import into Postman
- [ ] **Task 5.6**: Verify all endpoints are present
- [ ] **Task 5.7**: Verify authentication works
- [ ] **Task 5.8**: Verify test data is realistic and complete

---

## Endpoint Checklist

### Public Endpoints (No Auth)
- [x] POST `/v1/auth/request-otp`
- [x] POST `/v1/auth/verify-otp`
- [x] POST `/v1/auth/resend-otp`
- [x] GET `/v1/tokens/bundles`
- [x] GET `/v1/currency/rate`
- [x] GET `/v1/tokens/purchase/callback`

### Protected Endpoints (Bearer Token Required)
- [x] POST `/v1/auth/logout`
- [x] GET `/v1/user`
- [x] PUT `/v1/user`
- [x] POST `/v1/user/avatar`
- [x] POST `/v1/tokens/purchase`
- [x] GET `/v1/tokens/balance`
- [x] GET `/v1/tokens/history`
- [x] POST `/v1/tokens/consume` (Legacy)
- [x] POST `/v1/tokens/add` (Legacy)
- [x] POST `/v1/generate/image`
- [x] POST `/v1/generate/video`
- [x] POST `/v1/generate/audio`
- [x] GET `/v1/generate/jobs`
- [x] GET `/v1/generate/jobs/{id}`
- [x] POST `/v1/generate/jobs/{id}/cancel`
- [x] POST `/v1/generate/jobs/{id}/retry`
- [x] POST `/v1/gallery/post`
- [x] GET `/v1/gallery/posts/{id}`
- [x] PUT `/v1/gallery/posts/{id}`
- [x] DELETE `/v1/gallery/posts/{id}`
- [x] GET `/v1/gallery/my-posts`
- [x] GET `/v1/gallery/feed`
- [x] POST `/v1/gallery/feed/copy-prompt`
- [x] POST `/v1/gallery/feed/copy-model`
- [x] POST `/v1/gallery/{id}/like`
- [x] DELETE `/v1/gallery/{id}/like`
- [x] POST `/v1/gallery/{id}/comment`
- [x] DELETE `/v1/gallery/comments/{id}`
- [x] POST `/v1/gallery/posts/{id}/report`
- [x] GET `/v1/notifications`
- [x] GET `/v1/notifications/{id}`
- [x] PUT `/v1/notifications/{id}/read`
- [x] PUT `/v1/notifications/{id}/unread`
- [x] PUT `/v1/notifications/read-all`

### Admin Endpoints (Bearer Token + Admin Role Required)
- [x] POST `/v1/admin/tokens/bundles`
- [x] PUT `/v1/admin/tokens/bundles/{id}`
- [x] DELETE `/v1/admin/tokens/bundles/{id}`
- [x] POST `/v1/admin/tokens/bundles/{id}/activate`
- [x] POST `/v1/admin/tokens/bundles/{id}/deactivate`
- [x] POST `/v1/admin/gallery/{id}/curate`
- [x] POST `/v1/admin/gallery/{id}/uncurate`
- [x] POST `/v1/admin/gallery/{id}/feature`
- [x] POST `/v1/admin/gallery/{id}/unfeature`
- [x] POST `/v1/admin/gallery/bulk-curate`
- [x] POST `/v1/admin/gallery/bulk-uncurate`
- [x] GET `/v1/admin/gallery/curated`
- [x] GET `/v1/admin/sales/summary`
- [x] GET `/v1/admin/users/summary`
- [x] GET `/v1/admin/users/list`
- [x] GET `/v1/admin/models/usage`
- [x] GET `/v1/admin/tokens/summary`
- [x] GET `/v1/admin/cost-profit/summary`
- [x] GET `/v1/admin/system-health`
- [x] GET `/v1/admin/moderation/queue`
- [x] GET `/v1/admin/moderation/reports`
- [x] POST `/v1/admin/moderation/posts/{id}/approve`
- [x] POST `/v1/admin/moderation/posts/{id}/reject`
- [x] POST `/v1/admin/moderation/reports/{id}/resolve`
- [x] POST `/v1/admin/moderation/reports/{id}/dismiss`

**Total Endpoints:** 71 endpoints

---

## Test Data Guidelines

### Phone Numbers
- Use Iranian phone format: `09123456789` or `+989123456789`
- Use different numbers for different test scenarios
- Example: `09121234567`, `09129876543`

### OTP Codes
- Format: 6-digit numeric codes
- Examples: `123456`, `654321`, `000000` (for testing)

### Prompts (AI Generation)
- Image: "A beautiful sunset over mountains with vibrant colors"
- Video: "A cat playing with a ball of yarn in slow motion"
- Audio: "Relaxing ambient music with nature sounds"

### Model IDs
- Will be dynamic based on database seeding
- Use realistic IDs: `1`, `2`, `3`, etc.
- Document which IDs correspond to which model types

### Token Bundle IDs
- Standard bundles: `1` (100 tokens), `2` (500 tokens), `3` (1000 tokens), `4` (2000 tokens)
- Actual IDs depend on database seeding

### File Uploads
- Avatar: Small image files (JPEG/PNG)
- Use realistic file sizes (max 5MB typically)

---

## Collection Features

### Authentication Flow
1. User requests OTP via `/v1/auth/request-otp`
2. System returns `request_id` (UUID)
3. User verifies OTP via `/v1/auth/verify-otp` with `request_id` and `code`
4. System returns `token` (Bearer token)
5. Token is automatically set in collection variables for subsequent requests

### Environment Setup
- Create Postman environment with `base_url` variable
- Set `base_url` to your API endpoint (local or production)
- Token is auto-populated after OTP verification

### Request Organization
- All requests organized in logical folders
- Numbered folders for easy navigation
- Clear naming conventions
- Descriptions where needed

### Test Data Quality
- Realistic test data for all endpoints
- Proper data types and formats
- Edge cases covered where relevant
- Iranian market context (phone numbers, currency)

---

## Deliverables

1. ✅ **POSTMAN_COLLECTION_TASKLIST.md** - This task list document
2. ⏳ **negarify-api.postman_collection.json** - Complete Postman collection file
3. 📋 **POSTMAN_USAGE_GUIDE.md** - Optional usage guide (future)

---

## Notes

- Postman collection schema version: 2.1 (latest stable)
- All endpoints follow RESTful conventions
- Bearer token authentication using Laravel Sanctum
- Environment variables for flexible configuration
- Test data designed for Iranian market (Toman currency, phone numbers)
- Collection is ready for import into Postman application

---

**Status:** Task 4 in progress - Creating Postman collection JSON file

