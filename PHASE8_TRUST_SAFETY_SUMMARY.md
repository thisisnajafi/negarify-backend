# Phase 8: Additional Features - Trust & Safety Summary

## Trust & Safety Understanding (Production Safety Review)

### 1. Notification Goals and Boundaries
- **Purpose**: Keep users informed of relevant events (likes, comments, generation completion)
- **Event Triggers**: 
  - Generation job completed (user's own generation)
  - Post liked (user's post)
  - Comment added (user's post or comment replied to)
  - Post curated (user's post curated by admin)
- **Boundaries**: 
  - Only notify about user's own content (posts, comments)
  - Never notify about private content visibility changes
  - Never include sensitive data (API keys, tokens, hidden prompts/models)
  - Respect user preferences (if user disabled notifications for a type)

### 2. Read/Unread Model and User Preferences
- **Read/Unread Status**: `read_at` timestamp (null = unread, timestamp = read)
- **User Preferences**: Optional notification preferences table (if specified in docs)
  - Users can disable specific notification types
  - Default: all notifications enabled
- **Notification Lifecycle**: 
  - Created → Unread
  - User views notification → Marked as read
  - User can mark as unread (if specified)
- **Privacy**: Users can only see their own notifications

### 3. Moderation Goals
- **Reporting Flow**:
  - User reports content (gallery post) with reason
  - Report created with status='pending'
  - Report triggers moderation queue entry (if threshold met or auto-flag)
  - Admin/moderator reviews report
  - Admin takes action (approve/reject/remove content)
  - Report status updated (resolved/dismissed)
- **Moderation Queue Lifecycle**:
  - Entry created from report or automated filtering
  - Status: pending → approved/rejected
  - Reviewed by admin/moderator (reviewed_by, reviewed_at)
  - Action taken (content removed/hidden if rejected)
- **Content Removal Actions**:
  - Hide content (set visibility='private' or add is_hidden flag)
  - Remove from feed (uncurate)
  - Delete content (soft delete or hard delete)
  - Notify content owner (if specified)
- **User Blocking** (if included in docs):
  - Block user from posting
  - Block user from commenting
  - Hide blocked user's content
  - Prevent blocked user from interacting with blocker

### 4. Key Risks and Mitigations
- **Spam Notifications**:
  - Risk: User receives too many notifications (notification spam)
  - Mitigation: Rate limiting (max notifications per hour), batching similar notifications, user preferences
- **Abusive Reports**:
  - Risk: Users report content maliciously (false reports, harassment)
  - Mitigation: Rate limiting (max reports per user per day), duplicate report detection, review threshold
- **False Positives/Negatives in Filtering**:
  - Risk: Automated filtering incorrectly flags/ignores content
  - Mitigation: Human review required, automated filtering as initial flag only, admin override
- **Data Leakage and Privacy Issues**:
  - Risk: Notifications or moderation expose private content, hidden prompts/models
  - Mitigation: Never include hidden prompts/models in notifications, only include public content info, sanitize all data
- **Escalation Paths**:
  - Risk: No clear path for complex moderation cases
  - Mitigation: Clear status workflow (pending → reviewed → resolved), audit trail, admin notes

### 5. Required Access Controls and Audit Logs
- **Notification Access**:
  - Users can only view their own notifications
  - No admin access to user notifications (unless specified)
- **Reporting Access**:
  - All authenticated users can create reports
  - Users can only view their own reports (if specified)
  - Admins/moderators can view all reports
- **Moderation Access**:
  - Only admins/moderators can access moderation queue
  - Only admins/moderators can take moderation actions
  - Regular users cannot moderate content
- **Audit Logs**:
  - Log all moderation actions (who, what, when, why)
  - Log report creation and resolution
  - Log content removal actions
  - Log user blocking actions (if implemented)
  - Never log sensitive data (API keys, tokens)

### 6. Notification Types
- **generation_completed**: Generation job completed successfully
- **post_liked**: User's post was liked
- **comment_added**: Comment added to user's post or reply to user's comment
- **post_curated**: User's post was curated by admin
- **report_resolved**: User's report was reviewed and resolved (if specified)

### 7. Report Reasons
- **spam**: Spam or low-quality content
- **inappropriate**: Inappropriate or offensive content
- **copyright**: Copyright violation
- **harassment**: Harassment or bullying
- **other**: Other reason (with description)

### 8. Moderation Queue Status
- **pending**: Awaiting review
- **approved**: Content approved (no action needed)
- **rejected**: Content rejected (action taken - removed/hidden)

### 9. Report Status
- **pending**: Report submitted, awaiting review
- **reviewed**: Report reviewed by admin
- **resolved**: Report resolved (action taken)
- **dismissed**: Report dismissed (no action needed)

### 10. Automated Filtering (If Documented)
- **Placeholder Policy Hook**: If automated filtering is required but not fully specified, implement a policy hook that can be extended
- **Initial Flag Only**: Automated filtering should only flag content, not take action
- **Human Review Required**: All flagged content must be reviewed by admin/moderator
- **False Positive Handling**: Admins can override automated decisions

### 11. Abuse Prevention Measures
- **Rate Limiting**:
  - Reports: Max 5 reports per user per day (configurable)
  - Notifications: Max 100 notifications per user per hour (configurable)
- **Duplicate Detection**:
  - Prevent duplicate reports (same user, same post, within 24 hours)
  - Prevent duplicate notifications (same event, same user, within 1 minute)
- **Content Validation**:
  - Report reason must be valid (enum check)
  - Report must reference valid post
  - User cannot report their own content (if specified)

### 12. Privacy and Data Safety
- **Notification Privacy**:
  - Never include hidden prompts/models in notification data
  - Never include private content details
  - Only include public information (post title, user name)
- **Moderation Privacy**:
  - Admins can see all content (including private) for moderation
  - Regular users cannot see moderation queue
  - Moderation actions logged but not exposed to users
- **Report Privacy**:
  - Report creator identity not exposed to content owner (if specified)
  - Report details only visible to admins/moderators

### 13. Content Removal Actions
- **Soft Removal**: Hide content (set is_hidden flag or visibility='private')
- **Hard Removal**: Delete content (cascade deletes likes/comments)
- **Feed Removal**: Uncurate post (remove from public feed)
- **User Notification**: Notify content owner of removal (if specified)
- **Reversibility**: Content removal should be reversible (soft delete preferred)

### 14. User Blocking (If Specified)
- **Block Actions**:
  - Block user from posting
  - Block user from commenting
  - Hide blocked user's content from blocker
  - Prevent blocked user from interacting with blocker
- **Block Storage**: `user_blocks` table (blocker_id, blocked_id, created_at)
- **Block Effects**:
  - Blocked user's posts hidden from blocker's feed
  - Blocked user cannot comment on blocker's posts
  - Blocked user cannot like blocker's posts

### 15. Audit Trail Requirements
- **Moderation Actions**: Who (admin_id), what (action), when (timestamp), why (reason)
- **Report Lifecycle**: Created, reviewed, resolved/dismissed with timestamps
- **Content Changes**: Visibility changes, removal actions logged
- **User Actions**: Blocking actions logged (if implemented)
- **Data Format**: Structured logs (JSON) for easy querying

### 16. Notification Service Design
- **Service Class**: `NotificationService` handles notification creation
- **Event-Driven**: Notifications created from events (like, comment, generation complete)
- **Batching**: Similar notifications batched (e.g., "5 people liked your post")
- **De-duplication**: Prevent duplicate notifications (same event, same user, within time window)
- **Preferences**: Check user preferences before creating notification

### 17. Moderation Service Design
- **Service Class**: `ModerationService` handles moderation logic
- **Queue Management**: Add/remove items from moderation queue
- **Action Execution**: Execute moderation actions (remove content, block user)
- **Audit Logging**: Log all moderation actions
- **Notification**: Notify affected users (if specified)

### 18. Authorization Strategy
- **Policies**: Use Laravel Policies for authorization
  - `NotificationPolicy`: Users can only view their own notifications
  - `ReportPolicy`: Users can create reports, admins can view all
  - `ModerationPolicy`: Only admins/moderators can moderate
- **Middleware**: Use existing `admin` middleware for moderation routes
- **Gates**: Use gates for fine-grained permissions (if needed)

### 19. API Endpoint Design
- **Notifications**:
  - GET `/api/v1/notifications` - List user's notifications (paginated)
  - GET `/api/v1/notifications/{id}` - Get notification details
  - PUT `/api/v1/notifications/{id}/read` - Mark as read
  - PUT `/api/v1/notifications/{id}/unread` - Mark as unread
  - GET `/api/v1/notifications/preferences` - Get preferences
  - PUT `/api/v1/notifications/preferences` - Update preferences
- **Reports**:
  - POST `/api/v1/gallery/posts/{id}/report` - Create report
  - GET `/api/v1/reports` - List user's reports (if specified)
- **Moderation** (Admin only):
  - GET `/api/v1/admin/moderation/queue` - List moderation queue
  - GET `/api/v1/admin/moderation/reports` - List all reports
  - POST `/api/v1/admin/moderation/posts/{id}/approve` - Approve content
  - POST `/api/v1/admin/moderation/posts/{id}/reject` - Reject/remove content
  - POST `/api/v1/admin/moderation/reports/{id}/resolve` - Resolve report
  - POST `/api/v1/admin/moderation/reports/{id}/dismiss` - Dismiss report

### 20. Data Model Considerations
- **Notifications Table**: Already exists (user_id, type, data_json, read_at, created_at)
- **Reports Table**: Already exists (user_id, gallery_post_id, reason, status, created_at)
- **Moderation Queue Table**: Already exists (gallery_post_id, reason, status, reviewed_by, reviewed_at, created_at)
- **Notification Preferences Table**: May need to create (user_id, type, enabled, created_at, updated_at)
- **User Blocks Table**: May need to create (blocker_id, blocked_id, created_at) if blocking is required

### 21. Failure Modes and Mitigations
- **Notification Creation Fails**: Log error, don't block main operation (non-critical)
- **Report Creation Fails**: Return error to user, log error
- **Moderation Action Fails**: Log error, return error to admin, don't partially apply
- **Duplicate Notification**: Skip creation (de-duplication)
- **Duplicate Report**: Return error or update existing report (based on policy)
- **Rate Limit Exceeded**: Return 429 Too Many Requests with retry-after header

### 22. Testing Considerations
- **Notification Tests**:
  - User can only view their own notifications
  - Notifications created on events (like, comment, generation)
  - Read/unread status works
  - Preferences respected
- **Report Tests**:
  - User can create report
  - Duplicate reports prevented (if specified)
  - Rate limiting works
  - User cannot report own content (if specified)
- **Moderation Tests**:
  - Only admins can access moderation queue
  - Moderation actions work (approve/reject)
  - Content removal works
  - Audit trail created
  - Reports resolved correctly

### 23. Performance Considerations
- **Notification Queries**: Index on user_id, read_at, created_at
- **Report Queries**: Index on gallery_post_id, status, created_at
- **Moderation Queue Queries**: Index on status, created_at
- **Pagination**: All list endpoints paginated (15-50 per page)
- **Eager Loading**: Load relationships to prevent N+1 queries

### 24. Security Considerations
- **Input Validation**: All inputs validated (report reason, notification preferences)
- **SQL Injection**: Use Eloquent ORM (parameterized queries)
- **XSS Prevention**: Sanitize user input in notification data
- **Authorization**: Strict authorization on all endpoints
- **Rate Limiting**: Prevent abuse with rate limiting
- **Audit Logging**: Log all sensitive actions (moderation, blocking)

### 25. Observability Requirements
- **Logging**: Log notification creation, report creation, moderation actions
- **Metrics**: Track notification delivery rate, report rate, moderation queue length
- **Alerts**: Alert on high report rate, long moderation queue, notification failures
- **No Secrets**: Never log API keys, tokens, sensitive user data

---

## Critical Safety Principles

1. **Privacy First**: Never expose private content, hidden prompts/models, or sensitive data
2. **Authorization Always**: Strict authorization on all endpoints (users see only their data, admins see all)
3. **Abuse Resistance**: Rate limiting, duplicate detection, validation prevent abuse
4. **Audit Trail**: All moderation actions logged for accountability
5. **Human Review**: Automated filtering only flags, humans make decisions
6. **Reversibility**: Content removal should be reversible (soft delete preferred)
7. **User Trust**: Transparent processes, clear communication, fair treatment
8. **Data Safety**: No secrets in logs, sanitized error messages, secure data handling

