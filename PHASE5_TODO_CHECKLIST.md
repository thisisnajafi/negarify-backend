# Phase 5: Gallery & Feed System - TODO Checklist

## Task 5.1: Gallery Post Management

### Subtask 5.1.1: Create GalleryPostController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GalleryPostController.php` (new file)

**Routes & Middleware:**
- Route: `/api/v1/gallery/post` (POST) - `auth:sanctum`
- Route: `/api/v1/gallery/posts/{id}` (GET, PUT, DELETE) - `auth:sanctum`
- Route: `/api/v1/gallery/my-posts` (GET) - `auth:sanctum`

**DB Reads/Writes:**
- Read: `generation_jobs` (validate ownership, check completion)
- Write: `gallery_posts` (INSERT, UPDATE, DELETE)
- Read: `gallery_posts` (list user's posts, get post details)

**Indexes Used:**
- `gallery_posts.user_id` (for user's posts listing)
- `gallery_posts.generation_job_id` (unique constraint)
- `gallery_posts.visibility` (for filtering)

**Caching Decisions:**
- None (user-specific data, not cacheable)

**Authorization Rules:**
- Only owner can create post (generation_job must belong to user)
- Only owner can update/delete their posts
- Owner can view their own private posts
- Others cannot view private posts

**Acceptance Criteria:**
- Controller exists with all CRUD methods
- Authorization enforced (owner-only updates/deletes)
- Generation job ownership validated on creation

**Failure Cases & Security Pitfalls:**
- User tries to create post for other user's generation job → 403 Forbidden
- User tries to update/delete other user's post → 403 Forbidden
- User tries to view other user's private post → 404 Not Found
- Duplicate post creation → 400 Bad Request (unique constraint violation)

---

### Subtask 5.1.2: Implement store() - Create Post
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GalleryPostController.php` (store method)
- `app/Http/Requests/Api/V1/StoreGalleryPostRequest.php` (new - validation)

**Routes & Middleware:**
- POST `/api/v1/gallery/post` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `generation_jobs` (validate ownership, check completion, check not already posted)
- Write: `gallery_posts` (INSERT)

**Indexes Used:**
- `generation_jobs.user_id` (for ownership check)
- `gallery_posts.generation_job_id` (unique constraint)

**Caching Decisions:**
- None

**Authorization Rules:**
- Generation job must belong to current user
- Generation job must be completed (status = 'completed')
- Generation job must not already have a gallery post (unique constraint)

**Acceptance Criteria:**
- Post created successfully
- Generation job ownership validated
- Duplicate post prevented
- Tags validated and processed
- Visibility settings applied
- Prompt/model visibility flags set

**Failure Cases & Security Pitfalls:**
- Invalid generation_job_id → 404 Not Found
- Generation job belongs to another user → 403 Forbidden
- Generation job not completed → 400 Bad Request
- Duplicate post → 400 Bad Request
- Invalid tags format → 422 Validation Error

---

### Subtask 5.1.3: Implement show() - Get Post Details
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GalleryPostController.php` (show method)

**Routes & Middleware:**
- GET `/api/v1/gallery/posts/{id}` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (with eager loading: user, generationJob, generationJob.model)

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)

**Caching Decisions:**
- None (user-specific visibility checks)

**Authorization Rules:**
- Owner can view their own posts (public or private)
- Others can only view public posts
- Private posts return 404 to non-owners

**Acceptance Criteria:**
- Post details returned correctly
- Prompt included only if `prompt_visible = true`
- Model included only if `model_visible = true`
- Private posts hidden from non-owners
- Eager loading prevents N+1 queries

**Failure Cases & Security Pitfalls:**
- Post not found → 404 Not Found
- Private post accessed by non-owner → 404 Not Found (don't reveal existence)
- Prompt/model leaked when visibility flags false → Security breach

---

### Subtask 5.1.4: Implement update() - Update Post
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GalleryPostController.php` (update method)
- `app/Http/Requests/Api/V1/UpdateGalleryPostRequest.php` (new - validation)

**Routes & Middleware:**
- PUT `/api/v1/gallery/posts/{id}` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (get post, check ownership)
- Write: `gallery_posts` (UPDATE)

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)
- `gallery_posts.user_id` (ownership check)

**Caching Decisions:**
- Invalidate feed cache if visibility or curation status changes

**Authorization Rules:**
- Only owner can update their posts
- Cannot change generation_job_id (immutable)
- Cannot change is_curated (admin-only)

**Acceptance Criteria:**
- Post updated successfully
- Ownership enforced
- Tags validated and processed
- Visibility settings updated
- Prompt/model visibility flags updated
- Immutable fields protected

**Failure Cases & Security Pitfalls:**
- Post not found → 404 Not Found
- User tries to update other user's post → 403 Forbidden
- User tries to change curation status → 403 Forbidden (admin-only)

---

### Subtask 5.1.5: Implement destroy() - Delete Post
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GalleryPostController.php` (destroy method)

**Routes & Middleware:**
- DELETE `/api/v1/gallery/posts/{id}` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (get post, check ownership)
- Write: `gallery_posts` (DELETE - cascade deletes likes/comments)

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)

**Caching Decisions:**
- Invalidate feed cache if post was curated

**Authorization Rules:**
- Only owner can delete their posts

**Acceptance Criteria:**
- Post deleted successfully
- Ownership enforced
- Cascade deletes likes and comments
- Feed cache invalidated if post was curated

**Failure Cases & Security Pitfalls:**
- Post not found → 404 Not Found
- User tries to delete other user's post → 403 Forbidden

---

### Subtask 5.1.6: Add Tag Validation and Processing
**Files to Create/Modify:**
- `app/Http/Requests/Api/V1/StoreGalleryPostRequest.php` (tag validation)
- `app/Http/Requests/Api/V1/UpdateGalleryPostRequest.php` (tag validation)
- `app/Services/GalleryPostService.php` (new - optional service for tag processing)

**Routes & Middleware:**
- N/A (validation only)

**DB Reads/Writes:**
- None (processing only)

**Indexes Used:**
- None

**Caching Decisions:**
- None

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Tags validated: Array of strings, max 10 tags, each tag max 50 characters
- Tags sanitized: Trim whitespace, remove duplicates
- Tags stored as JSON array in database

**Failure Cases & Security Pitfalls:**
- Invalid tags format → 422 Validation Error
- Too many tags → 422 Validation Error
- Tag too long → 422 Validation Error

---

### Subtask 5.1.7: Implement Visibility Settings
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GalleryPostController.php` (visibility handling in all methods)
- `app/Http/Requests/Api/V1/StoreGalleryPostRequest.php` (visibility validation)
- `app/Http/Requests/Api/V1/UpdateGalleryPostRequest.php` (visibility validation)

**Routes & Middleware:**
- N/A (handled in existing endpoints)

**DB Reads/Writes:**
- Write: `gallery_posts` (UPDATE visibility field)

**Indexes Used:**
- `gallery_posts.visibility` (for filtering)

**Caching Decisions:**
- Invalidate feed cache when visibility changes (public → private removes from feed)

**Authorization Rules:**
- User can set their own post visibility
- Cannot change visibility of curated posts (or document if allowed)

**Acceptance Criteria:**
- Visibility settings saved correctly
- Private posts hidden from non-owners
- Public posts visible to all
- Feed cache invalidated on visibility change

**Failure Cases & Security Pitfalls:**
- Private posts leaked in public endpoints → Security breach
- Visibility change not reflected in feed → Data inconsistency

---

### Subtask 5.1.8: Add Prompt/Model Visibility Flags
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GalleryPostController.php` (visibility flag handling)
- `app/Http/Requests/Api/V1/StoreGalleryPostRequest.php` (flag validation)
- `app/Http/Requests/Api/V1/UpdateGalleryPostRequest.php` (flag validation)

**Routes & Middleware:**
- N/A (handled in existing endpoints)

**DB Reads/Writes:**
- Write: `gallery_posts` (UPDATE prompt_visible, model_visible fields)

**Indexes Used:**
- None (flags not indexed)

**Caching Decisions:**
- None (flags affect response shape, not cacheability)

**Authorization Rules:**
- User can set their own post visibility flags

**Acceptance Criteria:**
- Prompt/model visibility flags saved correctly
- Prompt hidden from responses when `prompt_visible = false`
- Model hidden from responses when `model_visible = false`
- Flags respected in all endpoints (show, list, feed)

**Failure Cases & Security Pitfalls:**
- Prompt/model leaked when flags false → Security breach
- Flags not checked in all endpoints → Inconsistent behavior

---

### Subtask 5.1.9: Create User's Posts Listing Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/GalleryPostController.php` (myPosts method)
- `app/Http/Requests/Api/V1/GalleryPostListRequest.php` (new - optional, for filters)

**Routes & Middleware:**
- GET `/api/v1/gallery/my-posts` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (WHERE user_id = current_user.id, with eager loading)

**Indexes Used:**
- `gallery_posts.user_id` (for user's posts)
- `gallery_posts.created_at` (for ordering)

**Caching Decisions:**
- None (user-specific data)

**Authorization Rules:**
- User can only see their own posts (public and private)

**Acceptance Criteria:**
- User's posts listed correctly
- Pagination works (15 per page)
- Ordered by created_at DESC
- Eager loading prevents N+1 queries
- Prompt/model visibility respected

**Failure Cases & Security Pitfalls:**
- N+1 queries → Performance issue
- Other users' posts leaked → Security breach

**Test Coverage Expectations:**
- Feature test: Create post (validates generation job ownership)
- Feature test: Update post (only owner can update)
- Feature test: Delete post (only owner can delete)
- Feature test: View post (private posts hidden from non-owners)
- Feature test: Prompt/model visibility (hidden when flags false)
- Feature test: Tag validation (max 10 tags, max 50 chars each)

---

## Task 5.2: Admin Feed Curation

### Subtask 5.2.1: Create Admin Endpoints for Curation
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminGalleryController.php` (new file)

**Routes & Middleware:**
- POST `/api/v1/admin/gallery/{id}/curate` - `auth:sanctum`, `admin` middleware
- POST `/api/v1/admin/gallery/{id}/uncurate` - `auth:sanctum`, `admin` middleware
- POST `/api/v1/admin/gallery/{id}/feature` - `auth:sanctum`, `admin` middleware (optional)
- GET `/api/v1/admin/gallery/curated` - `auth:sanctum`, `admin` middleware

**DB Reads/Writes:**
- Read: `gallery_posts` (get post to curate)
- Write: `gallery_posts` (UPDATE is_curated, curated_at, is_featured)

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)
- `gallery_posts.is_curated` (for curated listing)

**Caching Decisions:**
- Invalidate feed cache when post is curated/uncurated

**Authorization Rules:**
- Only admins can curate/uncurate posts
- Admin middleware must enforce strict authorization

**Acceptance Criteria:**
- Admin endpoints exist
- Admin middleware enforced
- Non-admin cannot access endpoints

**Failure Cases & Security Pitfalls:**
- Non-admin tries to curate → 403 Forbidden
- Admin middleware not enforced → Security breach

---

### Subtask 5.2.2: Implement curate() - Mark Post as Curated
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminGalleryController.php` (curate method)

**Routes & Middleware:**
- POST `/api/v1/admin/gallery/{id}/curate` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `gallery_posts` (get post)
- Write: `gallery_posts` (UPDATE is_curated = true, curated_at = now())

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)

**Caching Decisions:**
- Invalidate feed cache: `Cache::forget('feed:curated:*')`

**Authorization Rules:**
- Only admins can curate
- Post must exist
- Post must be public (or document if private posts can be curated)

**Acceptance Criteria:**
- Post marked as curated
- `is_curated = true` set
- `curated_at` timestamp set
- Feed cache invalidated
- Post appears in feed (if public)

**Failure Cases & Security Pitfalls:**
- Post not found → 404 Not Found
- Non-admin tries to curate → 403 Forbidden
- Private post curated → Document behavior (should it be allowed?)

---

### Subtask 5.2.3: Implement uncurate() - Remove from Feed
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminGalleryController.php` (uncurate method)

**Routes & Middleware:**
- POST `/api/v1/admin/gallery/{id}/uncurate` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `gallery_posts` (get post)
- Write: `gallery_posts` (UPDATE is_curated = false, curated_at = null)

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)

**Caching Decisions:**
- Invalidate feed cache

**Authorization Rules:**
- Only admins can uncurate

**Acceptance Criteria:**
- Post uncurated successfully
- `is_curated = false` set
- `curated_at` set to null (or keep for history?)
- Feed cache invalidated
- Post removed from feed

**Failure Cases & Security Pitfalls:**
- Post not found → 404 Not Found
- Non-admin tries to uncurate → 403 Forbidden

---

### Subtask 5.2.4: Implement feature() - Feature a Post
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminGalleryController.php` (feature method, optional)

**Routes & Middleware:**
- POST `/api/v1/admin/gallery/{id}/feature` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Write: `gallery_posts` (UPDATE is_featured = true)

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)
- `gallery_posts.is_featured` (for feed ordering)

