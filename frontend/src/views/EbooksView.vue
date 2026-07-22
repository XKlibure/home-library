<template>
  <div class="space-y-4">

    <!-- ── Page header ─────────────────────────────────────────── -->
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-gray-900">{{ t('ebooks.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ pagination.total }} {{ t('ebooks.title').toLowerCase() }}</p>
      </div>
      <button @click="showUploadModal = true" class="btn-primary flex items-center gap-2">
        ➕ <span class="hidden sm:inline">{{ t('ebooks.upload_ebook') }}</span>
      </button>
    </div>

    <!-- ── Filter + toggle bar ──────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-48">
        <span class="absolute ltr:left-3 rtl:right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">🔍</span>
        <input
          v-model="search"
          @input="debouncedLoad"
          type="text"
          :placeholder="t('ebooks.search_placeholder')"
          class="w-full ltr:pl-8 rtl:pr-8 pr-3 pl-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
        />
      </div>

      <select v-model="filterFormat" @change="loadEbooks()" class="filter-select">
        <option value="">{{ t('ebooks.all_formats') }}</option>
        <option value="pdf">PDF</option>
        <option value="epub">EPUB</option>
        <option value="mobi">MOBI</option>
      </select>

      <div class="flex-1 hidden lg:block" />

      <!-- List / Grid toggle -->
      <div class="flex items-center bg-gray-100 rounded-lg p-0.5 gap-0.5">
        <button @click="viewMode = 'list'"
          :class="['p-1.5 rounded-md text-sm transition-colors', viewMode==='list' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700']">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <button @click="viewMode = 'grid'"
          :class="['p-1.5 rounded-md text-sm transition-colors', viewMode==='grid' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700']">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h7v7H4zM13 6h7v7h-7zM4 15h7v7H4zM13 15h7v7h-7z"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- ── Loading ──────────────────────────────────────────────── -->
    <div v-if="loading" class="text-center py-16">
      <div class="inline-block w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- ── Empty ────────────────────────────────────────────────── -->
    <div v-else-if="ebooks.length === 0" class="text-center py-20 bg-white rounded-xl border border-gray-200">
      <div class="text-5xl mb-3">📱</div>
      <p class="text-gray-500 text-sm">{{ t('ebooks.no_ebooks') }}</p>
      <p class="text-xs text-gray-400 mt-1">{{ t('ebooks.upload_hint') }}</p>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         LIST VIEW
    ════════════════════════════════════════════════════════════ -->
    <div v-else-if="viewMode === 'list'" class="bg-white rounded-xl border border-gray-200 overflow-hidden divide-y divide-gray-100">
      <div
        v-for="ebook in ebooks" :key="ebook.id"
        @click="$router.push(`/ebooks/${ebook.id}`)"
        class="group flex gap-4 px-4 py-3.5 hover:bg-indigo-50/40 cursor-pointer transition-colors"
      >
        <!-- Cover thumbnail -->
        <div class="w-10 h-14 shrink-0 rounded-md overflow-hidden bg-gradient-to-br from-indigo-100 to-purple-200 flex items-center justify-center shadow-sm">
          <img v-if="ebook.cover_source !== 'default' && coverUrls[ebook.id]"
               :src="coverUrls[ebook.id]" :alt="ebook.title"
               class="w-full h-full object-cover" />
          <span v-else class="text-lg">{{ formatIcon(ebook.file_format) }}</span>
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start gap-2 flex-wrap">
            <span class="font-semibold text-gray-900 text-sm leading-snug truncate max-w-sm">{{ ebook.title }}</span>
            <span class="shrink-0 text-xs font-bold uppercase px-1.5 py-0.5 rounded"
                  :class="formatBadgeClass(ebook.file_format)">{{ ebook.file_format }}</span>
            <span v-if="!ebook.metadata_complete" class="shrink-0 text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded">⚠️</span>
          </div>
          <p class="text-xs text-gray-500 mt-0.5 truncate">
            {{ ebook.author || t('ebooks.unknown_author') }}
            <span v-if="ebook.publisher_name" class="text-gray-400"> · {{ ebook.publisher_name }}</span>
          </p>
          <!-- Progress -->
          <div v-if="ebook.total_pages > 0" class="flex items-center gap-2 mt-1.5">
            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden w-20">
              <div class="h-full bg-indigo-500 rounded-full" :style="{ width: ebook.read_percentage + '%' }"></div>
            </div>
            <span class="text-xs font-medium" :class="ebook.read_percentage===100 ? 'text-emerald-600' : 'text-indigo-500'">
              {{ ebook.read_percentage }}%
            </span>
          </div>
        </div>

        <!-- Read button (on hover) -->
        <div class="shrink-0 flex items-center opacity-0 group-hover:opacity-100 transition-opacity">
          <button
            @click.stop="$router.push(`/ebooks/${ebook.id}/read`)"
            class="text-xs bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 font-medium"
          >
            📖 {{ t('ebooks.read_book') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         GRID VIEW
    ════════════════════════════════════════════════════════════ -->
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
      <div
        v-for="ebook in ebooks" :key="ebook.id"
        @click="$router.push(`/ebooks/${ebook.id}`)"
        class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-md hover:border-indigo-200 cursor-pointer group transition-all"
      >
        <div class="relative aspect-[2/3] bg-gradient-to-br from-indigo-100 to-purple-200 flex items-center justify-center overflow-hidden">
          <img v-if="ebook.cover_source !== 'default' && coverUrls[ebook.id]"
               :src="coverUrls[ebook.id]" :alt="ebook.title"
               class="w-full h-full object-cover" />
          <div v-else class="flex flex-col items-center text-indigo-400 p-2 text-center">
            <span class="text-3xl mb-1">{{ formatIcon(ebook.file_format) }}</span>
            <span class="text-xs font-medium uppercase">{{ ebook.file_format }}</span>
          </div>
          <span class="absolute top-1.5 ltr:right-1.5 rtl:left-1.5 text-xs font-bold uppercase px-1.5 py-0.5 rounded"
                :class="formatBadgeClass(ebook.file_format)">{{ ebook.file_format }}</span>
          <!-- Hover overlay -->
          <div class="absolute inset-0 bg-indigo-900/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
            <span class="text-white text-sm font-medium">📖 {{ t('ebooks.read_book') }}</span>
          </div>
        </div>
        <div class="p-2.5">
          <p class="text-sm font-semibold text-gray-900 truncate">{{ ebook.title }}</p>
          <p class="text-xs text-gray-500 truncate mt-0.5">{{ ebook.author || t('ebooks.unknown_author') }}</p>
          <div v-if="ebook.total_pages > 0" class="mt-2">
            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full bg-indigo-500 rounded-full" :style="{ width: ebook.read_percentage + '%' }"></div>
            </div>
            <p class="text-right text-xs text-indigo-500 mt-0.5">{{ ebook.read_percentage }}%</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Pagination ────────────────────────────────────────────── -->
    <div v-if="pagination.last_page > 1" class="flex justify-center items-center gap-2 pt-2">
      <button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="btn-page">
        ← {{ t('books.previous') }}
      </button>
      <span class="text-sm text-gray-600">
        {{ t('books.page_info', { current: pagination.current_page, total: pagination.last_page }) }}
      </span>
      <button @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="btn-page">
        {{ t('books.next') }} →
      </button>
    </div>

  </div>

  <!-- ══════════════════════════════════════════════════════════════
       UPLOAD MODAL (unchanged logic, refreshed styling)
  ══════════════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <div v-if="showUploadModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showUploadModal = false">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">📤 {{ t('ebooks.upload_ebook') }}</h2>
          <button @click="showUploadModal = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>

        <form @submit.prevent="submitUpload" class="space-y-4">
          <div
            class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center cursor-pointer hover:border-indigo-400 transition-colors bg-gray-50"
            @click="$refs.fileInput.click()" @dragover.prevent @drop.prevent="onFileDrop"
          >
            <input ref="fileInput" type="file" accept=".pdf,.epub,.mobi" class="hidden" @change="onFileSelect" />
            <div v-if="!uploadFile">
              <div class="text-3xl mb-2">📎</div>
              <p class="text-sm text-gray-600">{{ t('ebooks.drag_or_click') }}</p>
              <p class="text-xs text-gray-400 mt-1">PDF · EPUB · MOBI · max 100 MB</p>
            </div>
            <div v-else class="flex items-center justify-center gap-3">
              <span class="text-2xl">{{ formatIcon(uploadFileExt) }}</span>
              <div class="text-left">
                <p class="text-sm font-medium text-gray-900">{{ uploadFile.name }}</p>
                <p class="text-xs text-gray-500">{{ formatFileSize(uploadFile.size) }}</p>
              </div>
              <button type="button" @click.stop="clearFile" class="text-red-400 hover:text-red-600">✕</button>
            </div>
          </div>

          <div>
            <label class="label">{{ t('ebooks.title_override') }}</label>
            <input v-model="uploadForm.title" type="text" class="input-field" :placeholder="t('ebooks.title_auto_hint')" />
          </div>
          <div>
            <label class="label">{{ t('ebooks.author_override') }}</label>
            <SearchableSelect
              v-model="uploadForm.author"
              :items="writers"
              :placeholder="t('ebooks.author_placeholder')"
              :search-placeholder="t('ebooks.search_writer')"
              :create-label="t('ebooks.add_writer')"
              @create="createWriter"
            />
          </div>
          <div>
            <label class="label">{{ t('ebooks.publisher_label') }}</label>
            <SearchableSelect
              v-model="uploadForm.publisher_name"
              :items="publishers"
              :placeholder="t('ebooks.publisher_placeholder')"
              :search-placeholder="t('ebooks.search_publisher')"
              :create-label="t('ebooks.add_publisher')"
              @select="item => uploadForm.publisher_id = item?.id || null"
              @create="createPublisher"
            />
          </div>
          <div>
            <label class="label">{{ t('ebooks.total_pages_label') }}</label>
            <input v-model.number="uploadForm.total_pages" type="number" min="0" class="input-field" :placeholder="t('ebooks.total_pages_hint')" />
          </div>

          <p v-if="uploadError" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ uploadError }}</p>

          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showUploadModal = false" class="btn-ghost">{{ t('cancel') }}</button>
            <button type="submit" :disabled="!uploadFile || uploading" class="btn-primary">
              {{ uploading ? t('ebooks.uploading') : t('ebooks.upload') }}
            </button>
          </div>
        </form>

        <div v-if="uploading" class="space-y-1">
          <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-indigo-500 rounded-full animate-pulse" style="width:60%"></div>
          </div>
          <p class="text-xs text-center text-gray-500">{{ t('ebooks.uploading_hint') }}</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, watch, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToastStore } from '../store/toast'
