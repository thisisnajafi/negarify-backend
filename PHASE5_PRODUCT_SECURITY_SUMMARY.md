# Phase 5: Gallery & Feed System - Product & Security Summary

## Product Understanding (Onboarding Guide)

### 1. Gallery vs Feed: What Each Is and Why
- **Gallery**: User's personal collection of published content (public or private posts)
  - Users publish their generated content (images/videos/audio) to their gallery
  - Gallery posts can be public (visible to others) or private (only owner sees)
  - Gallery is for personal organization and sharing with selected audiences
- **Feed**: Public-facing curated content stream (images/videos only, no audio)
  - Only admin-curated posts appear in the feed
  - Feed is for discovery and inspiration (browse high-quality content)
  - Feed has view limits to encourage token purchases
  - Users can copy prompts/models from feed to regenerate similar content

### 2. Privacy Model
- **Public/Private Posts**:
  - `visibility` field: 'public' (visible to all) or 'private' (only owner)
  - Private posts never appear in feed (even if curated)
  - Private posts only visible to owner in their gallery
  - Public posts visible to all users (but only curated ones in feed)
- **Prompt Visibility**:
  - `prompt_visible` boolean: Controls whether prompt is shown in post details
  - If false: Prompt hidden from API responses (even to owner viewing their own post)
  - Copy prompt endpoint must check this flag before returning prompt
  - Default: true (prompts visible by default)
- **Model Visibility**:
  - `model_visible` boolean: Controls whether model name is shown
  - If false: Model name hidden from API responses
  - Copy model endpoint must check this flag before returning model
  - Default: true (models visible by default)

### 3. Admin Curation Model
- **Curated vs Featured**:
  - `is_curated`: Boolean flag marking post as curated (appears in feed)
  - `is_featured`: Boolean flag for featured posts (can be used for prioritization)
  - `curated_at`: Timestamp when post was curated (for audit trail)
  - Only admins can curate/uncurate posts
  - Curation does not modify the underlying generation job
- **Who Can Curate**:
  - Only users with `role = 'admin'` can curate
  - Admin middleware must enforce this (strict authorization)
  - Curation is a one-way action (can be undone with uncurate)

### 4. View Limit Model
- **Daily Reset**:
  - View limits reset at midnight (configurable timezone)
  - Scheduled job runs daily to reset `views_remaining` to `daily_limit`
  - Each user has separate limits for images and videos
  - Limits tracked in `feed_view_limits` table (or similar structure)
- **Admin Unlimited Logic**:
  - Admins have unlimited views (bypass view limit checks)
  - Check `user->isAdmin()` before decrementing views
  - Admins can view feed without consuming view limits
- **What Counts as a "View"**:
  - View counted when user requests feed items (paginated)
  - Each item in feed response counts as one view
  - Views decremented before returning feed (atomic operation)
  - If views_remaining = 0, feed returns empty (or error message)
  - View limit is per content_type (image/video tracked separately)

### 5. Social Actions
- **Like/Unlike Idempotency**:
  - Like must be idempotent: Liking twice = same result (no double increment)
  - Database unique constraint on (user_id, gallery_post_id) prevents duplicates
  - Unlike must be safe: Unliking without like should not break (check exists first)
  - Counters updated atomically with like/unlike operations
- **Comment Creation and Deletion Constraints**:
  - Comments can be created by any authenticated user
  - Comments can be deleted by owner or admin
  - Nested comments (replies) supported via `parent_id` field
  - Comment deletion must update `comments_count` on post
  - Comments are immutable (no edit functionality in Phase 5)