**Caching Decisions:**
- Invalidate feed cache (featured posts appear first)

**Authorization Rules:**
- Only admins can feature posts

**Acceptance Criteria:**
- Post featured successfully
- `is_featured = true` set
- Feed cache invalidated
- Featured posts appear first in feed

**Failure Cases & Security Pitfalls:**
- Post not found → 404 Not Found
- Non-admin tries to feature → 403 Forbidden

---

### Subtask 5.2.5: Add Bulk Curation Actions
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminGalleryController.php` (bulkCurate, bulkUncurate methods)
- `app/Http/Requests/Api/V1/BulkCurationRequest.php` (new - validation)

**Routes & Middleware:**
- POST `/api/v1/admin/gallery/bulk-curate` - `auth:sanctum`, `admin`
- POST `/api/v1/admin/gallery/bulk-uncurate` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Write: `gallery_posts` (UPDATE multiple posts in transaction)

**Indexes Used:**
- `gallery_posts.id` (for WHERE IN clause)

**Caching Decisions:**
- Invalidate feed cache after bulk operations

**Authorization Rules:**
- Only admins can bulk curate

**Acceptance Criteria:**
- Multiple posts curated/uncurated in single operation
- Transaction ensures atomicity
- Feed cache invalidated
- Validation prevents invalid post IDs

**Failure Cases & Security Pitfalls:**
- Invalid post IDs → 422 Validation Error
- Non-admin tries bulk action → 403 Forbidden
- Partial failure → Transaction rollback

---

### Subtask 5.2.6: Track Curation Timestamp
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminGalleryController.php` (curated_at handling)
- Already in database schema (curated_at field exists)