import api from '../services/api'
import SearchableSelect from '../components/SearchableSelect.vue'

// ── Cover loading ──────────────────────────────────────────────
// Plain reactive map: ebook.id -> blob URL string (or null while loading)
// We manage blob URLs directly here instead of using the composable
// outside of setup(), which breaks Vue lifecycle hooks.
const coverUrls = reactive({})

onUnmounted(() => {
  // Revoke all blob URLs when the list component unmounts
  Object.values(coverUrls).forEach(url => { if (url) URL.revokeObjectURL(url) })
})

async function loadCoverForEbook(ebook) {
  if (ebook.cover_source === 'default') return
  // Already attempted (null = loading/failed, string = loaded)
  if (ebook.id in coverUrls) return
  coverUrls[ebook.id] = null // mark as in-progress
  try {
    const res = await api.get(`/ebooks/${ebook.id}/cover`, { responseType: 'blob' })
    if (res.data && res.data.size > 200) {
      coverUrls[ebook.id] = URL.createObjectURL(res.data)
    }
  } catch {
    // silent — cover stays null, placeholder shown
  }
}

const { t } = useI18n()
const toastStore = useToastStore()

// View mode (list / grid) — persisted in localStorage
const viewMode = ref(localStorage.getItem('ebooks_view') || 'list')
watch(viewMode, v => localStorage.setItem('ebooks_view', v))

