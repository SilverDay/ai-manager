# Frontend Vue 3 Rules

Applied when working in `frontend/` directory.

## Component Structure

Every Vue component follows this order inside `<script setup>`:

```
1. imports
2. props / emits definitions
3. composables (useRouter, useI18n, etc.)
4. store references
5. reactive state (ref, reactive, computed)
6. watchers
7. lifecycle hooks (onMounted, etc.)
8. methods
```

## API Layer

All backend calls go through `src/api/` modules. Each module maps to one resource:

```javascript
// src/api/aiSystems.js
import { apiClient } from './client.js'

export const aiSystemsApi = {
  list: (params) => apiClient.get('/v1/ai-systems', { params }),
  get: (id) => apiClient.get(`/v1/ai-systems/${id}`),
  create: (data) => apiClient.post('/v1/ai-systems', data),
  update: (id, data) => apiClient.put(`/v1/ai-systems/${id}`, data),
}
```

The `apiClient` (Axios instance in `src/api/client.js`) handles:
- `Authorization: Bearer` header injection from the auth store
- Automatic token refresh on 401
- Standard error envelope parsing
- Base URL from environment config

Components call `aiSystemsApi.list()`, never `axios.get()` directly.

## State Management (Pinia)

One store per domain: `useAuthStore`, `useAiSystemsStore`, `useNotificationStore`, etc.

Use setup syntax:

```javascript
export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const accessToken = ref(null)
  const isAuthenticated = computed(() => !!accessToken.value)

  async function login(email, password) { ... }
  async function logout() { ... }
  async function refreshToken() { ... }

  return { user, accessToken, isAuthenticated, login, logout, refreshToken }
})
```

Stores own API calls for their domain. Components call store actions, not API modules directly (unless it's a one-off operation not worth storing).

## i18n

All user-facing text uses `$t('key')` in templates or `t('key')` in script setup via `const { t } = useI18n()`.

Locale files are in `src/i18n/locales/en.json`. Namespace by feature:

```json
{
  "auth": { "login": "Log in", "logout": "Log out" },
  "aiSystems": { "title": "AI Systems", "addNew": "Register AI System" },
  "common": { "save": "Save", "cancel": "Cancel", "delete": "Delete" }
}
```

Never hardcode user-facing strings in templates or script.

## Routing

Routes are grouped by feature in `src/router/index.js`. All routes require authentication except `/login`, `/register`, `/register/invite/:token`, `/forgot-password`, `/reset-password`.

Route meta fields:
- `requiresAuth: true` (default for all authenticated routes)
- `roles: ['Admin', 'AI Owner']` (RBAC check in router guard)
- `title: 'i18n.key'` (used in page title and breadcrumbs)

## Wizard Components

Wizard flows (AI Registration, Classification, Risk Assessment) use a shared `WizardShell.vue` component that provides:
- Step navigation with progress indicator
- Step validation before advancing
- State persistence in Pinia (survives page refresh via sessionStorage)
- Contextual help panel (right sidebar)

Each wizard step is a separate component: `RegistrationStep1Basic.vue`, `RegistrationStep2AiDetermination.vue`, etc.

## Tailwind

Use Tailwind utility classes. No custom CSS unless absolutely necessary (complex animations, third-party overrides).

Colour palette is defined in `tailwind.config.js` under `theme.extend.colors` with semantic names: `primary`, `secondary`, `danger`, `warning`, `success`, `surface`, `muted`.

Do not use arbitrary Tailwind values (`bg-[#1a2b3c]`). If you need a new colour, add it to the config.