**Routes & Middleware:**
- N/A (handled in curate method)

**DB Reads/Writes:**
- Write: `gallery_posts` (UPDATE curated_at)

**Indexes Used:**
- `gallery_posts.curated_at` (for feed ordering)

**Caching Decisions:**
- None

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- `curated_at` set when post is curated
- `curated_at` cleared when post is uncurated (or kept for history)
- Timestamp used for feed ordering

**Failure Cases & Security Pitfalls:**
- Timestamp not set → Data inconsistency

---

### Subtask 5.2.7: Create Curated Posts Listing for Admin
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/AdminGalleryController.php` (curated method)

**Routes & Middleware:**
- GET `/api/v1/admin/gallery/curated` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `gallery_posts` (WHERE is_curated = true, with eager loading)

**Indexes Used:**
- `gallery_posts.is_curated` (for filtering)
- `gallery_posts.curated_at` (for ordering)

**Caching Decisions:**
- None (admin-specific, low traffic)

**Authorization Rules:**
- Only admins can view curated listing

**Acceptance Criteria:**
- Curated posts listed correctly
- Pagination works
- Ordered by curated_at DESC
- Eager loading prevents N+1 queries

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- N+1 queries → Performance issue

**Test Coverage Expectations:**
- Feature test: Admin can curate posts
- Feature test: Non-admin cannot curate
- Feature test: Curation sets curated_at timestamp
- Feature test: Uncuration removes from feed
- Feature test: Bulk curation works

---

## Task 5.3: Public Feed with View Limits

### Subtask 5.3.1: Create FeedController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/FeedController.php` (new file)