// State
const ebooks = ref([])
const loading = ref(false)
const search = ref('')
const filterFormat = ref('')
const pagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 24 })

// Upload modal
const showUploadModal = ref(false)
const uploadFile = ref(null)
const uploadFileExt = ref('')
const uploading = ref(false)
const uploadError = ref('')
const uploadForm = reactive({ title: '', author: '', total_pages: '', publisher_id: null, publisher_name: '' })
const fileInput = ref(null)

// Writers & publishers for selects
const writers    = ref([])
const publishers = ref([])

async function loadWriters() {
  try { const r = await api.get('/writers');    writers.value    = r.data.data } catch {}
}
async function loadPublishers() {
  try { const r = await api.get('/publishers'); publishers.value = r.data.data } catch {}
}

async function createWriter(name) {
  try {
    const r = await api.post('/writers', { name })
    writers.value.push(r.data.data)
    uploadForm.author = r.data.data.name
    toastStore.success(t('writers.created'))
  } catch { toastStore.error(t('writers.save_failed')) }
}

async function createPublisher(name) {
  try {
    const r = await api.post('/publishers', { name })
    publishers.value.push(r.data.data)
    uploadForm.publisher_name = r.data.data.name
    uploadForm.publisher_id   = r.data.data.id
    toastStore.success(t('publishers.created'))
  } catch { toastStore.error(t('publishers.save_failed')) }
}

