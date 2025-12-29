# Test Coverage Map

This document maps API endpoints to their corresponding test files and tracks implementation status.

**Last Updated**: Based on todo.md status

## Authentication Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `POST /api/v1/auth/request-otp` | `Feature/Auth/OtpRequestTest.php` | ✅ Complete |
| `POST /api/v1/auth/verify-otp` | `Feature/Auth/OtpVerifyTest.php` | ✅ Complete |
| `POST /api/v1/auth/resend-otp` | `Feature/Auth/OtpResendTest.php` | ✅ Complete |
| `POST /api/v1/auth/logout` | `Feature/Auth/LogoutTest.php` | ✅ Complete |

## User Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `GET /api/v1/user` | `Feature/User/ProfileTest.php` | ✅ Complete |
| `PUT /api/v1/user` | `Feature/User/ProfileTest.php` | ✅ Complete |
| `POST /api/v1/user/avatar` | `Feature/User/AvatarTest.php` | ✅ Complete |

## Token & Payment Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `GET /api/v1/tokens/bundles` | `Feature/Tokens/TokenBundlesTest.php` | ✅ Complete |
| `GET /api/v1/tokens/balance` | `Feature/Transactions/BalanceTest.php` | ✅ Complete |
| `GET /api/v1/tokens/history` | `Feature/Transactions/HistoryTest.php` | ✅ Complete |
| `POST /api/v1/tokens/purchase` | `Feature/Payments/PurchaseTest.php` | ✅ Complete |
| `GET /api/v1/tokens/purchase/callback` | `Feature/Payments/CallbackTest.php` | ✅ Complete |
| `GET /api/v1/currency/rate` | `Feature/Currency/RateTest.php` | ✅ Complete |

## Generation Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `POST /api/v1/generate/image` | `Feature/Generation/ImageGenerationTest.php` | ✅ Complete |
| `POST /api/v1/generate/video` | `Feature/Generation/VideoAudioGenerationTest.php` | ✅ Complete |
| `POST /api/v1/generate/audio` | `Feature/Generation/VideoAudioGenerationTest.php` | ✅ Complete |
| `GET /api/v1/generate/jobs` | `Feature/Generation/JobStatusTest.php` | ✅ Complete |
| `GET /api/v1/generate/jobs/{id}` | `Feature/Generation/JobStatusTest.php` | ✅ Complete |
| `POST /api/v1/generate/jobs/{id}/cancel` | `Feature/Generation/JobStatusTest.php` | ✅ Complete |
| `POST /api/v1/generate/jobs/{id}/retry` | `Feature/Generation/JobStatusTest.php` | ✅ Complete |
| Queue Job Processing | `Feature/Generation/QueueJobProcessingTest.php` | ✅ Complete |

## Gallery & Feed Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `POST /api/v1/gallery/post` | `Feature/Gallery/GalleryPostTest.php` | ✅ Complete |
| `GET /api/v1/gallery/posts/{id}` | `Feature/Gallery/GalleryPostTest.php` | ✅ Complete |
| `PUT /api/v1/gallery/posts/{id}` | `Feature/Gallery/GalleryPostManagementTest.php` | ✅ Complete |
| `DELETE /api/v1/gallery/posts/{id}` | `Feature/Gallery/GalleryPostManagementTest.php` | ✅ Complete |
| `GET /api/v1/gallery/my-posts` | `Feature/Gallery/GalleryPostManagementTest.php` | ✅ Complete |
| `GET /api/v1/gallery/feed` | `Feature/Feed/FeedListTest.php` | ⏳ Pending |
| `POST /api/v1/gallery/feed/copy-prompt` | `Feature/Feed/FeedCopyTest.php` | ⏳ Pending |
| `POST /api/v1/gallery/feed/copy-model` | `Feature/Feed/FeedCopyTest.php` | ⏳ Pending |
| Feed Visibility | `Feature/Feed/FeedVisibilityTest.php` | ⏳ Pending |

## Social Features Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `POST /api/v1/gallery/{id}/like` | `Feature/Social/LikeTest.php` | ⏳ Pending |
| `DELETE /api/v1/gallery/{id}/like` | `Feature/Social/LikeTest.php` | ⏳ Pending |
| `POST /api/v1/gallery/{id}/comment` | `Feature/Social/CommentTest.php` | ⏳ Pending |
| `DELETE /api/v1/gallery/comments/{id}` | `Feature/Social/CommentTest.php` | ⏳ Pending |
| Comment Accuracy | `Feature/Social/CommentAccuracyTest.php` | ⏳ Pending |
| Comment Delete | `Feature/Social/CommentDeleteTest.php` | ⏳ Pending |