**Routes & Middleware:**
- GET `/api/v1/gallery/feed` - `auth:sanctum`
- POST `/api/v1/gallery/feed/copy-prompt` - `auth:sanctum`
- POST `/api/v1/gallery/feed/copy-model` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (curated, public, images/videos only)
- Read: `feed_view_limits` (get user's view limits)
- Write: `feed_view_limits` (decrement views_remaining)

**Indexes Used:**
- `gallery_posts.is_curated` (for filtering)
- `gallery_posts.visibility` (for filtering)
- `gallery_posts.curated_at` (for ordering)
- `feed_view_limits.user_id` (for user's limits)
- `feed_view_limits.content_type` (for type-specific limits)

**Caching Decisions:**
- Cache feed query results in Redis (5-10 minute TTL)
- Cache key: `feed:curated:page:{page}` or `feed:curated:cursor:{cursor}`
- Cache invalidation: On curation changes

**Authorization Rules:**
- All authenticated users can view feed (subject to view limits)
- Admins bypass view limits

**Acceptance Criteria:**
- Controller exists with feed endpoints
- View limits enforced
- Feed returns curated content only

**Failure Cases & Security Pitfalls:**
- Non-curated posts in feed → Data inconsistency
- Private posts in feed → Security breach
- Audio posts in feed → Business logic violation

---

### Subtask 5.3.2: Implement index() - Get Curated Feed
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/FeedController.php` (index method)

**Routes & Middleware:**
- GET `/api/v1/gallery/feed` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (curated, public, images/videos, with eager loading)
- Read: `feed_view_limits` (get user's limits)
- Write: `feed_view_limits` (decrement views atomically)

**Indexes Used:**
- All indexes from previous subtask

**Caching Decisions:**
- Cache feed query (but check view limits after cache hit)
- Cache key includes pagination cursor/page

**Authorization Rules:**
- All authenticated users can view feed
- Admins bypass view limits

**Acceptance Criteria:**
- Feed returns curated public posts only
- Feed excludes audio posts (only images/videos)
- View limits decremented correctly
- Views_remaining returned in response
- Feed ordered by featured first, then curated_at DESC
- Eager loading prevents N+1 queries
- Cursor-based pagination works

**Failure Cases & Security Pitfalls:**
- Non-curated posts in feed → Data inconsistency
- Private posts in feed → Security breach
- Audio posts in feed → Business logic violation
- View limits not decremented → Data inconsistency
- N+1 queries → Performance issue

---

### Subtask 5.3.3: Filter to Show Only Curated Images/Videos
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/FeedController.php` (filtering logic)
- `app/Models/GalleryPost.php` (add scopeForFeed if needed)

**Routes & Middleware:**
- N/A (handled in index method)

**DB Reads/Writes:**
- Read: `gallery_posts` (with WHERE clauses)
- Read: `generation_jobs` (join to filter by job_type)

**Indexes Used:**
- `gallery_posts.is_curated`
- `gallery_posts.visibility`
- `generation_jobs.job_type`

**Caching Decisions:**
- None (filtering is part of query)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Feed only includes posts where `is_curated = true`
- Feed only includes posts where `visibility = 'public'`
- Feed only includes posts where `generation_job.job_type IN ('image', 'video')`
- Audio posts excluded

**Failure Cases & Security Pitfalls:**
- Audio posts in feed → Business logic violation
- Non-curated posts in feed → Data inconsistency

---

### Subtask 5.3.4: Implement View Limit Tracking
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/FeedController.php` (view limit logic)
- `app/Services/FeedViewLimitService.php` (new - optional service)

**Routes & Middleware:**
- N/A (handled in index method)

**DB Reads/Writes:**
- Read: `feed_view_limits` (get user's limits)
- Write: `feed_view_limits` (decrement views_remaining atomically)
- Write: `feed_view_limits` (create record if not exists)

**Indexes Used:**
- `feed_view_limits.user_id`
- `feed_view_limits.content_type`

**Caching Decisions:**
- None (user-specific, changes frequently)

**Authorization Rules:**
- Admins bypass view limits (check `user->isAdmin()`)

**Acceptance Criteria:**
- View limits checked before returning feed
- Views decremented atomically (one per item viewed)
- Views_remaining returned in response
- Admins bypass view limits
- Feed stops returning items when views_remaining = 0

**Failure Cases & Security Pitfalls:**
- View limits not decremented → Data inconsistency
- Race conditions on decrement → Double-counting views
- Admins consume view limits → Business logic violation

---

### Subtask 5.3.5: Create Daily Limit Reset Job
**Files to Create/Modify:**
- `app/Console/Commands/ResetFeedViewLimits.php` (new file)
- `routes/console.php` (schedule job)

**Routes & Middleware:**
- N/A (scheduled job)

**DB Reads/Writes:**
- Write: `feed_view_limits` (UPDATE views_remaining = daily_limit, reset_at = now())

**Indexes Used:**
- `feed_view_limits.reset_at` (for finding records to reset)

**Caching Decisions:**
- None

**Authorization Rules:**
- N/A (system job)

**Acceptance Criteria:**
- Job runs daily at midnight
- All users' view limits reset to daily_limit
- reset_at timestamp updated
- Job logs success/failure

**Failure Cases & Security Pitfalls:**
- Job fails → View limits not reset (manual intervention needed)
- Partial reset → Data inconsistency

---

### Subtask 5.3.6: Add Cursor-Based Pagination
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/FeedController.php` (pagination logic)
- `app/Http/Requests/Api/V1/FeedListRequest.php` (new - validation)

**Routes & Middleware:**
- N/A (handled in index method)

**DB Reads/Writes:**
- Read: `gallery_posts` (with cursor-based WHERE clause)

**Indexes Used:**
- `gallery_posts.created_at` (for cursor ordering)
- `gallery_posts.id` (for cursor fallback)

**Caching Decisions:**
- Cache key includes cursor value

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Cursor-based pagination works correctly
- Cursor based on `created_at` or `id`
- Next cursor returned in response
- Stable ordering (no duplicates, no skipped items)

**Failure Cases & Security Pitfalls:**
- Unstable ordering → Duplicates or skipped items
- Invalid cursor → 400 Bad Request

---

### Subtask 5.3.7: Implement "Copy Prompt" Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/FeedController.php` (copyPrompt method)
- `app/Http/Requests/Api/V1/CopyPromptRequest.php` (new - validation)

**Routes & Middleware:**
- POST `/api/v1/gallery/feed/copy-prompt` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (get post, check visibility flags)
- Read: `generation_jobs` (get prompt)

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)

**Caching Decisions:**
- None

**Authorization Rules:**
- Post must be in feed (curated and public)
- Post must have `prompt_visible = true`

**Acceptance Criteria:**
- Prompt returned if visible
- Error returned if prompt_visible = false
- Error returned if post not in feed
- Post must be curated and public

**Failure Cases & Security Pitfalls:**
- Prompt leaked when visibility false → Security breach
- Private post prompt copied → Security breach
- Non-curated post prompt copied → Business logic violation

---

### Subtask 5.3.8: Implement "Copy Model" Endpoint
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/FeedController.php` (copyModel method)
- `app/Http/Requests/Api/V1/CopyModelRequest.php` (new - validation)

**Routes & Middleware:**
- POST `/api/v1/gallery/feed/copy-model` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (get post, check visibility flags)
- Read: `generation_jobs` (get model)

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)

**Caching Decisions:**
- None

**Authorization Rules:**
- Post must be in feed (curated and public)
- Post must have `model_visible = true`

**Acceptance Criteria:**
- Model returned if visible
- Error returned if model_visible = false
- Error returned if post not in feed

**Failure Cases & Security Pitfalls:**
- Model leaked when visibility false → Security breach
- Private post model copied → Security breach

---

### Subtask 5.3.9: Cache Feed in Redis
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/FeedController.php` (caching logic)

**Routes & Middleware:**
- N/A (handled in index method)

**DB Reads/Writes:**
- Read: Redis cache (get cached feed)
- Write: Redis cache (store cached feed)

**Indexes Used:**
- None (caching layer)

**Caching Decisions:**
- Cache feed query results (5-10 minute TTL)
- Cache key: `feed:curated:page:{page}` or `feed:curated:cursor:{cursor}`
- Cache invalidation: On curation changes

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Feed cached in Redis
- Cache hit returns cached data
- Cache miss queries database
- Cache invalidated on curation changes
- View limits still checked after cache hit

**Failure Cases & Security Pitfalls:**
- Cache not invalidated → Stale data
- View limits bypassed by cache → Business logic violation

---

### Subtask 5.3.10: Return Views Remaining in Response
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/FeedController.php` (response formatting)

**Routes & Middleware:**
- N/A (handled in index method)

**DB Reads/Writes:**
- Read: `feed_view_limits` (get views_remaining)

**Indexes Used:**
- `feed_view_limits.user_id`
- `feed_view_limits.content_type`

**Caching Decisions:**
- None (user-specific data)

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Views_remaining returned in feed response
- Separate counts for images and videos
- Admins see unlimited or null

**Failure Cases & Security Pitfalls:**
- Views_remaining not accurate → User confusion

**Test Coverage Expectations:**
- Feature test: Feed returns curated posts only
- Feature test: Feed excludes audio posts
- Feature test: View limits decrement correctly
- Feature test: View limits reset daily
- Feature test: Admins bypass view limits
- Feature test: Copy prompt respects visibility
- Feature test: Copy model respects visibility
- Feature test: Feed pagination works

---

## Task 5.4: Social Features (Likes & Comments)

### Subtask 5.4.1: Create LikeController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/LikeController.php` (new file)

**Routes & Middleware:**
- POST `/api/v1/gallery/{id}/like` - `auth:sanctum`
- DELETE `/api/v1/gallery/{id}/like` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (get post)
- Write: `likes` (INSERT, DELETE)
- Write: `gallery_posts` (UPDATE likes_count)

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)
- `likes.user_id` and `gallery_post_id` (unique constraint)

