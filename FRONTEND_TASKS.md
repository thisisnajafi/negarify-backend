# Negarify Platform - Frontend Development Tasks (Vue.js 3)

## Table of Contents
1. [Project Overview](#project-overview)
2. [Technology Stack](#technology-stack)
3. [Project Structure](#project-structure)
4. [Frontend Development Tasks](#frontend-development-tasks)
5. [Component Specifications](#component-specifications)
6. [Page Specifications](#page-specifications)
7. [State Management](#state-management)
8. [API Integration](#api-integration)
9. [Styling & UI/UX](#styling--uiux)
10. [Testing Requirements](#testing-requirements)
11. [Build & Deployment](#build--deployment)

---

## Project Overview

**Negarify Frontend** is a Vue.js 3 application that provides the user interface for the Negarify AI content generation platform. The frontend communicates with the Laravel backend API to enable users to generate images, videos, and audio content using tokens.

### Key Frontend Responsibilities
- User authentication (OTP-based)
- Token purchase and management
- Content generation interface (images, videos, audio)
- Gallery and curated feed browsing
- User profile management
- Admin dashboard (comprehensive analytics)
- Responsive design for desktop and mobile

---

## Technology Stack

### Core Framework
- **Vue.js**: 3.4+
- **Vite**: 5.0+ (build tool)
- **TypeScript**: 5.0+ (optional but recommended)
- **Vue Router**: 4.x (routing)
- **Pinia**: 2.x (state management)

### UI Framework (Choose One)
- **Option 1**: Vuetify 3.x (Material Design)
- **Option 2**: Element Plus (Element UI for Vue 3)
- **Option 3**: Tailwind CSS + Headless UI
- **Recommended**: Element Plus (good component library, easy customization)

### Additional Libraries
- **Axios**: HTTP client for API calls
- **VueUse**: Composition utilities
- **Chart.js / ApexCharts**: For admin dashboard charts
- **Vue3-Toastify**: Toast notifications
- **Vue3-Lazy-Load**: Image lazy loading
- **Vue3-Infinite-Scroll**: Infinite scroll for feed

---

## Project Structure

```
negarify-frontend/
├── public/
│   ├── index.html
│   └── favicon.ico
├── src/
│   ├── assets/
│   │   ├── images/
│   │   ├── styles/
│   │   └── fonts/
│   ├── components/
│   │   ├── common/
│   │   ├── auth/
│   │   ├── generation/
│   │   ├── gallery/
│   │   ├── admin/
│   │   └── layout/
│   ├── views/
│   │   ├── auth/
│   │   ├── generation/
│   │   ├── gallery/
│   │   ├── profile/
│   │   └── admin/
│   ├── stores/
│   │   ├── auth.js
│   │   ├── tokens.js
│   │   ├── generation.js
│   │   ├── gallery.js
│   │   └── admin.js
│   ├── services/
│   │   ├── api.js
│   │   ├── auth.js
│   │   └── storage.js
│   ├── router/
│   │   └── index.js
│   ├── composables/
│   │   ├── useAuth.js
│   │   ├── useApi.js
│   │   └── useCurrency.js
│   ├── utils/
│   │   ├── validators.js
│   │   ├── formatters.js
│   │   └── helpers.js
│   ├── App.vue
│   └── main.js
├── package.json
├── vite.config.js
└── .env
```

---

## Frontend Development Tasks

### Phase 1: Project Setup & Foundation

#### Task 1.1: Project Initialization
**Priority**: Critical  
**Estimated Time**: 1 day

**Subtasks**:
- [ ] Initialize Vue 3 project with Vite
- [ ] Install core dependencies (Vue Router, Pinia, Axios)
- [ ] Install UI framework (Element Plus recommended)
- [ ] Install additional libraries (toast, charts, etc.)
- [ ] Configure Vite build settings
- [ ] Set up environment variables (.env)
- [ ] Configure path aliases (@ for src/)
- [ ] Set up ESLint and Prettier
- [ ] Create basic project structure

**Package.json Dependencies**:
```json
{
  "dependencies": {
    "vue": "^3.4.0",
    "vue-router": "^4.2.0",
    "pinia": "^2.1.0",
    "axios": "^1.6.0",
    "element-plus": "^2.4.0",
    "vue3-toastify": "^0.2.0",
    "chart.js": "^4.4.0",
    "vue-chartjs": "^5.2.0",
    "@vueuse/core": "^10.7.0"
  }
}
```

**Vite Config**:
```js
// vite.config.js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src')
    }
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true
      }
    }
  }
})
```

**Acceptance Criteria**:
- Project initialized
- All dependencies installed
- Build process works
- Development server runs

---

#### Task 1.2: API Service Setup
**Priority**: Critical  
**Estimated Time**: 1 day

**Subtasks**:
- [ ] Create API service base class
- [ ] Configure Axios instance with base URL
- [ ] Add request interceptors (add auth token)
- [ ] Add response interceptors (handle errors)
- [ ] Implement token refresh logic
- [ ] Add request/response logging (dev only)
- [ ] Create API endpoint constants
- [ ] Set up error handling

**API Service**:
```js
// src/services/api.js
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request interceptor
api.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore()
    if (authStore.token) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor
api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    if (error.response?.status === 401) {
      // Handle unauthorized
      const authStore = useAuthStore()
      authStore.logout()
    }
    return Promise.reject(error)
  }
)

export default api
```

**Acceptance Criteria**:
- API service configured
- Interceptors work correctly
- Error handling functional

---

#### Task 1.3: Router Setup
**Priority**: Critical  
**Estimated Time**: 1 day

**Subtasks**:
- [ ] Install and configure Vue Router
- [ ] Create route definitions
- [ ] Set up route guards (auth, admin)
- [ ] Configure route meta (requiresAuth, requiresAdmin)
- [ ] Add 404 page
- [ ] Implement route transitions

**Router Structure**:
```js
// src/router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/',
    name: 'Home',
    component: () => import('@/views/Home.vue')
  },
  {
    path: '/auth',
    name: 'Auth',
    component: () => import('@/views/auth/Login.vue'),
    meta: { requiresGuest: true }
  },
  {
    path: '/generate',
    name: 'Generate',
    component: () => import('@/views/generation/Index.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/gallery',
    name: 'Gallery',
    component: () => import('@/views/gallery/Feed.vue')
  },
  {
    path: '/admin',
    name: 'Admin',
    component: () => import('@/views/admin/Dashboard.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guards
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'Auth' })
  } else if (to.meta.requiresAdmin && !authStore.user?.isAdmin) {
    next({ name: 'Home' })
  } else {
    next()
  }
})

export default router
```

**Acceptance Criteria**:
- Router configured
- Route guards work
- Navigation functional

---

#### Task 1.4: State Management (Pinia)
**Priority**: Critical  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Install and configure Pinia
- [ ] Create auth store
- [ ] Create tokens store
- [ ] Create generation store
- [ ] Create gallery store
- [ ] Create admin store
- [ ] Implement persistence (localStorage)
- [ ] Add store actions and getters

**Auth Store Example**:
```js
// src/stores/auth.js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token'))
  const isAuthenticated = computed(() => !!token.value && !!user.value)

  async function login(phone, code) {
    const response = await api.post('/auth/verify-otp', { phone, code })
    token.value = response.token
    user.value = response.user
    localStorage.setItem('token', response.token)
  }

  function logout() {
    user.value = null
    token.value = null
    localStorage.removeItem('token')
  }

  return {
    user,
    token,
    isAuthenticated,
    login,
    logout
  }
})
```

**Acceptance Criteria**:
- Pinia configured
- All stores created
- State persistence works

---

#### Task 1.5: Layout Components
**Priority**: High  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create main layout component
- [ ] Create header/navbar component
- [ ] Create footer component
- [ ] Create sidebar component (for admin)
- [ ] Create loading spinner component
- [ ] Create error boundary component
- [ ] Implement responsive navigation
- [ ] Add mobile menu toggle

**Layout Structure**:
```vue
<!-- src/components/layout/MainLayout.vue -->
<template>
  <div class="main-layout">
    <AppHeader />
    <main class="main-content">
      <router-view />
    </main>
    <AppFooter />
  </div>
</template>
```

**Acceptance Criteria**:
- Layout components created
- Responsive design works
- Navigation functional

---

### Phase 2: Authentication

#### Task 2.1: OTP Login Page
**Priority**: Critical  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create login page component
- [ ] Implement phone number input
- [ ] Add phone number validation
- [ ] Implement OTP request
- [ ] Create OTP input component (6 digits)
- [ ] Implement OTP verification
- [ ] Add resend OTP functionality
- [ ] Add loading states
- [ ] Add error handling
- [ ] Implement auto-focus and auto-submit

**Component Structure**:
```vue
<!-- src/views/auth/Login.vue -->
<template>
  <div class="login-page">
    <el-card>
      <h2>Login to Negarify</h2>
      <el-form @submit.prevent="handleSubmit">
        <el-input
          v-model="phone"
          placeholder="Phone number"
          :disabled="otpSent"
        />
        <el-input
          v-if="otpSent"
          v-model="otpCode"
          placeholder="Enter OTP"
          maxlength="6"
        />
        <el-button
          type="primary"
          :loading="loading"
          @click="otpSent ? verifyOtp() : requestOtp()"
        >
          {{ otpSent ? 'Verify' : 'Send OTP' }}
        </el-button>
        <el-button
          v-if="otpSent"
          type="text"
          @click="resendOtp"
        >
          Resend OTP
        </el-button>
      </el-form>
    </el-card>
  </div>
</template>
```

**Acceptance Criteria**:
- Login page functional
- OTP flow works
- Error handling proper

---

### Phase 3: Token Purchase

#### Task 3.1: Token Bundles Page
**Priority**: Critical  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create token bundles page
- [ ] Display token bundles (100, 500, 1000, 2000)
- [ ] Show real-time prices in Toman
- [ ] Fetch current USD rate
- [ ] Display price per token
- [ ] Add bundle selection
- [ ] Create purchase button
- [ ] Add loading states
- [ ] Implement responsive card layout

**Component**:
```vue
<!-- src/views/tokens/Bundles.vue -->
<template>
  <div class="bundles-page">
    <h1>Purchase Tokens</h1>
    <div class="bundles-grid">
      <el-card
        v-for="bundle in bundles"
        :key="bundle.id"
        class="bundle-card"
      >
        <h3>{{ bundle.name }}</h3>
        <p class="tokens">{{ bundle.token_amount }} Tokens</p>
        <p class="price">{{ formatPrice(bundle.price_toman) }} Toman</p>
        <el-button
          type="primary"
          @click="purchaseBundle(bundle.id)"
        >
          Purchase
        </el-button>
      </el-card>
    </div>
  </div>
</template>
```

**Acceptance Criteria**:
- Bundles displayed correctly
- Prices calculated in real-time
- Purchase flow initiated

---

#### Task 3.2: Payment Flow
**Priority**: Critical  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create payment page/component
- [ ] Handle Zarinpal payment redirect
- [ ] Create payment callback handler
- [ ] Display payment success page
- [ ] Display payment failure page
- [ ] Show order details
- [ ] Update token balance after payment
- [ ] Add payment status polling (if needed)

**Payment Flow**:
```js
// Purchase bundle
async function purchaseBundle(bundleId) {
  const response = await api.post('/tokens/purchase', { bundle_id: bundleId })
  // Redirect to Zarinpal
  window.location.href = response.payment_url
}

// Callback handler
async function handlePaymentCallback() {
  const params = new URLSearchParams(window.location.search)
  const authority = params.get('Authority')
  const status = params.get('Status')
  
  if (status === 'OK') {
    // Payment successful
    await api.get(`/tokens/purchase/callback?Authority=${authority}&Status=OK`)
    router.push('/tokens/success')
  } else {
    router.push('/tokens/failed')
  }
}
```

**Acceptance Criteria**:
- Payment flow works
- Callback handled correctly
- Token balance updated

---

#### Task 3.3: Token Balance & History
**Priority**: High  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create token balance display component
- [ ] Create transaction history page
- [ ] Display transaction list with filters
- [ ] Add pagination
- [ ] Show transaction types (purchase, consume, refund)
- [ ] Format dates and amounts
- [ ] Add export functionality (optional)

**Component**:
```vue
<!-- src/views/tokens/History.vue -->
<template>
  <div class="token-history">
    <h2>Token Balance: {{ balance }} Tokens</h2>
    <el-table :data="transactions" style="width: 100%">
      <el-table-column prop="type" label="Type" />
      <el-table-column prop="amount_tokens" label="Amount" />
      <el-table-column prop="description" label="Description" />
      <el-table-column prop="created_at" label="Date" />
    </el-table>
  </div>
</template>
```

**Acceptance Criteria**:
- Balance displayed correctly
- History shows all transactions
- Filters work

---

### Phase 4: Content Generation

#### Task 4.1: Generation Interface
**Priority**: Critical  
**Estimated Time**: 5 days

**Subtasks**:
- [ ] Create generation page
- [ ] Add content type selector (Image/Video/Audio)
- [ ] Create model selector dropdown
- [ ] Implement prompt input (textarea)
- [ ] Add negative prompt input (optional)
- [ ] Add parameter controls (size, quality, etc.)
- [ ] Show token cost estimate
- [ ] Add generate button
- [ ] Implement form validation
- [ ] Add parameter presets

**Component Structure**:
```vue
<!-- src/views/generation/Index.vue -->
<template>
  <div class="generation-page">
    <el-tabs v-model="activeTab">
      <el-tab-pane label="Image" name="image">
        <ImageGenerator />
      </el-tab-pane>
      <el-tab-pane label="Video" name="video">
        <VideoGenerator />
      </el-tab-pane>
      <el-tab-pane label="Audio" name="audio">
        <AudioGenerator />
      </el-tab-pane>
    </el-tabs>
  </div>
</template>
```

**Image Generator Component**:
```vue
<!-- src/components/generation/ImageGenerator.vue -->
<template>
  <el-form @submit.prevent="generate">
    <el-select v-model="selectedModel" placeholder="Select Model">
      <el-option
        v-for="model in imageModels"
        :key="model.id"
        :label="model.model_name"
        :value="model.id"
      />
    </el-select>
    
    <el-input
      v-model="prompt"
      type="textarea"
      placeholder="Enter your prompt"
      :rows="5"
    />
    
    <el-select v-model="size" placeholder="Size">
      <el-option label="1024x1024" value="1024x1024" />
      <el-option label="512x512" value="512x512" />
    </el-select>
    
    <div class="token-cost">
      Estimated Cost: {{ estimatedTokens }} tokens
    </div>
    
    <el-button
      type="primary"
      :loading="generating"
      :disabled="!canGenerate"
      @click="generate"
    >
      Generate
    </el-button>
  </el-form>
</template>
```

**Acceptance Criteria**:
- Generation interface functional
- All parameters configurable
- Token cost displayed

---

#### Task 4.2: Job Status & Results
**Priority**: Critical  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Create job status component
- [ ] Implement job polling
- [ ] Display job progress
- [ ] Show job status (pending, processing, completed, failed)
- [ ] Create results display component
- [ ] Add image/video/audio preview
- [ ] Implement download functionality
- [ ] Add "Publish to Gallery" button
- [ ] Show error messages for failed jobs
- [ ] Add retry functionality

**Component**:
```vue
<!-- src/components/generation/JobStatus.vue -->
<template>
  <div class="job-status">
    <el-steps :active="statusStep">
      <el-step title="Pending" />
      <el-step title="Processing" />
      <el-step title="Completed" />
    </el-steps>
    
    <div v-if="job.status === 'processing'" class="progress">
      <el-progress :percentage="progress" />
    </div>
    
    <div v-if="job.status === 'completed'" class="result">
      <img v-if="job.job_type === 'image'" :src="job.result_url" />
      <video v-if="job.job_type === 'video'" :src="job.result_url" controls />
      <audio v-if="job.job_type === 'audio'" :src="job.result_url" controls />
      
      <el-button @click="download">Download</el-button>
      <el-button @click="publishToGallery">Publish to Gallery</el-button>
    </div>
  </div>
</template>
```

**Job Polling**:
```js
async function pollJobStatus(jobId) {
  const interval = setInterval(async () => {
    const job = await api.get(`/generate/jobs/${jobId}`)
    if (job.status === 'completed' || job.status === 'failed') {
      clearInterval(interval)
    }
  }, 2000) // Poll every 2 seconds
}
```

**Acceptance Criteria**:
- Job status tracked correctly
- Results displayed properly
- Download works

---

#### Task 4.3: Job History
**Priority**: High  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create job history page
- [ ] Display user's generation jobs
- [ ] Add filters (type, status, date)
- [ ] Add pagination
- [ ] Show job thumbnails
- [ ] Add job details modal
- [ ] Implement job deletion
- [ ] Add bulk actions

**Component**:
```vue
<!-- src/views/generation/History.vue -->
<template>
  <div class="job-history">
    <el-table :data="jobs" style="width: 100%">
      <el-table-column label="Preview">
        <template #default="{ row }">
          <img :src="row.result_thumbnail_url" class="thumbnail" />
        </template>
      </el-table-column>
      <el-table-column prop="job_type" label="Type" />
      <el-table-column prop="status" label="Status" />
      <el-table-column prop="tokens_consumed" label="Tokens" />
      <el-table-column prop="created_at" label="Date" />
      <el-table-column label="Actions">
        <template #default="{ row }">
          <el-button @click="viewJob(row.id)">View</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>
```

**Acceptance Criteria**:
- Job history displayed
- Filters work
- Pagination functional

---

### Phase 5: Gallery & Feed

#### Task 5.1: Curated Feed Page
**Priority**: High  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Create feed page component
- [ ] Implement infinite scroll
- [ ] Display curated images and videos only
- [ ] Show view limit indicator
- [ ] Add content type filter (image/video)
- [ ] Implement lazy loading for images
- [ ] Add "Copy Prompt" button
- [ ] Add "Copy Model" button
- [ ] Show prompt and model info (if visible)
- [ ] Display user info and likes/comments
- [ ] Handle view limit reached state

**Component**:
```vue
<!-- src/views/gallery/Feed.vue -->
<template>
  <div class="feed-page">
    <div class="view-limit-info">
      Views Remaining: {{ viewsRemaining }}
    </div>
    
    <div class="feed-grid" v-infinite-scroll="loadMore">
      <FeedItem
        v-for="post in posts"
        :key="post.id"
        :post="post"
        @copy-prompt="handleCopyPrompt"
        @copy-model="handleCopyModel"
      />
    </div>
    
    <div v-if="viewsRemaining === 0" class="limit-reached">
      <p>You've reached your daily view limit</p>
      <p>Come back tomorrow for more!</p>
    </div>
  </div>
</template>
```

**Feed Item Component**:
```vue
<!-- src/components/gallery/FeedItem.vue -->
<template>
  <el-card class="feed-item">
    <img v-if="post.type === 'image'" :src="post.result_url" />
    <video v-if="post.type === 'video'" :src="post.result_url" controls />
    
    <div class="post-info">
      <h3>{{ post.title }}</h3>
      <p>{{ post.description }}</p>
      
      <div v-if="post.prompt_visible" class="prompt-section">
        <p><strong>Prompt:</strong> {{ post.prompt }}</p>
        <el-button size="small" @click="$emit('copy-prompt', post.prompt)">
          Copy Prompt
        </el-button>
      </div>
      
      <div v-if="post.model_visible" class="model-section">
        <p><strong>Model:</strong> {{ post.model_name }}</p>
        <el-button size="small" @click="$emit('copy-model', post.model_id)">
          Copy Model
        </el-button>
      </div>
      
      <div class="actions">
        <el-button @click="likePost">Like ({{ post.likes_count }})</el-button>
        <el-button @click="showComments">Comments ({{ post.comments_count }})</el-button>
      </div>
    </div>
  </el-card>
</template>
```

**Acceptance Criteria**:
- Feed displays curated content
- View limits enforced
- Copy functionality works
- Infinite scroll functional

---

#### Task 5.2: Gallery Post Creation
**Priority**: High  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create publish to gallery modal/page
- [ ] Add title and description inputs
- [ ] Implement tag input (multi-select)
- [ ] Add visibility settings (public/private)
- [ ] Add prompt/model visibility toggles
- [ ] Show preview of content
- [ ] Implement form validation
- [ ] Add publish button

**Component**:
```vue
<!-- src/components/gallery/PublishModal.vue -->
<template>
  <el-dialog v-model="visible" title="Publish to Gallery">
    <el-form>
      <el-input v-model="title" placeholder="Title" />
      <el-input
        v-model="description"
        type="textarea"
        placeholder="Description"
      />
      <el-select
        v-model="tags"
        multiple
        placeholder="Tags"
      >
        <el-option label="Landscape" value="landscape" />
        <el-option label="Portrait" value="portrait" />
      </el-select>
      
      <el-switch
        v-model="promptVisible"
        label="Make prompt visible"
      />
      <el-switch
        v-model="modelVisible"
        label="Make model visible"
      />
      
      <el-button type="primary" @click="publish">Publish</el-button>
    </el-form>
  </el-dialog>
</template>
```

**Acceptance Criteria**:
- Publish modal functional
- All fields work correctly
- Post created successfully

---

#### Task 5.3: Post Details & Interactions
**Priority**: Medium  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create post detail page
- [ ] Display full-size image/video
- [ ] Show post metadata
- [ ] Implement like functionality
- [ ] Create comment section
- [ ] Add nested comments (replies)
- [ ] Implement comment form
- [ ] Add share functionality (optional)
- [ ] Show related posts (optional)

**Component**:
```vue
<!-- src/views/gallery/PostDetail.vue -->
<template>
  <div class="post-detail">
    <div class="post-content">
      <img v-if="post.type === 'image'" :src="post.result_url" />
      <video v-if="post.type === 'video'" :src="post.result_url" controls />
    </div>
    
    <div class="post-info">
      <h1>{{ post.title }}</h1>
      <p>{{ post.description }}</p>
      
      <div class="actions">
        <el-button @click="toggleLike">
          <el-icon><Like /></el-icon>
          {{ post.likes_count }}
        </el-button>
      </div>
      
      <div class="comments-section">
        <h3>Comments</h3>
        <CommentList :comments="post.comments" />
        <CommentForm :post-id="post.id" />
      </div>
    </div>
  </div>
</template>
```

**Acceptance Criteria**:
- Post details displayed
- Likes work
- Comments functional

---

### Phase 6: User Profile

#### Task 6.1: Profile Page
**Priority**: High  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create profile page
- [ ] Display user information
- [ ] Show token balance
- [ ] Display avatar
- [ ] Add edit profile functionality
- [ ] Implement avatar upload
- [ ] Show user's gallery posts
- [ ] Display generation statistics

**Component**:
```vue
<!-- src/views/profile/Index.vue -->
<template>
  <div class="profile-page">
    <el-card>
      <div class="profile-header">
        <el-avatar :src="user.avatar_url" :size="100" />
        <h2>{{ user.name || user.phone }}</h2>
        <p>Token Balance: {{ user.tokens_balance }}</p>
      </div>
      
      <el-tabs>
        <el-tab-pane label="My Posts">
          <GalleryPostList :user-id="user.id" />
        </el-tab-pane>
        <el-tab-pane label="Settings">
          <ProfileSettings />
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>
```

**Acceptance Criteria**:
- Profile displayed correctly
- Edit functionality works
- Avatar upload works

---

### Phase 7: Admin Dashboard

#### Task 7.1: Admin Layout
**Priority**: High  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create admin layout component
- [ ] Add admin sidebar navigation
- [ ] Create admin header
- [ ] Add route protection for admin
- [ ] Implement responsive admin layout
- [ ] Add admin menu items

**Layout**:
```vue
<!-- src/views/admin/AdminLayout.vue -->
<template>
  <div class="admin-layout">
    <AdminSidebar />
    <div class="admin-content">
      <AdminHeader />
      <main>
        <router-view />
      </main>
    </div>
  </div>
</template>
```

**Acceptance Criteria**:
- Admin layout functional
- Navigation works
- Responsive design

---

#### Task 7.2: Sales Dashboard
**Priority**: High  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Create sales dashboard page
- [ ] Display revenue charts (line chart)
- [ ] Show total revenue (Toman and USD)
- [ ] Display top selling bundles (bar chart)
- [ ] Show refunds information
- [ ] Add date range picker
- [ ] Implement CSV export
- [ ] Add revenue breakdown by day/week/month
- [ ] Display customer LTV metrics

**Component**:
```vue
<!-- src/views/admin/SalesDashboard.vue -->
<template>
  <div class="sales-dashboard">
    <h1>Sales Dashboard</h1>
    
    <el-date-picker
      v-model="dateRange"
      type="daterange"
      @change="fetchData"
    />
    
    <el-row :gutter="20">
      <el-col :span="12">
        <el-card>
          <h3>Total Revenue</h3>
          <p class="revenue">{{ formatPrice(summary.total_revenue_toman) }} Toman</p>
          <p>{{ formatPrice(summary.total_revenue_usd) }} USD</p>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card>
          <h3>Total Orders</h3>
          <p class="orders">{{ summary.total_orders }}</p>
        </el-card>
      </el-col>
    </el-row>
    
    <el-card>
      <h3>Revenue Over Time</h3>
      <LineChart :data="revenueChartData" />
    </el-card>
    
    <el-card>
      <h3>Top Selling Bundles</h3>
      <BarChart :data="bundlesChartData" />
    </el-card>
    
    <el-button @click="exportCSV">Export CSV</el-button>
  </div>
</template>
```

**Acceptance Criteria**:
- Charts display correctly
- Data accurate
- Export works

---

#### Task 7.3: Users Dashboard
**Priority**: High  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Create users dashboard page
- [ ] Display DAU/WAU/MAU metrics
- [ ] Show user growth chart
- [ ] Display top users by generation
- [ ] Display top users by spending
- [ ] Implement user search
- [ ] Add user filters
- [ ] Create user detail modal
- [ ] Implement cohort analysis chart
- [ ] Show churn metrics

**Component**:
```vue
<!-- src/views/admin/UsersDashboard.vue -->
<template>
  <div class="users-dashboard">
    <h1>Users Dashboard</h1>
    
    <el-row :gutter="20">
      <el-col :span="8">
        <el-card>
          <h3>DAU</h3>
          <p>{{ summary.active_users.dau }}</p>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card>
          <h3>WAU</h3>
          <p>{{ summary.active_users.wau }}</p>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card>
          <h3>MAU</h3>
          <p>{{ summary.active_users.mau }}</p>
        </el-card>
      </el-col>
    </el-row>
    
    <el-card>
      <h3>User Growth</h3>
      <LineChart :data="growthChartData" />
    </el-card>
    
    <el-card>
      <h3>Top Generators</h3>
      <el-table :data="summary.top_generators">
        <el-table-column prop="name" label="User" />
        <el-table-column prop="generation_count" label="Generations" />
      </el-table>
    </el-card>
  </div>
</template>
```

**Acceptance Criteria**:
- Metrics displayed correctly
- Charts functional
- Search works

---

#### Task 7.4: Models Usage Dashboard
**Priority**: High  
**Estimated Time**: 4 days

**Subtasks**:
- [ ] Create models usage dashboard
- [ ] Display usage by model (table)
- [ ] Show success/failure rates
- [ ] Display average latency per model
- [ ] Show tokens consumed per model
- [ ] Display cost and revenue per model
- [ ] Add model comparison charts
- [ ] Implement date range filtering
- [ ] Add model type filter (image/video/audio)

**Component**:
```vue
<!-- src/views/admin/ModelsUsageDashboard.vue -->
<template>
  <div class="models-usage-dashboard">
    <h1>Models Usage Dashboard</h1>
    
    <el-select v-model="selectedType" placeholder="Filter by Type">
      <el-option label="All" value="" />
      <el-option label="Image" value="image" />
      <el-option label="Video" value="video" />
      <el-option label="Audio" value="audio" />
    </el-select>
    
    <el-table :data="usageData" style="width: 100%">
      <el-table-column prop="model_name" label="Model" />
      <el-table-column prop="requests_count" label="Requests" />
      <el-table-column prop="success_rate" label="Success Rate" />
      <el-table-column prop="avg_latency_ms" label="Avg Latency (ms)" />
      <el-table-column prop="tokens_consumed" label="Tokens" />
      <el-table-column prop="cost_usd" label="Cost (USD)" />
      <el-table-column prop="revenue_usd" label="Revenue (USD)" />
    </el-table>
    
    <el-card>
      <h3>Model Comparison</h3>
      <BarChart :data="comparisonChartData" />
    </el-card>
  </div>
</template>
```

**Acceptance Criteria**:
- Usage data displayed
- Charts functional
- Filters work

---

#### Task 7.5: Token Analytics Dashboard
**Priority**: High  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create token analytics dashboard
- [ ] Display tokens consumed per provider
- [ ] Show tokens by type (image/video/audio)
- [ ] Display cost and profit per provider
- [ ] Add time-series charts
- [ ] Show token consumption trends
- [ ] Implement date range filtering

**Component**:
```vue
<!-- src/views/admin/TokenAnalyticsDashboard.vue -->
<template>
  <div class="token-analytics-dashboard">
    <h1>Token Analytics</h1>
    
    <el-card>
      <h3>Tokens Consumed by Type</h3>
      <PieChart :data="tokensByTypeData" />
    </el-card>
    
    <el-card>
      <h3>Cost and Profit by Provider</h3>
      <BarChart :data="providerData" />
    </el-card>
    
    <el-card>
      <h3>Token Consumption Over Time</h3>
      <LineChart :data="consumptionChartData" />
    </el-card>
  </div>
</template>
```

**Acceptance Criteria**:
- Analytics displayed correctly
- Charts functional

---

#### Task 7.6: Cost & Profit Dashboard
**Priority**: High  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create cost/profit dashboard
- [ ] Display total revenue, cost, and profit
- [ ] Show profit margin
- [ ] Display profit by model
- [ ] Display profit by provider
- [ ] Add profit margin chart over time
- [ ] Show historical comparison
- [ ] Implement date range filtering

**Component**:
```vue
<!-- src/views/admin/CostProfitDashboard.vue -->
<template>
  <div class="cost-profit-dashboard">
    <h1>Cost & Profit Dashboard</h1>
    
    <el-row :gutter="20">
      <el-col :span="8">
        <el-card>
          <h3>Total Revenue</h3>
          <p>{{ formatPrice(summary.total_revenue_usd) }} USD</p>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card>
          <h3>Total Cost</h3>
          <p>{{ formatPrice(summary.total_cost_usd) }} USD</p>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card>
          <h3>Total Profit</h3>
          <p>{{ formatPrice(summary.total_profit_usd) }} USD</p>
          <p>Margin: {{ (summary.profit_margin * 100).toFixed(2) }}%</p>
        </el-card>
      </el-col>
    </el-row>
    
    <el-card>
      <h3>Profit Margin Over Time</h3>
      <LineChart :data="marginChartData" />
    </el-card>
  </div>
</template>
```

**Acceptance Criteria**:
- Cost/profit displayed correctly
- Charts functional

---

#### Task 7.7: System Health Dashboard
**Priority**: Medium  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create system health dashboard
- [ ] Display queue length
- [ ] Show worker status
- [ ] Display failed jobs count
- [ ] Show error rate
- [ ] Display API latency
- [ ] Show storage usage
- [ ] Display bandwidth usage
- [ ] Add real-time updates (polling)
- [ ] Create alert indicators

**Component**:
```vue
<!-- src/views/admin/SystemHealthDashboard.vue -->
<template>
  <div class="system-health-dashboard">
    <h1>System Health</h1>
    
    <el-row :gutter="20">
      <el-col :span="6">
        <el-card>
          <h3>Queue Length</h3>
          <p :class="health.queue_length > 100 ? 'warning' : ''">
            {{ health.queue_length }}
          </p>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card>
          <h3>Failed Jobs (24h)</h3>
          <p :class="health.failed_jobs_24h > 10 ? 'error' : ''">
            {{ health.failed_jobs_24h }}
          </p>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card>
          <h3>Error Rate</h3>
          <p :class="health.error_rate > 0.05 ? 'error' : ''">
            {{ (health.error_rate * 100).toFixed(2) }}%
          </p>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card>
          <h3>API Latency</h3>
          <p>{{ health.api_latency_ms }}ms</p>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>
```

**Acceptance Criteria**:
- Health metrics displayed
- Real-time updates work
- Alerts functional

---

#### Task 7.8: Feed Management Dashboard
**Priority**: Medium  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Create feed management page
- [ ] Display all gallery posts
- [ ] Add curation controls (curate/uncurate)
- [ ] Implement bulk curation actions
- [ ] Add featured post management
- [ ] Create feed preview
- [ ] Add post search and filters
- [ ] Show curation status

**Component**:
```vue
<!-- src/views/admin/FeedManagement.vue -->
<template>
  <div class="feed-management">
    <h1>Feed Management</h1>
    
    <el-table :data="posts" style="width: 100%">
      <el-table-column label="Preview">
        <template #default="{ row }">
          <img :src="row.result_thumbnail_url" class="thumbnail" />
        </template>
      </el-table-column>
      <el-table-column prop="title" label="Title" />
      <el-table-column prop="user.name" label="User" />
      <el-table-column label="Curation Status">
        <template #default="{ row }">
          <el-tag v-if="row.is_curated" type="success">Curated</el-tag>
          <el-tag v-else>Not Curated</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Actions">
        <template #default="{ row }">
          <el-button
            v-if="!row.is_curated"
            type="primary"
            @click="curatePost(row.id)"
          >
            Curate
          </el-button>
          <el-button
            v-else
            @click="uncuratePost(row.id)"
          >
            Uncurate
          </el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>
```

**Acceptance Criteria**:
- Feed management functional
- Curation works
- Bulk actions work

---

### Phase 8: Additional Features

#### Task 8.1: Notifications
**Priority**: Medium  
**Estimated Time**: 2 days

**Subtasks**:
- [ ] Create notification component
- [ ] Display notification bell icon
- [ ] Show notification count badge
- [ ] Create notification dropdown/list
- [ ] Implement mark as read
- [ ] Add notification polling
- [ ] Show notification types (like, comment, generation complete)

**Component**:
```vue
<!-- src/components/common/NotificationBell.vue -->
<template>
  <el-badge :value="unreadCount" class="notification-bell">
    <el-icon @click="showNotifications = true">
      <Bell />
    </el-icon>
  </el-badge>
  
  <el-drawer v-model="showNotifications" title="Notifications">
    <div v-for="notification in notifications" :key="notification.id">
      <el-card>
        <p>{{ notification.message }}</p>
        <el-button size="small" @click="markAsRead(notification.id)">
          Mark as Read
        </el-button>
      </el-card>
    </div>
  </el-drawer>
</template>
```

**Acceptance Criteria**:
- Notifications displayed
- Mark as read works
- Polling functional

---

#### Task 8.2: Responsive Design
**Priority**: High  
**Estimated Time**: 3 days

**Subtasks**:
- [ ] Make all pages responsive
- [ ] Add mobile navigation menu
- [ ] Optimize images for mobile
- [ ] Adjust layouts for tablets
- [ ] Test on various screen sizes
- [ ] Add touch-friendly buttons
- [ ] Optimize feed for mobile

**Acceptance Criteria**:
- All pages responsive
- Mobile navigation works
- Touch interactions work

---

## Component Specifications

### Common Components
- `AppHeader` - Main header with navigation
- `AppFooter` - Footer component
- `LoadingSpinner` - Loading indicator
- `ErrorBoundary` - Error handling
- `NotificationBell` - Notification icon with dropdown
- `TokenBalance` - Token balance display
- `CurrencyRate` - Current USD rate display

### Auth Components
- `OtpInput` - OTP code input (6 digits)
- `PhoneInput` - Phone number input with validation

### Generation Components
- `ImageGenerator` - Image generation form
- `VideoGenerator` - Video generation form
- `AudioGenerator` - Audio generation form
- `JobStatus` - Job status display with progress
- `JobResult` - Job result display with download

### Gallery Components
- `FeedItem` - Single feed item card
- `PostDetail` - Post detail page component
- `PublishModal` - Publish to gallery modal
- `CommentList` - Comments list component
- `CommentForm` - Comment form component

### Admin Components
- `AdminSidebar` - Admin navigation sidebar
- `AdminHeader` - Admin page header
- `DashboardCard` - Reusable dashboard card
- `ChartWrapper` - Chart component wrapper

---

## State Management

### Stores (Pinia)

#### Auth Store
```js
{
  user: null,
  token: null,
  isAuthenticated: computed,
  login(),
  logout(),
  fetchUser()
}
```

#### Tokens Store
```js
{
  balance: 0,
  bundles: [],
  transactions: [],
  currentRate: null,
  fetchBalance(),
  fetchBundles(),
  fetchTransactions(),
  fetchCurrencyRate()
}
```

#### Generation Store
```js
{
  jobs: [],
  currentJob: null,
  models: [],
  fetchJobs(),
  createJob(),
  pollJobStatus(),
  cancelJob()
}
```

#### Gallery Store
```js
{
  feed: [],
  viewsRemaining: 0,
  currentPost: null,
  fetchFeed(),
  loadMore(),
  likePost(),
  addComment()
}
```

---

## API Integration

### API Service Methods

```js
// Auth
api.post('/auth/request-otp', { phone })
api.post('/auth/verify-otp', { request_id, code })
api.post('/auth/resend-otp', { request_id })

// User
api.get('/user')
api.put('/user', data)
api.post('/user/avatar', formData)

// Tokens
api.get('/tokens/bundles')
api.get('/tokens/balance')
api.get('/tokens/history')
api.post('/tokens/purchase', { bundle_id })
api.get('/currency/rate')

// Generation
api.post('/generate/image', data)
api.post('/generate/video', data)
api.post('/generate/audio', data)
api.get('/generate/jobs')
api.get('/generate/jobs/{id}')
api.post('/generate/jobs/{id}/cancel')

// Gallery
api.post('/gallery/post', data)
api.get('/gallery/feed')
api.get('/gallery/posts/{id}')
api.post('/gallery/{id}/like')
api.post('/gallery/{id}/comment')

// Admin
api.get('/admin/sales/summary')
api.get('/admin/users/summary')
api.get('/admin/models/usage')
// ... more admin endpoints
```

---

## Styling & UI/UX

### Design System
- Use consistent color palette
- Implement consistent spacing (4px, 8px, 16px, etc.)
- Use consistent typography scale
- Implement dark mode (optional)
- Add smooth transitions and animations
- Ensure accessibility (WCAG 2.1)

### Responsive Breakpoints
- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: > 1024px

### UI Guidelines
- Use loading states for all async operations
- Show error messages clearly
- Provide feedback for user actions
- Use toast notifications for success/error
- Implement skeleton loaders for better UX

---

## Testing Requirements

### Unit Tests
- Component rendering
- Store actions and getters
- Utility functions
- Form validation

### Integration Tests
- API calls
- Authentication flow
- Payment flow
- Generation flow

### E2E Tests (Optional)
- Complete user journeys
- Admin workflows

---

## Build & Deployment

### Build Configuration
```js
// vite.config.js
export default defineConfig({
  build: {
    outDir: 'dist',
    sourcemap: false,
    rollupOptions: {
      output: {
        manualChunks: {
          'vendor': ['vue', 'vue-router', 'pinia'],
          'element-plus': ['element-plus'],
          'charts': ['chart.js', 'vue-chartjs']
        }
      }
    }
  }
})
```

### Environment Variables
```env
VITE_API_BASE_URL=https://api.negarify.com
VITE_APP_NAME=Negarify
```

### Deployment Steps
1. Build production bundle (`npm run build`)
2. Upload `dist` folder to web server
3. Configure Nginx/Apache
4. Set up SSL certificate
5. Configure CDN (optional)
6. Set up monitoring

---

**Document Version**: 1.0  
**Last Updated**: 2024-01-01  
**Status**: Active Development