// Debounce
let debounceTimer = null
function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => loadEbooks(), 300)
}

async function loadEbooks(page = 1) {
  loading.value = true
  try {
    const params = {
      page,
      per_page: 24,
      ...(search.value && { search: search.value }),
      ...(filterFormat.value && { format: filterFormat.value }),
    }
    const res = await api.get('/ebooks', { params })
    ebooks.value = res.data.data
    pagination.value = res.data.pagination
    // Load covers in parallel (non-blocking)
    ebooks.value.forEach(e => loadCoverForEbook(e))
  } catch (err) {
    toastStore.error(t('ebooks.load_failed'))
  } finally {
    loading.value = false
  }
}

function changePage(p) {
  if (p < 1 || p > pagination.value.last_page) return
  loadEbooks(p)
}

// File handling
function onFileSelect(e) {
  const f = e.target.files[0]
  if (f) setUploadFile(f)
}

function onFileDrop(e) {
  const f = e.dataTransfer.files[0]
  if (f) setUploadFile(f)
}

function setUploadFile(f) {
  const ext = f.name.split('.').pop().toLowerCase()
  if (!['pdf', 'epub', 'mobi'].includes(ext)) {
    uploadError.value = t('ebooks.invalid_format')
    return
  }
  uploadFile.value = f
  uploadFileExt.value = ext
  uploadError.value = ''
}

function clearFile() {
  uploadFile.value = null
  uploadFileExt.value = ''
  if (fileInput.value) fileInput.value.value = ''
}

async function submitUpload() {
  if (!uploadFile.value) return
  uploading.value = true
  uploadError.value = ''

  const formData = new FormData()
  formData.append('file', uploadFile.value)
  if (uploadForm.title)        formData.append('title',        uploadForm.title)
  if (uploadForm.author)       formData.append('author',       uploadForm.author)
  if (uploadForm.total_pages)  formData.append('total_pages',  uploadForm.total_pages)
  if (uploadForm.publisher_id) formData.append('publisher_id', uploadForm.publisher_id)

  try {
    const res = await api.post('/ebooks/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 120000,
    })
    toastStore.success(t('ebooks.upload_success'))
    showUploadModal.value = false
    clearFile()
    Object.assign(uploadForm, { title: '', author: '', total_pages: '', publisher_id: null, publisher_name: '' })

    // If metadata is incomplete, go to detail to fill it in
    if (!res.data.metadata_complete) {
      toastStore.info?.(t('ebooks.fill_metadata'))
    }
    loadEbooks()
  } catch (err) {
    uploadError.value = err.response?.data?.error || t('ebooks.upload_failed')
  } finally {
    uploading.value = false
  }
}

// Helpers
function formatIcon(ext) {
  return { pdf: '📄', epub: '📖', mobi: '📚' }[ext] || '📁'
}

function formatBadgeClass(fmt) {
  return {
    pdf:  'bg-red-100 text-red-700',
    epub: 'bg-green-100 text-green-700',
    mobi: 'bg-blue-100 text-blue-700',
  }[fmt] || 'bg-gray-100 text-gray-700'
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}

function onCoverError(e) {
  e.target.style.display = 'none'
}

onMounted(() => {
  loadEbooks()
  loadWriters()
  loadPublishers()
})
</script>

<style scoped>
.input-field {
  @apply w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none;
}
.filter-select {
  @apply px-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500 bg-white text-gray-700 cursor-pointer;
}
.btn-primary {
  @apply flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed;
}
.btn-ghost {
  @apply px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors;
}
.btn-page {
  @apply px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed;
}
.label {
  @apply block text-sm font-medium text-gray-700 mb-1;
}
</style>