**Caching Decisions:**
- None (counters updated in database)

**Authorization Rules:**
- All authenticated users can like posts
- Users can only unlike their own likes

**Acceptance Criteria:**
- Controller exists with like/unlike methods
- Idempotent like operations
- Safe unlike operations

**Failure Cases & Security Pitfalls:**
- Duplicate likes → Unique constraint violation (handled gracefully)
- Unlike without like → Safe (check exists first)

---

### Subtask 5.4.2: Implement Like/Unlike Endpoints
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/LikeController.php` (store, destroy methods)

**Routes & Middleware:**
- POST `/api/v1/gallery/{id}/like` - `auth:sanctum`
- DELETE `/api/v1/gallery/{id}/like` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (get post)
- Read: `likes` (check if like exists)
- Write: `likes` (INSERT on like, DELETE on unlike)
- Write: `gallery_posts` (UPDATE likes_count atomically)

**Indexes Used:**
- `gallery_posts.id`
- `likes.user_id` and `gallery_post_id` (unique constraint)

**Caching Decisions:**
- None

**Authorization Rules:**
- All authenticated users can like posts
- Users can only unlike their own likes

**Acceptance Criteria:**
- Like creates like record and increments counter (atomic)
- Unlike deletes like record and decrements counter (atomic)
- Idempotent: Liking twice = same result (no error, no double increment)
- Safe: Unliking without like = no error (check exists first)
- Counter updated correctly

**Failure Cases & Security Pitfalls:**
- Race condition on counter update → Double increment/decrement
- Counter out of sync → Data inconsistency

---

### Subtask 5.4.3: Update likes_count on Posts
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/LikeController.php` (counter update logic)

