# Phase 8: Additional Features - TODO Checklist

## Task 8.1: Notification System

### Subtask 8.1.1: Verify Notification Model Exists
**Files to Check:**
- `app/Models/Notification.php` (already exists)
- `database/migrations/*_create_notifications_table.php` (already exists)

**Routes & Middleware:**
- All notification routes require `auth:sanctum`

**DB Reads/Writes:**
- Read: `notifications` (WHERE user_id = current_user.id)
- Write: `notifications` (INSERT on event)

**Indexes Used:**
- `notifications.user_id` (for user's notifications)
- `notifications.read_at` (for unread filtering)
- `notifications.created_at` (for ordering)

**Authorization Rules:**
- Users can only view their own notifications
- Notifications scoped by user_id

**Acceptance Criteria:**
- Notification model exists and works correctly
- Migration exists and table structure correct

**Failure Cases & Security Pitfalls:**
- User views other user's notifications → 403 Forbidden
- Notification data leaks sensitive info → Privacy breach

---

### Subtask 8.1.2: Create Notification Service
**Files to Create:**
- `app/Services/NotificationService.php` (new file)

**Routes & Middleware:**
- N/A (service class, not endpoint)

**DB Reads/Writes:**
- Write: `notifications` (INSERT notification)

**Indexes Used:**
- `notifications.user_id`
- `notifications.type`
- `notifications.created_at`

**Authorization Rules:**
- N/A (service class)

**Abuse Prevention Measures:**
- De-duplication: Prevent duplicate notifications (same event, same user, within 1 minute)
- Rate limiting: Max 100 notifications per user per hour (configurable)
- Batching: Similar notifications batched (e.g., "5 people liked your post")

**Acceptance Criteria:**
- Service exists with createNotification() method
- De-duplication works
- Rate limiting works (if implemented)
- Preferences checked before creating notification

**Failure Cases & Security Pitfalls:**
- Duplicate notifications → De-duplication prevents
- Notification spam → Rate limiting prevents
- Sensitive data in notification → Data sanitization prevents

---

### Subtask 8.1.3: Implement Notification Creation
**Files to Create/Modify:**
- `app/Services/NotificationService.php` (createNotification method)
- Integrate into existing controllers:
  - `app/Http/Controllers/Api/V1/LikeController.php` (on like)
  - `app/Http/Controllers/Api/V1/CommentController.php` (on comment)
  - `app/Jobs/GenerateImageJob.php` (on completion)
  - `app/Jobs/GenerateVideoJob.php` (on completion)
  - `app/Jobs/GenerateAudioJob.php` (on completion)
  - `app/Http/Controllers/Api/V1/AdminGalleryController.php` (on curation)

**Routes & Middleware:**
- N/A (integrated into existing endpoints)

**DB Reads/Writes:**
- Write: `notifications` (INSERT)
- Read: `notification_preferences` (if exists, check if enabled)

**Indexes Used:**
- All indexes from previous subtask

**Authorization Rules:**
- N/A (internal service call)

**Abuse Prevention Measures:**
- De-duplication
- Rate limiting
- Preferences check

**Acceptance Criteria:**
- Notifications created on like events
- Notifications created on comment events
- Notifications created on generation completion
- Notifications created on curation (if specified)
- De-duplication prevents duplicates
- Preferences respected

**Failure Cases & Security Pitfalls:**
- Notification creation fails → Log error, don't block main operation
- Sensitive data leaked → Data sanitization prevents
- Duplicate notifications → De-duplication prevents

---

### Subtask 8.1.4: Create Notification Endpoints
**Files to Create:**
- `app/Http/Controllers/Api/V1/NotificationController.php` (new file)
- `app/Http/Requests/Api/V1/NotificationListRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/notifications` - `auth:sanctum`
- GET `/api/v1/notifications/{id}` - `auth:sanctum`
- PUT `/api/v1/notifications/{id}/read` - `auth:sanctum`
- PUT `/api/v1/notifications/{id}/unread` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `notifications` (WHERE user_id = current_user.id, paginated)

**Indexes Used:**
- `notifications.user_id` (for filtering)
- `notifications.read_at` (for unread filtering)
- `notifications.created_at` (for ordering)

**Authorization Rules:**
- Users can only view their own notifications
- Users can only mark their own notifications as read/unread

**Abuse Prevention Measures:**
- Pagination (max 50 per page)
- Rate limiting (if needed)

**Acceptance Criteria:**
- List endpoint returns user's notifications (paginated)
- Show endpoint returns notification details
- Mark as read/unread works
- Notifications ordered by created_at DESC
- Unread count returned in response

**Failure Cases & Security Pitfalls:**
- User views other user's notification → 403 Forbidden
- Notification data leaks sensitive info → Privacy breach
- Unbounded query → Pagination prevents

**Test Coverage Expectations:**
- Feature test: User can list only their notifications
- Feature test: Mark read/unread works
- Feature test: Pagination works
- Feature test: Authorization enforced

---

### Subtask 8.1.5: Add Read/Unread Status
**Files to Create/Modify:**
- `app/Http/Controllers/Api/V1/NotificationController.php` (markAsRead, markAsUnread methods)
- `app/Models/Notification.php` (already has markAsRead method)

**Routes & Middleware:**
- PUT `/api/v1/notifications/{id}/read` - `auth:sanctum`
- PUT `/api/v1/notifications/{id}/unread` - `auth:sanctum`

**DB Reads/Writes:**
- Write: `notifications` (UPDATE read_at)

**Indexes Used:**
- `notifications.id` (primary key lookup)
- `notifications.user_id` (ownership check)

**Authorization Rules:**
- Users can only mark their own notifications as read/unread

**Abuse Prevention Measures:**
- Ownership check prevents marking other user's notifications

**Acceptance Criteria:**
- Mark as read sets read_at timestamp
- Mark as unread clears read_at (sets to null)
- Ownership enforced
- Idempotent (marking read twice = no error)

**Failure Cases & Security Pitfalls:**
- User marks other user's notification → 403 Forbidden
- Notification not found → 404 Not Found

---

### Subtask 8.1.6: Create Notification Types
**Files to Create/Modify:**
- `app/Services/NotificationService.php` (notification type constants)
- `app/Models/Notification.php` (add type constants if needed)

**Routes & Middleware:**
- N/A (internal constants)

**DB Reads/Writes:**
- None (constants only)

**Indexes Used:**
- None

**Authorization Rules:**
- N/A

**Abuse Prevention Measures:**
- N/A

**Acceptance Criteria:**
- Notification types defined:
  - generation_completed
  - post_liked
  - comment_added
  - post_curated
  - report_resolved (if specified)
- Types validated on creation

**Failure Cases & Security Pitfalls:**
- Invalid type → 422 Validation Error

---

### Subtask 8.1.7: Add Notification Preferences
**Files to Create:**
- `database/migrations/*_create_notification_preferences_table.php` (new migration)
- `app/Models/NotificationPreference.php` (new model)
- `app/Http/Controllers/Api/V1/NotificationController.php` (preferences methods)
- `app/Http/Requests/Api/V1/UpdateNotificationPreferencesRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/notifications/preferences` - `auth:sanctum`
- PUT `/api/v1/notifications/preferences` - `auth:sanctum`

**DB Reads/Writes:**
- Read: `notification_preferences` (WHERE user_id = current_user.id)
- Write: `notification_preferences` (INSERT/UPDATE)

**Indexes Used:**
- `notification_preferences.user_id` (for user's preferences)
- `notification_preferences.type` (for type filtering)

**Authorization Rules:**
- Users can only view/update their own preferences

**Abuse Prevention Measures:**
- N/A (user's own preferences)

**Acceptance Criteria:**
- Preferences table exists
- Users can get their preferences
- Users can update their preferences
- Default: all notifications enabled
- Preferences checked before creating notification

**Failure Cases & Security Pitfalls:**
- User updates other user's preferences → 403 Forbidden
- Invalid preference type → 422 Validation Error

**Test Coverage Expectations:**
- Feature test: User can get/update preferences
- Feature test: Preferences respected in notification creation
- Feature test: Authorization enforced

---

## Task 8.2: Content Moderation

### Subtask 8.2.1: Verify Moderation Queue Exists
**Files to Check:**
- `app/Models/ModerationQueue.php` (already exists)
- `database/migrations/*_create_moderation_queue_table.php` (already exists)

**Routes & Middleware:**
- All moderation routes require `auth:sanctum` + `admin` middleware

**DB Reads/Writes:**
- Read: `moderation_queue` (admin access)
- Write: `moderation_queue` (INSERT/UPDATE)

**Indexes Used:**
- `moderation_queue.gallery_post_id` (for post lookup)
- `moderation_queue.status` (for filtering)
- `moderation_queue.created_at` (for ordering)

**Authorization Rules:**
- Only admins/moderators can access moderation queue
- Regular users cannot access moderation queue

**Acceptance Criteria:**
- ModerationQueue model exists and works correctly
- Migration exists and table structure correct

**Failure Cases & Security Pitfalls:**
- Non-admin accesses moderation queue → 403 Forbidden
- Data leakage → Admin-only access prevents

---

### Subtask 8.2.2: Implement Automated Filtering (Placeholder)
**Files to Create:**
- `app/Services/ModerationService.php` (new file with placeholder policy hook)

**Routes & Middleware:**
- N/A (service class, called on content creation)

**DB Reads/Writes:**
- Write: `moderation_queue` (INSERT if content flagged)

**Indexes Used:**
- All indexes from previous subtask

**Authorization Rules:**
- N/A (automated system)

**Abuse Prevention Measures:**
- Automated filtering only flags, doesn't take action
- Human review required for all flagged content
- False positive handling (admin can override)

**Acceptance Criteria:**
- Service exists with checkContent() method (placeholder)
- Method can be extended with actual filtering logic
- Flagged content added to moderation queue
- Status set to 'pending' (awaiting review)

**Failure Modes & Mitigations:**
- False positives → Human review required, admin can override
- False negatives → Reports system catches missed content
- Filtering fails → Log error, content not flagged (safe default)

**Test Coverage Expectations:**
- Test placeholder policy hook exists
- Test flagged content added to queue
- Test human review required

---

### Subtask 8.2.3: Create Admin Moderation Endpoints
**Files to Create:**
- `app/Http/Controllers/Api/V1/AdminModerationController.php` (new file)
- `app/Http/Requests/Api/V1/ModerationActionRequest.php` (new - validation)

**Routes & Middleware:**
- GET `/api/v1/admin/moderation/queue` - `auth:sanctum`, `admin`
- GET `/api/v1/admin/moderation/reports` - `auth:sanctum`, `admin`
- POST `/api/v1/admin/moderation/posts/{id}/approve` - `auth:sanctum`, `admin`
- POST `/api/v1/admin/moderation/posts/{id}/reject` - `auth:sanctum`, `admin`
- POST `/api/v1/admin/moderation/reports/{id}/resolve` - `auth:sanctum`, `admin`
- POST `/api/v1/admin/moderation/reports/{id}/dismiss` - `auth:sanctum`, `admin`

**DB Reads/Writes:**
- Read: `moderation_queue` (list pending items)
- Read: `reports` (list all reports)
- Write: `moderation_queue` (UPDATE status, reviewed_by, reviewed_at)
- Write: `reports` (UPDATE status)
- Write: `gallery_posts` (UPDATE visibility, is_hidden, etc.)

**Indexes Used:**
- All indexes from previous subtasks

**Authorization Rules:**
- Only admins/moderators can access moderation endpoints
- Regular users cannot access moderation endpoints

**Abuse Prevention Measures:**
- Strict admin middleware
- Audit logging for all moderation actions

**Acceptance Criteria:**
- Queue listing endpoint works (paginated)
- Reports listing endpoint works (paginated)
- Approve action works (content approved, queue updated)
- Reject action works (content removed/hidden, queue updated)
- Resolve report works (report status updated)
- Dismiss report works (report status updated)
- Audit trail created for all actions

**Failure Cases & Security Pitfalls:**
- Non-admin accesses endpoint → 403 Forbidden
- Invalid post/report ID → 404 Not Found
- Action fails → Log error, return error response

**Test Coverage Expectations:**
- Feature test: Only admins can access moderation endpoints
- Feature test: Approve/reject actions work
- Feature test: Reports resolved/dismissed correctly
- Feature test: Audit trail created

---

### Subtask 8.2.4: Add Reporting System
**Files to Create:**
- `app/Http/Controllers/Api/V1/ReportController.php` (new file)
- `app/Http/Requests/Api/V1/StoreReportRequest.php` (new - validation)
- `app/Services/ModerationService.php` (addToModerationQueue method)

**Routes & Middleware:**
- POST `/api/v1/gallery/posts/{id}/report` - `auth:sanctum`
- GET `/api/v1/reports` - `auth:sanctum` (optional - user's own reports)

**DB Reads/Writes:**
- Write: `reports` (INSERT report)
- Write: `moderation_queue` (INSERT if threshold met or auto-flag)
- Read: `reports` (user's own reports, if endpoint exists)

**Indexes Used:**
- `reports.user_id` (for user's reports)
- `reports.gallery_post_id` (for post's reports)
- `reports.status` (for filtering)

**Authorization Rules:**
- All authenticated users can create reports
- Users can only view their own reports (if endpoint exists)
- Users cannot report their own content (if specified)

**Abuse Prevention Measures:**
- Rate limiting: Max 5 reports per user per day (configurable)
- Duplicate detection: Prevent duplicate reports (same user, same post, within 24 hours)
- Validation: Report reason must be valid enum

**Acceptance Criteria:**
- Report creation endpoint works
- Report reason validated (spam, inappropriate, copyright, harassment, other)
- Duplicate reports prevented
- Rate limiting works
- Report triggers moderation queue entry (if threshold met)
- User cannot report own content (if specified)

**Failure Cases & Security Pitfalls:**
- Duplicate report → 400 Bad Request or update existing
- Rate limit exceeded → 429 Too Many Requests
- User reports own content → 400 Bad Request (if specified)
- Invalid reason → 422 Validation Error

**Test Coverage Expectations:**
- Feature test: User can create report
- Feature test: Duplicate reports prevented
- Feature test: Rate limiting works
- Feature test: User cannot report own content (if specified)
- Feature test: Report triggers moderation queue (if threshold met)

---

### Subtask 8.2.5: Implement Content Removal
**Files to Create/Modify:**
- `app/Services/ModerationService.php` (removeContent method)
- `app/Http/Controllers/Api/V1/AdminModerationController.php` (reject method calls removal)

**Routes & Middleware:**
- N/A (handled in moderation endpoints)

**DB Reads/Writes:**
- Write: `gallery_posts` (UPDATE visibility='private' or is_hidden=true)
- Write: `gallery_posts` (UPDATE is_curated=false to remove from feed)
- Write: `gallery_posts` (DELETE if hard removal)

**Indexes Used:**
- `gallery_posts.id` (primary key lookup)

**Authorization Rules:**
- Only admins/moderators can remove content

**Abuse Prevention Measures:**
- Audit logging for all removal actions
- Soft delete preferred (reversible)

**Acceptance Criteria:**
- Content removal works (hide or delete)
- Content removed from feed (uncurated)
- Removal logged in audit trail
- Content owner notified (if specified)
- Removal reversible (soft delete)

**Failure Cases & Security Pitfalls:**
- Non-admin removes content → 403 Forbidden
- Removal fails → Log error, return error response
- Hard delete loses data → Use soft delete preferred

**Test Coverage Expectations:**
- Feature test: Content removal works
- Feature test: Content removed from feed
- Feature test: Audit trail created
- Feature test: Only admins can remove content

---

### Subtask 8.2.6: Add User Blocking (If Specified)
**Files to Create:**
- `database/migrations/*_create_user_blocks_table.php` (new migration, if specified)
- `app/Models/UserBlock.php` (new model, if specified)
- `app/Http/Controllers/Api/V1/BlockController.php` (new file, if specified)
- `app/Http/Requests/Api/V1/BlockUserRequest.php` (new - validation, if specified)

**Routes & Middleware:**
- POST `/api/v1/users/{id}/block` - `auth:sanctum` (if specified)
- DELETE `/api/v1/users/{id}/block` - `auth:sanctum` (if specified)
- GET `/api/v1/users/blocked` - `auth:sanctum` (if specified)

**DB Reads/Writes:**
- Write: `user_blocks` (INSERT block)
- Write: `user_blocks` (DELETE unblock)
- Read: `user_blocks` (list blocked users)

**Indexes Used:**
- `user_blocks.blocker_id` (for blocker's blocks)
- `user_blocks.blocked_id` (for blocked user lookup)
- Unique: (blocker_id, blocked_id) (prevent duplicate blocks)

**Authorization Rules:**
- Users can block/unblock other users
- Users cannot block themselves
- Blocked user's content hidden from blocker
- Blocked user cannot interact with blocker

**Abuse Prevention Measures:**
- Rate limiting: Max 50 blocks per user (configurable)
- Self-block prevention: Users cannot block themselves
- Duplicate block prevention: Unique constraint

**Acceptance Criteria:**
- Block endpoint works
- Unblock endpoint works
- Blocked user's content hidden from blocker
- Blocked user cannot like/comment on blocker's posts
- Block list endpoint works

**Failure Cases & Security Pitfalls:**
- User blocks themselves → 400 Bad Request
- Duplicate block → 400 Bad Request (unique constraint)
- Rate limit exceeded → 429 Too Many Requests

**Test Coverage Expectations:**
- Feature test: User can block/unblock
- Feature test: Blocked content hidden
- Feature test: Blocked user cannot interact
- Feature test: Self-block prevented

**Note**: Only implement if explicitly specified in BACKEND_TASKS.md. If not specified, skip this subtask.

---

## Summary

**Total Files to Create:** 10+
- 1 Service (NotificationService)
- 1 Service (ModerationService)
- 2 Controllers (NotificationController, ReportController, AdminModerationController)
- 1 Controller (BlockController, if user blocking specified)
- 5 Form Requests (NotificationListRequest, UpdateNotificationPreferencesRequest, StoreReportRequest, ModerationActionRequest, BlockUserRequest)
- 1 Migration (notification_preferences table)
- 1 Migration (user_blocks table, if blocking specified)
- 1 Model (NotificationPreference)
- 1 Model (UserBlock, if blocking specified)

**Total Files to Modify:** 6+
- app/Http/Controllers/Api/V1/LikeController.php (add notification on like)
- app/Http/Controllers/Api/V1/CommentController.php (add notification on comment)
- app/Jobs/GenerateImageJob.php (add notification on completion)
- app/Jobs/GenerateVideoJob.php (add notification on completion)
- app/Jobs/GenerateAudioJob.php (add notification on completion)
- app/Http/Controllers/Api/V1/AdminGalleryController.php (add notification on curation)
- routes/api.php (add notification and moderation routes)

**Dependencies:**
- Environment variables: None new
- Packages: None new
- Infrastructure: None new

**Authorization Checklist:**
- [ ] Users can only view their own notifications
- [ ] All authenticated users can create reports
- [ ] Only admins/moderators can access moderation endpoints
- [ ] Users cannot report their own content (if specified)
- [ ] Users can block/unblock (if specified)

**Abuse Prevention Checklist:**
- [ ] Rate limiting on reports (max 5 per day)
- [ ] Rate limiting on notifications (max 100 per hour)
- [ ] Duplicate report detection
- [ ] Duplicate notification prevention
- [ ] Content validation (report reason enum)

**Privacy Checklist:**
- [ ] No hidden prompts/models in notifications
- [ ] No private content details in notifications
- [ ] No sensitive data in logs
- [ ] Report creator identity not exposed (if specified)

**Test Coverage Minimum:**
- 10+ feature tests for notifications
- 10+ feature tests for moderation
- Test authorization correctness
- Test abuse prevention
- Test privacy enforcement