### 6. Performance Risks
- **N+1 Queries**:
  - Feed query must eager load: user, generationJob, generationJob.model
  - Gallery listing must eager load: user, generationJob, likes, comments
  - Use `with()` to eager load relationships
  - Avoid loading unnecessary relationships (e.g., don't load all comments in feed)
- **Pagination Correctness**:
  - Feed uses cursor-based pagination (for performance with large datasets)
  - Cursor should be based on `created_at` or `id` (stable ordering)
  - Gallery listing can use offset pagination (smaller dataset)
  - Pagination must respect filters (curated, visibility, etc.)
- **Counters Consistency**:
  - `likes_count`, `comments_count`, `views_count` must be accurate
  - Counters updated atomically with operations (database transactions)
  - Counters can be recalculated from relationships if needed (reconciliation)
  - Race conditions prevented with database locks or atomic operations

### 7. Authorization Rules
- **Gallery Post Ownership**:
  - Only owner can update/delete their posts
  - Only owner can view private posts
  - Generation job must belong to user (enforced on creation)
- **Feed Access**:
  - All authenticated users can view feed (subject to view limits)
  - Feed only shows curated public posts
  - Admins bypass view limits
- **Admin Actions**:
  - Only admins can curate/uncurate posts
  - Only admins can feature posts
  - Admin middleware must enforce strict authorization

### 8. Data Integrity
- **Unique Constraints**:
  - One gallery post per generation job (unique constraint on generation_job_id)
  - One like per user per post (unique constraint on user_id + gallery_post_id)
  - Prevents duplicate posts and duplicate likes
- **Foreign Key Constraints**:
  - Gallery post must reference valid generation job
  - Generation job must belong to user (enforced in controller)
  - Likes/comments must reference valid posts

### 9. Security Pitfalls to Avoid
- **Leaking Private Content**:
  - Never return private posts in public endpoints
  - Always check visibility before returning post details
  - Owner can see their own private posts, others cannot
- **Leaking Hidden Prompts/Models**:
  - Never return prompt if `prompt_visible = false`
  - Never return model if `model_visible = false`
  - Copy endpoints must check visibility flags
  - Even owner viewing their own post should respect visibility flags (or document if different)
- **Race Conditions on Counters**:
  - Use database transactions for counter updates
  - Use atomic operations (increment/decrement) where possible
  - Lock rows when updating counters to prevent race conditions
- **Abuse Vectors**:
  - Validate comment length and content (prevent spam)
  - Consider rate limiting for likes/comments (if documented)
  - Prevent self-likes if required (check if user is post owner)

### 10. Caching Strategy
- **Feed Caching**:
  - Cache curated feed in Redis (5-10 minute TTL)
  - Cache key: `feed:curated:{cursor}` or `feed:curated:page:{page}`
  - Cache invalidation: When post is curated/uncurated
  - Cache must still respect view limits (check limits after cache hit)
  - Never cache user-specific data (view limits are per-user)
- **Counter Caching**:
  - Counters stored in database (source of truth)
  - Can cache counters for read-heavy endpoints (with short TTL)
  - Cache invalidation: When like/comment is created/deleted

### 11. Feed Content Filtering
- **Curated Only**:
  - Feed must only return posts where `is_curated = true`
  - Feed must only return posts where `visibility = 'public'`
  - Feed must exclude audio posts (only images/videos)
  - Filter by generation job type: `job_type IN ('image', 'video')`
- **Ordering**:
  - Featured posts first (if `is_featured = true`)
  - Then by `curated_at` DESC (newest curated first)
  - Or by `created_at` DESC (newest posts first)

### 12. Copy Functionality
- **Copy Prompt**:
  - Endpoint: POST /gallery/feed/copy-prompt
  - Returns prompt text if `prompt_visible = true`
  - Returns error if `prompt_visible = false`
  - Must check post is in feed (curated and public)
- **Copy Model**:
  - Endpoint: POST /gallery/feed/copy-model
  - Returns model name if `model_visible = true`
  - Returns error if `model_visible = false`
  - Must check post is in feed (curated and public)

### 13. View Limit Implementation
- **Table Structure** (if not exists, create migration):
  - `feed_view_limits` table: user_id, content_type, views_remaining, daily_limit, reset_at
  - Or track in user table: `image_views_remaining`, `video_views_remaining`, `last_view_reset`
- **Decrement Logic**:
  - Before returning feed, decrement views_remaining for each item viewed
  - Atomic operation: `UPDATE feed_view_limits SET views_remaining = views_remaining - 1 WHERE ...`
  - If views_remaining = 0, stop returning items (or return empty feed)
- **Daily Reset Job**:
  - Scheduled job runs at midnight
  - Resets `views_remaining = daily_limit` for all users
  - Updates `reset_at = now()`

### 14. Performance Optimization
- **Eager Loading**:
  - Feed: Load user, generationJob, generationJob.model in single query
  - Gallery: Load user, generationJob, likes (count), comments (count)
  - Avoid N+1 queries in all endpoints
- **Indexes**:
  - Index on `gallery_posts.is_curated` and `visibility` (for feed queries)
  - Index on `gallery_posts.user_id` and `visibility` (for user gallery)
  - Index on `gallery_posts.created_at` (for ordering)
  - Index on `likes.user_id` and `gallery_post_id` (for uniqueness check)
- **Query Optimization**:
  - Use `select()` to limit columns loaded
  - Use `whereHas()` efficiently (avoid subquery performance issues)
  - Consider materialized views for feed if needed (future optimization)

### 15. Error Handling
- **Authorization Errors**:
  - 403 Forbidden: User not authorized (e.g., non-admin trying to curate)
  - 404 Not Found: Post not found or user cannot access (private post)
- **Validation Errors**:
  - 422 Unprocessable Entity: Invalid input (tags, visibility, etc.)
- **Business Logic Errors**:
  - 400 Bad Request: View limit exceeded, duplicate post, etc.

### 16. Testing Considerations
- **Privacy Testing**:
  - Test private posts not visible to other users
  - Test prompt/model hidden when visibility flags false
  - Test owner can see their own private posts
- **Authorization Testing**:
  - Test only owner can update/delete posts
  - Test only admin can curate posts
  - Test non-admin cannot curate
- **View Limit Testing**:
  - Test view limits decrement correctly
  - Test view limits reset daily
  - Test admins bypass view limits
- **Counter Testing**:
  - Test counters update correctly on like/unlike
  - Test counters update correctly on comment create/delete
  - Test idempotent like operations

### 17. API Response Shapes
- **Gallery Post Response**:
  - Include: id, title, description, tags, visibility, prompt (if visible), model (if visible), result_url, thumbnail_url, likes_count, comments_count, views_count, user, created_at
  - Exclude: prompt if `prompt_visible = false`, model if `model_visible = false`
- **Feed Response**:
  - Include: Same as gallery post, plus views_remaining for user
  - Exclude: Private posts, non-curated posts, audio posts
- **Like Response**:
  - Include: Success message, updated likes_count
- **Comment Response**:
  - Include: Comment id, body, user, created_at, parent_id (if reply)

### 18. Database Transactions
- **Post Creation**:
  - Transaction ensures post and validation are atomic
- **Like/Unlike**:
  - Transaction ensures like creation/deletion and counter update are atomic
- **Comment Create/Delete**:
  - Transaction ensures comment creation/deletion and counter update are atomic
- **View Decrement**:
  - Transaction ensures view decrement is atomic (prevent double-counting)

### 19. Rate Limiting (If Required)
- **Like/Comment Rate Limiting**:
  - Consider rate limiting to prevent spam
  - Laravel rate limiter: `RateLimiter::tooManyAttempts()`
  - Limit: 10 likes per minute, 5 comments per minute (configurable)
  - Only if documented or necessary for abuse prevention

### 20. Feed Caching Invalidation
- **When to Invalidate**:
  - Post curated: Invalidate feed cache
  - Post uncurated: Invalidate feed cache
  - Post deleted: Invalidate feed cache
  - Post visibility changed: Invalidate feed cache (if public → private)
- **Cache Keys**:
  - `feed:curated:page:{page}` for paginated feed
  - `feed:curated:cursor:{cursor}` for cursor-based feed
  - Clear all feed cache on curation changes

### 21. Counter Reconciliation
- **Accuracy Checks**:
  - `likes_count` should equal `COUNT(likes WHERE gallery_post_id = X)`
  - `comments_count` should equal `COUNT(comments WHERE gallery_post_id = X)`
  - `views_count` can be approximate (tracked separately, not from relationships)
- **Recalculation**:
  - Admin command to recalculate counters: `php artisan gallery:recalculate-counters`
  - Useful for data integrity checks

### 22. Nested Comments (Replies)
- **Structure**:
  - Comments have `parent_id` field (nullable)
  - Top-level comments: `parent_id = null`
  - Replies: `parent_id = comment_id`
  - Replies can have replies (nested structure)
- **API Response**:
  - Return comments with replies nested
  - Or return flat list with `parent_id` (client builds tree)
  - Document which approach is used

### 23. Featured Posts
- **Behavior**:
  - `is_featured` flag for prioritization
  - Featured posts appear first in feed (before non-featured)
  - Ordering: Featured DESC, then curated_at DESC
  - Featured is separate from curated (post can be curated but not featured)

### 24. Tags Processing
- **Format**:
  - Tags stored as JSON array: `["tag1", "tag2", "tag3"]`
  - Validate: Array of strings, max 10 tags, each tag max 50 characters
  - Sanitize: Trim whitespace, lowercase (optional), remove duplicates
- **Search** (Future):
  - Tags can be used for filtering/searching posts
  - Index tags_json for JSON queries (PostgreSQL) or use separate tags table

### 25. Generation Job Ownership Validation
- **On Post Creation**:
  - Verify `generation_job.user_id = current_user.id`
  - Prevent users from creating posts for other users' generation jobs
  - Verify generation job exists and is completed
  - Prevent duplicate posts (unique constraint on generation_job_id)

---

## Critical Security Principles

1. **Never leak private content** - Always check visibility before returning posts
2. **Never leak hidden prompts/models** - Always check visibility flags before returning
3. **Always enforce ownership** - Only owner can update/delete their posts
4. **Always enforce admin-only actions** - Strict middleware for curation endpoints
5. **Always use transactions** - Counter updates must be atomic
6. **Always validate input** - Tags, visibility, etc. must be validated
7. **Always prevent duplicates** - Unique constraints prevent duplicate posts/likes
8. **Always respect view limits** - Decrement views, check limits, reset daily
9. **Always eager load relationships** - Prevent N+1 queries
10. **Always maintain counter consistency** - Counters must match relationships