**Routes & Middleware:**
- N/A (handled in like/unlike methods)

**DB Reads/Writes:**
- Write: `gallery_posts` (UPDATE likes_count atomically)

**Indexes Used:**
- `gallery_posts.id` (for UPDATE)

**Caching Decisions:**
- None

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- likes_count incremented on like (atomic)
- likes_count decremented on unlike (atomic)
- Counter matches COUNT(likes) (reconciliation possible)

**Failure Cases & Security Pitfalls:**
- Counter not updated → Data inconsistency
- Race condition → Double increment/decrement

---

### Subtask 5.4.4: Create CommentController
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/CommentController.php` (new file)

**Routes & Middleware:**
- POST `/api/v1/gallery/{id}/comment` - `auth:sanctum`
- DELETE `/api/v1/gallery/comments/{id}` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (get post)
- Write: `comments` (INSERT, DELETE)
- Write: `gallery_posts` (UPDATE comments_count)

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)
- `comments.gallery_post_id` (for post's comments)
- `comments.parent_id` (for nested replies)

**Caching Decisions:**
- None

**Authorization Rules:**
- All authenticated users can create comments
- Only owner or admin can delete comments

**Acceptance Criteria:**
- Controller exists with create/delete methods
- Nested comments (replies) supported
- Authorization enforced

**Failure Cases & Security Pitfalls:**
- User deletes other user's comment → 403 Forbidden
- Invalid parent_id → 422 Validation Error

---

### Subtask 5.4.5: Implement Comment Creation
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/CommentController.php` (store method)
- `app/Http/Requests/Api/V1/StoreCommentRequest.php` (new - validation)

