<template>
  <div class="space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between gap-4">
      <h1 class="text-xl font-bold text-gray-900">👥 {{ t('users.title') }}</h1>
      <button @click="openCreateModal" class="btn-primary">➕ {{ t('users.add_user') }}</button>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-gray-200 gap-4">
      <button
        v-for="tab in tabs" :key="tab.id"
        @click="activeTab = tab.id"
        :class="['pb-2 text-sm font-medium border-b-2 -mb-px transition-colors',
                 activeTab === tab.id
                   ? 'border-blue-600 text-blue-600'
                   : 'border-transparent text-gray-500 hover:text-gray-700']"
      >
        {{ tab.label }}
        <span v-if="tab.badge" class="ml-1.5 bg-red-100 text-red-600 text-xs px-1.5 py-0.5 rounded-full">{{ tab.badge }}</span>
      </button>
    </div>

    <!-- ── USERS TAB ────────────────────────────────────── -->
    <template v-if="activeTab === 'users'">
      <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="text-start py-3 px-4 font-medium text-gray-600">{{ t('users.name') }}</th>
              <th class="text-start py-3 px-4 font-medium text-gray-600">{{ t('users.email') }}</th>
              <th class="text-start py-3 px-4 font-medium text-gray-600">{{ t('users.role') }}</th>
              <th class="text-start py-3 px-4 font-medium text-gray-600">{{ t('users.status') }}</th>
              <th v-if="ebooksEnabled" class="text-start py-3 px-4 font-medium text-gray-600">📱 {{ t('ebooks.title') }}</th>
              <th class="text-start py-3 px-4 font-medium text-gray-600">{{ t('users.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
              <td class="py-3 px-4">
                <div class="font-medium text-gray-900">{{ user.full_name }}</div>
                <div class="text-xs text-gray-400">{{ user.username }}</div>
              </td>
              <td class="py-3 px-4 text-gray-600">{{ user.email }}</td>
              <td class="py-3 px-4">
                <span :class="['px-2 py-1 rounded-full text-xs font-medium',
                              user.role === 'admin'  ? 'bg-purple-100 text-purple-700' :
                              user.role === 'viewer' ? 'bg-gray-100 text-gray-700'   : 'bg-blue-100 text-blue-700']">
                  {{ user.role }}
                </span>
              </td>
              <td class="py-3 px-4">
                <span :class="user.is_active ? 'text-emerald-600' : 'text-red-500'">
                  {{ user.is_active ? t('users.active') : t('users.inactive') }}
                </span>
              </td>
              <!-- E-book toggle per user (hidden for current admin — use Settings instead) -->
              <td v-if="ebooksEnabled" class="py-3 px-4">
                <span v-if="user.id === currentUserId" class="text-xs text-gray-400 italic">{{ t('users.use_settings') }}</span>
                <button
                  v-else
                  @click="toggleEbookForUser(user)"
                  :class="['text-xs px-2.5 py-1 rounded-full font-medium transition-colors',
                           userEbookStatus[user.id] !== false
                             ? 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200'
                             : 'bg-gray-100 text-gray-500 hover:bg-gray-200']"
                >
                  {{ userEbookStatus[user.id] !== false ? '📱 ON' : '🚫 OFF' }}
                </button>
              </td>
              <td class="py-3 px-4 space-x-3 rtl:space-x-reverse">
                <button @click="toggleStatus(user)" class="text-xs text-blue-600 hover:text-blue-800">
                  {{ user.is_active ? t('users.disable') : t('users.enable') }}
                </button>
                <button @click="confirmDelete(user)" class="text-xs text-red-600 hover:text-red-800">{{ t('delete') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- ── ACCESS REQUESTS TAB ──────────────────────────── -->
    <template v-else-if="activeTab === 'requests'">
      <div v-if="requests.length === 0" class="text-center py-16 text-gray-400">
        <div class="text-4xl mb-2">✉️</div>
        <p>{{ t('users.no_requests') }}</p>
      </div>
      <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden divide-y divide-gray-100">
        <div v-for="req in requests" :key="req.id" class="flex items-start gap-4 p-4">
          <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-900">{{ req.email }}</p>
            <p v-if="req.message" class="text-sm text-gray-500 mt-0.5">{{ req.message }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ formatDate(req.created_at) }}</p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button @click="openApprove(req)" class="btn-primary text-xs px-3 py-1.5">✅ {{ t('users.approve') }}</button>
            <button @click="rejectRequest(req)" class="text-xs px-3 py-1.5 border border-red-300 text-red-600 rounded-lg hover:bg-red-50">✕ {{ t('users.reject') }}</button>
          </div>
        </div>
      </div>
    </template>

  </div>

  <!-- Create User Modal -->
  <Teleport to="body">
    <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showCreateModal = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold">➕ {{ t('users.create_new_user') }}</h2>
          <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form @submit.prevent="createUser" class="space-y-3">
          <div>
            <label class="label">{{ t('users.name') }} *</label>
            <input v-model="newUser.full_name" type="text" required class="input-field" />
          </div>
          <div>
            <label class="label">{{ t('users.username') }} *</label>
            <input v-model="newUser.username" type="text" required class="input-field" />
          </div>
          <div>
            <label class="label">{{ t('users.email') }} *</label>
            <input v-model="newUser.email" type="email" required class="input-field" />
          </div>
          <div>
            <label class="label">{{ t('users.role') }}</label>
            <select v-model="newUser.role" class="input-field">
              <option value="user">{{ t('users.role_user') }}</option>
              <option value="admin">{{ t('users.role_admin') }}</option>
              <option value="viewer">{{ t('users.role_viewer') }}</option>
            </select>
          </div>
          <!-- Send email option -->
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" v-model="newUser.send_email" class="rounded" />
            {{ t('users.send_credentials_email') }}
          </label>
          <p class="text-xs text-gray-400 -mt-1">{{ t('users.send_credentials_hint') }}</p>

          <div v-if="createError" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ createError }}</div>

          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showCreateModal = false" class="btn-ghost">{{ t('cancel') }}</button>
            <button type="submit" :disabled="creating" class="btn-primary">
              {{ creating ? t('loading') : t('users.add_user') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>

  <!-- Approve Access Request Modal -->
  <Teleport to="body">
    <div v-if="approveModal.show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="approveModal.show = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold">✅ {{ t('users.approve_request') }}</h2>
        <p class="text-sm text-gray-500">{{ t('users.approve_hint') }} <strong>{{ approveModal.email }}</strong></p>
        <form @submit.prevent="submitApprove" class="space-y-3">
          <div>
            <label class="label">{{ t('users.name') }} *</label>
            <input v-model="approveForm.full_name" type="text" required class="input-field" />
          </div>
          <div>
            <label class="label">{{ t('users.username') }} *</label>
            <input v-model="approveForm.username" type="text" required class="input-field" />
          </div>
          <div>
            <label class="label">{{ t('users.role') }}</label>
            <select v-model="approveForm.role" class="input-field">
              <option value="user">{{ t('users.role_user') }}</option>
              <option value="viewer">{{ t('users.role_viewer') }}</option>
            </select>
          </div>
          <p class="text-xs text-gray-400">{{ t('users.approve_email_note') }}</p>
          <div v-if="approveModal.error" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ approveModal.error }}</div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="approveModal.show = false" class="btn-ghost">{{ t('cancel') }}</button>
            <button type="submit" :disabled="approveModal.loading" class="btn-primary">
              {{ approveModal.loading ? t('loading') : t('users.approve_and_send') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useToastStore } from '../store/toast'
import { useEbookPlugin } from '../store/ebookPlugin'

const { t }       = useI18n()
const toastStore  = useToastStore()
const ebookPluginStore = useEbookPlugin()
const ebooksEnabled    = computed(() => ebookPluginStore.enabled)

import { useAuthStore } from '../store/auth'
const authStore   = useAuthStore()
const currentUserId = computed(() => authStore.user?.id)

// ── State ──────────────────────────────────────────────────────
const users           = ref([])
const requests        = ref([])
const userEbookStatus = reactive({})  // userId → true/false
const activeTab       = ref('users')

// ── Tabs ───────────────────────────────────────────────────────
const tabs = computed(() => [
  { id: 'users',    label: '👥 ' + t('users.title') },
  { id: 'requests', label: '✉️ ' + t('users.access_requests'), badge: requests.value.filter(r => r.status === 'pending').length || null },
])

// ── Create user form ───────────────────────────────────────────
const showCreateModal = ref(false)
const creating        = ref(false)
const createError     = ref('')
const newUser = reactive({ full_name: '', username: '', email: '', role: 'user', send_email: true })

// ── Approve form ───────────────────────────────────────────────
const approveModal = reactive({ show: false, id: null, email: '', error: '', loading: false })
const approveForm  = reactive({ full_name: '', username: '', role: 'user' })

// ── Load ───────────────────────────────────────────────────────
async function loadUsers() {
  try {
    users.value = (await api.get('/users')).data.data
    if (ebooksEnabled.value) await loadEbookOverrides()
  } catch { toastStore.error(t('users.load_failed')) }
}

async function loadRequests() {
  try {
    requests.value = (await api.get('/users/access-requests?status=pending')).data.data
  } catch {}
}

async function loadEbookOverrides() {
  try {
    const res = (await api.get('/ebook-plugin/users')).data.data
    res.forEach(u => { userEbookStatus[u.id] = u.ebook_enabled })
  } catch {}
}

// ── Create user ────────────────────────────────────────────────
function openCreateModal() {
  Object.assign(newUser, { full_name: '', username: '', email: '', role: 'user', send_email: true })
  createError.value = ''
  showCreateModal.value = true
}

async function createUser() {
  creating.value   = true
  createError.value = ''
  try {
    const res = await api.post('/users', newUser)
    toastStore.success(t('users.user_created'))
    if (res.data.temp_password && !newUser.send_email) {
      toastStore.success(t('users.temp_password_hint') + ': ' + res.data.temp_password)
    }
    showCreateModal.value = false
    loadUsers()
  } catch (err) {
    createError.value = err.response?.data?.error || t('users.create_failed')
  } finally {
    creating.value = false
  }
}

// ── User actions ───────────────────────────────────────────────
async function toggleStatus(user) {
  try {
    await api.put(`/users/${user.id}`, { is_active: !user.is_active })
    toastStore.success(t('users.status_updated'))
    loadUsers()
  } catch { toastStore.error(t('users.update_failed')) }
}

async function confirmDelete(user) {
  if (!confirm(t('users.delete_confirm', { name: user.full_name }))) return
  try {
    await api.delete(`/users/${user.id}`)
    toastStore.success(t('users.user_deleted'))
    loadUsers()
  } catch (err) { toastStore.error(err.response?.data?.error || t('users.delete_failed')) }
}

// ── Per-user ebook toggle ──────────────────────────────────────
async function toggleEbookForUser(user) {
  const current = userEbookStatus[user.id] !== false
  const action  = current ? 'disable' : 'enable'
  try {
    await api.post(`/ebook-plugin/user/${user.id}/${action}`)
    userEbookStatus[user.id] = !current
  } catch { toastStore.error(t('users.update_failed')) }
}

// ── Access requests ────────────────────────────────────────────
function openApprove(req) {
  approveModal.id    = req.id
  approveModal.email = req.email
  approveModal.error = ''
  Object.assign(approveForm, { full_name: '', username: '', role: 'user' })
  approveModal.show = true
}

async function submitApprove() {
  approveModal.loading = true
  approveModal.error   = ''
  try {
    await api.post(`/users/access-requests/${approveModal.id}/approve`, approveForm)
    toastStore.success(t('users.request_approved'))
    approveModal.show = false
    loadRequests()
    loadUsers()
  } catch (err) {
    approveModal.error = err.response?.data?.error || t('users.update_failed')
  } finally {
    approveModal.loading = false
  }
}

async function rejectRequest(req) {
  if (!confirm(t('users.reject_confirm'))) return
  try {
    await api.delete(`/users/access-requests/${req.id}`)
    toastStore.success(t('users.request_rejected'))
    loadRequests()
  } catch { toastStore.error(t('users.update_failed')) }
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '—' }

onMounted(() => { loadUsers(); loadRequests() })
</script>

<style scoped>
.input-field { @apply w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm; }
.btn-primary { @apply flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors text-sm disabled:opacity-50; }
.btn-ghost   { @apply px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors; }
.label       { @apply block text-sm font-medium text-gray-700 mb-1; }
</style>