## Admin Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `GET /api/v1/admin/sales/summary` | `Feature/Admin/AdminSalesTest.php` | ⏳ Pending |
| `GET /api/v1/admin/dashboard/summary` | `Feature/Admin/DashboardSummaryTest.php` | ⏳ Pending |
| `GET /api/v1/admin/users/summary` | `Feature/Admin/DashboardSummaryTest.php` | ⏳ Pending |
| `GET /api/v1/admin/models/usage` | `Feature/Admin/DashboardSummaryTest.php` | ⏳ Pending |
| `GET /api/v1/admin/system-health` | `Feature/Admin/SystemHealthTest.php` | ⏳ Pending |
| `POST /api/v1/admin/gallery/{id}/curate` | `Feature/Admin/GalleryAdminTest.php` | ⏳ Pending |
| `POST /api/v1/admin/gallery/{id}/uncurate` | `Feature/Admin/GalleryAdminTest.php` | ⏳ Pending |
| `GET /api/v1/admin/gallery/curated` | `Feature/Admin/GalleryAdminTest.php` | ⏳ Pending |
| Token Bundle Admin | `Feature/Admin/TokenBundleAdminTest.php` | ⏳ Pending |
| Moderation Admin | `Feature/Admin/ModerationAdminTest.php` | ⏳ Pending |

## Unit Tests

| Component | Test File | Status |
|-----------|-----------|--------|
| TgjuScraperService | `Unit/Services/TgjuScraperServiceTest.php` | ✅ Complete |
| MelipayamakService | `Unit/Services/MelipayamakServiceTest.php` | ⏳ Pending |
| ZarinpalService | `Unit/Services/ZarinpalServiceTest.php` | ⏳ Pending |
| SegmindImageService | `Unit/Services/SegmindImageServiceTest.php` | ⏳ Pending |
| TokenService | `Unit/Services/TokenServiceTest.php` | ⏳ Pending |
| User Model | `Unit/Models/UserTest.php` | ⏳ Pending |
| GenerationJob Model | `Unit/Models/GenerationJobTest.php` | ⏳ Pending |

## Integration Tests

| Service | Test File | Status |
|---------|-----------|--------|
| Melipayamak Contract | `Integration/SMS/MelipayamakContractTest.php` | ⏳ Pending |
| Zarinpal Contract | `Integration/Payments/ZarinpalContractTest.php` | ⏳ Pending |
| TGJU Scraper Contract | `Integration/Currency/TgjuScraperContractTest.php` | ⏳ Pending |

## Observability & Smoke Tests

| Test | Test File | Status |
|------|-----------|--------|
| Route Registration | `Feature/Observability/RouteRegistrationTest.php` | ⏳ Pending |
| Error Handling | `Feature/Observability/ErrorHandlingTest.php` | ⏳ Pending |
| Smoke Tests | `Feature/Observability/SmokeTest.php` | ⏳ Pending |

## Security Tests

| Test | Test File | Status |
|------|-----------|--------|
| Middleware | `Feature/Security/MiddlewareTest.php` | ⏳ Pending |
| Rate Limiting | `Feature/Security/RateLimitingTest.php` | ⏳ Pending |
| Input Validation | `Feature/Security/InputValidationTest.php` | ⏳ Pending |

## Other Tests

| Test | Test File | Status |
|------|-----------|--------|
| Notifications | `Feature/Notifications/NotificationTest.php` | ⏳ Pending |
| Reports | `Feature/Reports/ReportTest.php` | ⏳ Pending |
| Transaction Types | `Feature/Tokens/TransactionTypesTest.php` | ✅ Complete |

## Status Legend

- ✅ Complete - Tests implemented and verified
- ⏳ Pending - Tests not yet implemented or incomplete
- 🚧 In Progress - Tests currently being implemented
- ❌ Blocked - Tests blocked by dependencies

## Coverage Summary

- **Authentication**: 4/4 endpoints (100%)
- **User Profile**: 3/3 endpoints (100%)
- **Tokens & Payments**: 6/6 endpoints (100%)
- **Generation**: 7/7 endpoints (100%)
- **Gallery (CRUD)**: 5/5 endpoints (100%)
- **Feed**: 0/4 endpoints (0%)
- **Social Features**: 0/6 endpoints (0%)
- **Admin Dashboard**: 0/9+ endpoints (0%)
- **Security**: 0/3 test suites (0%)
- **Observability**: 0/3 test suites (0%)

**Overall Progress**: ~60% of core endpoints covered
