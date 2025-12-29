# Test Coverage Map

This document maps API endpoints to their corresponding test files.

## Authentication Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `POST /api/v1/auth/request-otp` | `Feature/Auth/OtpRequestTest.php` | ⏳ Pending |
| `POST /api/v1/auth/verify-otp` | `Feature/Auth/OtpVerifyTest.php` | ⏳ Pending |
| `POST /api/v1/auth/resend-otp` | `Feature/Auth/OtpResendTest.php` | ⏳ Pending |
| `POST /api/v1/auth/logout` | `Feature/Auth/LogoutTest.php` | ⏳ Pending |

## User Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `GET /api/v1/user` | `Feature/User/ProfileTest.php` | ⏳ Pending |
| `PUT /api/v1/user` | `Feature/User/ProfileTest.php` | ⏳ Pending |
| `POST /api/v1/user/avatar` | `Feature/User/AvatarTest.php` | ⏳ Pending |

## Token & Payment Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `GET /api/v1/tokens/bundles` | `Feature/Tokens/TokenBundlesTest.php` | ⏳ Pending |
| `GET /api/v1/tokens/balance` | `Feature/Transactions/BalanceTest.php` | ⏳ Pending |
| `GET /api/v1/tokens/history` | `Feature/Transactions/HistoryTest.php` | ⏳ Pending |
| `POST /api/v1/tokens/purchase` | `Feature/Payments/PurchaseTest.php` | ⏳ Pending |
| `GET /api/v1/tokens/purchase/callback` | `Feature/Payments/CallbackTest.php` | ⏳ Pending |
| `GET /api/v1/currency/rate` | `Feature/Currency/RateTest.php` | ⏳ Pending |

## Generation Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `POST /api/v1/generate/image` | `Feature/Generation/ImageGenerationTest.php` | ⏳ Pending |
| `POST /api/v1/generate/video` | `Feature/Generation/VideoGenerationTest.php` | ⏳ Pending |
| `POST /api/v1/generate/audio` | `Feature/Generation/AudioGenerationTest.php` | ⏳ Pending |
| `GET /api/v1/generate/jobs` | `Feature/Generation/JobListingTest.php` | ⏳ Pending |
| `GET /api/v1/generate/jobs/{id}` | `Feature/Generation/JobStatusTest.php` | ⏳ Pending |
| `POST /api/v1/generate/jobs/{id}/cancel` | `Feature/Generation/JobCancellationTest.php` | ⏳ Pending |
| `POST /api/v1/generate/jobs/{id}/retry` | `Feature/Generation/JobRetryTest.php` | ⏳ Pending |

## Gallery & Feed Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `POST /api/v1/gallery/post` | `Feature/Gallery/PostCreationTest.php` | ⏳ Pending |
| `GET /api/v1/gallery/posts/{id}` | `Feature/Gallery/PostDetailTest.php` | ⏳ Pending |
| `PUT /api/v1/gallery/posts/{id}` | `Feature/Gallery/PostUpdateTest.php` | ⏳ Pending |
| `DELETE /api/v1/gallery/posts/{id}` | `Feature/Gallery/PostDeletionTest.php` | ⏳ Pending |
| `GET /api/v1/gallery/my-posts` | `Feature/Gallery/MyPostsTest.php` | ⏳ Pending |
| `GET /api/v1/gallery/feed` | `Feature/Feed/FeedTest.php` | ⏳ Pending |
| `POST /api/v1/gallery/feed/copy-prompt` | `Feature/Feed/CopyPromptTest.php` | ⏳ Pending |
| `POST /api/v1/gallery/feed/copy-model` | `Feature/Feed/CopyModelTest.php` | ⏳ Pending |

## Social Features Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `POST /api/v1/gallery/{id}/like` | `Feature/Social/LikeTest.php` | ⏳ Pending |
| `DELETE /api/v1/gallery/{id}/like` | `Feature/Social/LikeTest.php` | ⏳ Pending |
| `POST /api/v1/gallery/{id}/comment` | `Feature/Social/CommentTest.php` | ⏳ Pending |
| `DELETE /api/v1/gallery/comments/{id}` | `Feature/Social/CommentTest.php` | ⏳ Pending |

## Admin Endpoints

| Endpoint | Test File | Status |
|----------|-----------|--------|
| `GET /api/v1/admin/sales/summary` | `Feature/Admin/SalesDashboardTest.php` | ⏳ Pending |
| `GET /api/v1/admin/users/summary` | `Feature/Admin/UsersDashboardTest.php` | ⏳ Pending |
| `GET /api/v1/admin/users/list` | `Feature/Admin/UsersListTest.php` | ⏳ Pending |
| `GET /api/v1/admin/models/usage` | `Feature/Admin/ModelsUsageTest.php` | ⏳ Pending |
| `GET /api/v1/admin/tokens/summary` | `Feature/Admin/TokenAnalyticsTest.php` | ⏳ Pending |
| `GET /api/v1/admin/cost-profit/summary` | `Feature/Admin/CostProfitTest.php` | ⏳ Pending |
| `GET /api/v1/admin/system-health` | `Feature/Admin/SystemHealthTest.php` | ⏳ Pending |
| `POST /api/v1/admin/gallery/{id}/curate` | `Feature/Admin/FeedManagementTest.php` | ⏳ Pending |
| `POST /api/v1/admin/gallery/{id}/uncurate` | `Feature/Admin/FeedManagementTest.php` | ⏳ Pending |
| `GET /api/v1/admin/gallery/curated` | `Feature/Admin/FeedManagementTest.php` | ⏳ Pending |

## Unit Tests

| Component | Test File | Status |
|-----------|-----------|--------|
| MelipayamakService | `Unit/Services/MelipayamakServiceTest.php` | ⏳ Pending |
| ZarinpalService | `Unit/Services/ZarinpalServiceTest.php` | ⏳ Pending |
| TgjuScraperService | `Unit/Services/TgjuScraperServiceTest.php` | ⏳ Pending |
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

## Smoke Tests

| Test | Test File | Status |
|------|-----------|--------|
| Route Registration | `Feature/Smoke/RouteRegistrationTest.php` | ⏳ Pending |
| Error Handling | `Feature/Smoke/ErrorHandlingTest.php` | ⏳ Pending |

## Status Legend

- ✅ Complete
- ⏳ Pending
- 🚧 In Progress
- ❌ Blocked