**Routes & Middleware:**
- POST `/api/v1/gallery/{id}/comment` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `gallery_posts` (get post)
- Read: `comments` (validate parent_id if reply)
- Write: `comments` (INSERT)
- Write: `gallery_posts` (UPDATE comments_count atomically)

**Indexes Used:**
- `gallery_posts.id`
- `comments.parent_id` (for validating parent comment)

**Caching Decisions:**
- None

**Authorization Rules:**
- All authenticated users can create comments
- Parent comment must exist if parent_id provided

**Acceptance Criteria:**
- Comment created successfully
- Nested comments (replies) supported via parent_id
- Comment body validated (length, content)
- comments_count incremented (atomic)
- Comment returned in response

**Failure Cases & Security Pitfalls:**
- Invalid parent_id → 422 Validation Error
- Comment too long → 422 Validation Error
- Spam comments → Consider rate limiting

---

### Subtask 5.4.6: Implement Nested Comments (Replies)
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/CommentController.php` (parent_id handling)
- `app/Http/Requests/Api/V1/StoreCommentRequest.php` (parent_id validation)

**Routes & Middleware:**
- N/A (handled in comment creation)

**DB Reads/Writes:**
- Read: `comments` (validate parent comment exists)
- Write: `comments` (INSERT with parent_id)

**Indexes Used:**
- `comments.parent_id` (for validating parent)

**Caching Decisions:**
- None

**Authorization Rules:**
- Parent comment must exist
- Parent comment must belong to same post

**Acceptance Criteria:**
- Replies created with parent_id
- Parent comment validated
- Nested structure maintained
- Replies included in comment responses

**Failure Cases & Security Pitfalls:**
- Invalid parent_id → 422 Validation Error
- Parent comment from different post → 422 Validation Error

---

### Subtask 5.4.7: Implement Comment Deletion
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/CommentController.php` (destroy method)

**Routes & Middleware:**
- DELETE `/api/v1/gallery/comments/{id}` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `comments` (get comment, check ownership)
- Write: `comments` (DELETE - cascade deletes replies)
- Write: `gallery_posts` (UPDATE comments_count atomically)

**Indexes Used:**
- `comments.id` (primary key lookup)
- `comments.user_id` (ownership check)

**Caching Decisions:**
- None

**Authorization Rules:**
- Only owner or admin can delete comments
- Deleting parent comment deletes replies (cascade)

**Acceptance Criteria:**
- Comment deleted successfully
- Ownership enforced
- Replies deleted (cascade)
- comments_count decremented (atomic)

**Failure Cases & Security Pitfalls:**
- User deletes other user's comment → 403 Forbidden
- Counter not updated → Data inconsistency

---

### Subtask 5.4.8: Update comments_count on Posts
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/CommentController.php` (counter update logic)

**Routes & Middleware:**
- N/A (handled in create/delete methods)

**DB Reads/Writes:**
- Write: `gallery_posts` (UPDATE comments_count atomically)

**Indexes Used:**
- `gallery_posts.id` (for UPDATE)

**Caching Decisions:**
- None

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- comments_count incremented on create (atomic)
- comments_count decremented on delete (atomic, including replies)
- Counter matches COUNT(comments) (reconciliation possible)

**Failure Cases & Security Pitfalls:**
- Counter not updated → Data inconsistency
- Replies not counted in decrement → Data inconsistency

---

### Subtask 5.4.9: Create Notifications for Likes/Comments
**Files to Create/Modify:**
- Optional: Notification system (if required by docs)
- Skip if not explicitly required in Phase 5

**Routes & Middleware:**
- N/A (notifications are background jobs)

**DB Reads/Writes:**
- Write: `notifications` (if notification system exists)

**Indexes Used:**
- N/A

**Caching Decisions:**
- N/A

**Authorization Rules:**
- N/A

**Acceptance Criteria:**
- Notifications created when post is liked
- Notifications created when post is commented on
- Notifications sent to post owner

**Failure Cases & Security Pitfalls:**
- N/A (optional feature)

**Test Coverage Expectations:**
- Feature test: Like post (idempotent, counter updated)
- Feature test: Unlike post (safe, counter updated)
- Feature test: Create comment (nested replies supported)
- Feature test: Delete comment (only owner/admin can delete)
- Feature test: Counter accuracy (likes_count, comments_count)

---

## Summary

**Total Files to Create:** 20+
- 4 Controllers (GalleryPostController, AdminGalleryController, FeedController, LikeController, CommentController)
- 8 Form Requests (StoreGalleryPostRequest, UpdateGalleryPostRequest, GalleryPostListRequest, BulkCurationRequest, FeedListRequest, CopyPromptRequest, CopyModelRequest, StoreCommentRequest)
- 1 Optional Service (GalleryPostService, FeedViewLimitService)
- 1 Scheduled Job (ResetFeedViewLimits)

**Total Files to Modify:** 3
- routes/api.php (add gallery/feed routes)
- routes/console.php (add reset job)
- app/Models/GalleryPost.php (add helper methods if needed)

**Dependencies:**
- Environment variables: None new
- Packages: Redis (for caching)
- Infrastructure: Redis (for feed caching)

**Authorization Checklist:**
- [ ] Only owner can create/update/delete their posts
- [ ] Only admins can curate posts
- [ ] Private posts hidden from non-owners
- [ ] Prompt/model hidden when visibility flags false
- [ ] Only owner/admin can delete comments

**Security Checklist:**
- [ ] No private posts in feed
- [ ] No hidden prompts/models leaked
- [ ] No duplicate posts (unique constraint)
- [ ] No duplicate likes (unique constraint)
- [ ] View limits enforced correctly
- [ ] Counters updated atomically

**Test Coverage Minimum:**
- 15+ feature tests for gallery CRUD
- 10+ feature tests for admin curation
- 15+ feature tests for feed and view limits
- 10+ feature tests for likes and comments
- Test privacy enforcement
- Test authorization correctness
- Test counter accuracy

