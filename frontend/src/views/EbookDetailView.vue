<template>
  <div v-if="ebook" class="max-w-4xl mx-auto space-y-6">
    <!-- Back -->
    <router-link to="/ebooks" class="text-sm text-gray-500 hover:text-gray-700 inline-block">← {{ t('ebooks.back_to_ebooks') }}</router-link>

    <!-- Main card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <div class="flex flex-col md:flex-row gap-6">

        <!-- Cover with change overlay -->
        <div class="flex-shrink-0 w-36 md:w-44 mx-auto md:mx-0">
          <div
            class="relative rounded-xl overflow-hidden shadow aspect-[2/3] bg-gradient-to-br from-indigo-100 to-purple-200 flex items-center justify-center group"
          >
            <!-- Actual cover image -->
            <img
              v-if="coverBlobUrl"
              :src="coverBlobUrl"
              :alt="ebook.title"
              class="w-full h-full object-cover"
            />
            <!-- Loading skeleton -->
            <div v-else-if="coverLoading" class="w-full h-full animate-pulse bg-indigo-200"></div>
            <!-- Placeholder (no cover) -->
            <div v-else class="flex flex-col items-center text-indigo-400 text-center p-3">
              <span class="text-5xl">{{ formatIcon(ebook.file_format) }}</span>
              <span class="text-sm font-semibold uppercase mt-2">{{ ebook.file_format }}</span>
            </div>

            <!-- Change cover overlay (visible on hover, always visible on mobile) -->
            <button
              v-if="canEdit"
              @click="openCoverModal"
              class="absolute inset-0 bg-black/50 flex flex-col items-center justify-center text-white
                     opacity-0 group-hover:opacity-100 transition-opacity duration-200
                     sm:opacity-0 active:opacity-100"
            >
              <span class="text-2xl mb-1">📷</span>
              <span class="text-xs font-medium">{{ t('ebooks.change_cover') }}</span>
            </button>
          </div>

          <!-- Change cover button (always visible below cover for clarity) -->
          <button
            v-if="canEdit"
            @click="openCoverModal"
            class="mt-2 w-full text-xs text-indigo-600 hover:text-indigo-800 font-medium py-1"
          >
            📷 {{ t('ebooks.change_cover') }}
          </button>

          <!-- Cover source badge -->
          <p class="text-center text-xs text-gray-400 mt-1">
            {{ coverSourceLabel }}
          </p>
        </div>

        <!-- Details -->
        <div class="flex-1 space-y-4">
          <!-- Title / Author (view mode) -->
          <div v-if="!editingMeta">
            <h1 class="text-2xl font-bold text-gray-900">{{ ebook.title }}</h1>
            <p class="text-lg text-gray-600 mt-1">{{ ebook.author || t('ebooks.unknown_author') }}</p>

            <!-- Metadata incomplete warning -->
            <div v-if="!ebook.metadata_complete" class="mt-3 bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3 text-sm text-yellow-800 flex items-start gap-2">
              <span>⚠️</span>
              <div>
                <strong>{{ t('ebooks.metadata_incomplete') }}</strong>
                <p class="text-xs mt-0.5">{{ t('ebooks.metadata_incomplete_hint') }}</p>
                <button v-if="canEdit" @click="startEditMeta" class="mt-1 text-yellow-700 underline text-xs">{{ t('ebooks.fill_now') }}</button>
              </div>
            </div>
          </div>

          <!-- Inline meta edit -->
          <div v-else class="space-y-3">
            <div>
              <label class="label">{{ t('ebooks.title_label') }}</label>
              <input v-model="metaForm.title" type="text" class="input-field" />
            </div>
            <div>
              <label class="label">{{ t('ebooks.author_label') }}</label>
              <SearchableSelect
                v-model="metaForm.author"
                :items="writers"
                :placeholder="t('ebooks.author_placeholder')"
                :search-placeholder="t('ebooks.search_writer')"
                :create-label="t('ebooks.add_writer')"
                @create="createWriterInline"
              />
            </div>
            <div>
              <label class="label">{{ t('ebooks.publisher_label') }}</label>
              <SearchableSelect
                v-model="metaForm.publisher_name"
                :items="publishers"
                :placeholder="t('ebooks.publisher_placeholder')"
                :search-placeholder="t('ebooks.search_publisher')"
                :create-label="t('ebooks.add_publisher')"
                @select="item => metaForm.publisher_id = item?.id || null"
                @create="createPublisherInline"
              />
            </div>
            <div>
              <label class="label">{{ t('ebooks.total_pages_label') }}</label>
              <input v-model.number="metaForm.total_pages" type="number" min="0" class="input-field" />
            </div>
            <div class="flex gap-2">
              <button @click="saveMeta" :disabled="savingMeta" class="btn-primary">
                {{ savingMeta ? t('loading') : t('save') }}
              </button>
              <button @click="editingMeta = false" class="btn-secondary">{{ t('cancel') }}</button>
            </div>
          </div>

          <!-- Badges -->
          <div class="flex flex-wrap gap-2">
            <span :class="['px-3 py-1 rounded-full text-sm font-medium uppercase', formatBadgeClass(ebook.file_format)]">
              {{ ebook.file_format }}
            </span>
            <span class="px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-600">
              🌐 {{ t('ebooks.location_local') }}
            </span>
            <span v-if="ebook.linked_book_title" class="px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-700">
              📚 {{ ebook.linked_book_title }}
            </span>
            <span v-if="ebook.publisher_name" class="px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-600">
              🏢 {{ ebook.publisher_name }}
            </span>
          </div>

          <!-- File info grid -->
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div v-if="ebook.file_size">
              <p class="text-xs text-gray-500">{{ t('ebooks.file_size') }}</p>
              <p class="font-medium">{{ formatFileSize(ebook.file_size) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">{{ t('ebooks.format') }}</p>
              <p class="font-medium uppercase">{{ ebook.file_format }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">{{ t('ebooks.added_on') }}</p>
              <p class="font-medium">{{ formatDate(ebook.created_at) }}</p>
            </div>
          </div>

          <!-- Action buttons -->
          <div class="flex flex-wrap gap-2 pt-2">
            <button @click="router.push(`/ebooks/${route.params.id}/read`)" class="btn-primary flex items-center gap-2">
              📖 {{ t('ebooks.read_book') }}
            </button>
            <button v-if="canEdit && !editingMeta" @click="startEditMeta" class="btn-secondary">
              ✏️ {{ t('edit') }}
            </button>
            <button v-if="canEdit" @click="confirmDelete" class="px-3 py-2 text-sm text-red-600 border border-red-300 rounded-lg hover:bg-red-50">
              🗑️ {{ t('delete') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reading Progress Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-4">📊 {{ t('ebooks.reading_progress') }}</h2>
      <div class="space-y-4">
        <div>
          <div class="flex justify-between text-sm text-gray-600 mb-2">
            <span>{{ t('ebooks.pages_read', { current: ebook.current_page, total: ebook.total_pages || '?' }) }}</span>
            <span class="font-semibold text-indigo-600">{{ ebook.read_percentage }}%</span>
          </div>
          <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
            <div
              class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full transition-all duration-500"
              :style="{ width: ebook.read_percentage + '%' }"
            ></div>
          </div>
        </div>
        <div v-if="ebook.total_pages > 0" class="flex items-end gap-3">
          <div class="flex-1">
            <label class="label text-xs">{{ t('ebooks.current_page_label') }}</label>
            <input v-model.number="progressInput" type="number" :min="0" :max="ebook.total_pages" class="input-field" />
          </div>
          <button @click="saveProgress" :disabled="savingProgress" class="btn-primary mb-0.5">
            {{ savingProgress ? t('loading') : t('ebooks.update_progress') }}
          </button>
        </div>
        <div v-else class="text-sm text-gray-500 bg-gray-50 rounded-lg p-3">
          {{ t('ebooks.set_total_pages_hint') }}
          <button v-if="canEdit" @click="startEditMeta" class="ml-2 text-indigo-600 underline text-xs">{{ t('ebooks.set_now') }}</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Loading spinner -->
  <div v-else class="text-center py-16">
    <div class="animate-spin inline-block w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full"></div>
  </div>

  <!-- =====================================================
       Change Cover Modal
  ====================================================== -->
  <Teleport to="body">
    <div
      v-if="showCoverModal"
      class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4"
      @click.self="closeCoverModal"
    >
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <!-- Modal header -->
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h2 class="text-lg font-semibold text-gray-900">📷 {{ t('ebooks.change_cover') }}</h2>
          <button @click="closeCoverModal" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b">
          <button
            v-for="tab in coverTabs"
            :key="tab.id"
            @click="activeCoverTab = tab.id"
            :class="['flex-1 py-3 text-sm font-medium transition-colors',
                     activeCoverTab === tab.id
                       ? 'text-indigo-600 border-b-2 border-indigo-600'
                       : 'text-gray-500 hover:text-gray-700']"
          >
            {{ tab.icon }} {{ tab.label }}
          </button>
        </div>

        <div class="p-6 space-y-4">

          <!-- ── Tab 1: Upload custom image ── -->
          <div v-if="activeCoverTab === 'upload'">
            <div class="flex gap-4 items-start">
              <!-- Preview -->
              <div class="w-24 h-36 flex-shrink-0 rounded-lg overflow-hidden border border-gray-200 bg-gray-50 flex items-center justify-center">
                <img v-if="coverPreviewUrl" :src="coverPreviewUrl" class="w-full h-full object-cover" alt="preview" />
                <span v-else class="text-gray-300 text-3xl">🖼️</span>
              </div>

              <!-- File picker -->
              <div class="flex-1 space-y-3">
                <div
                  class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center cursor-pointer hover:border-indigo-400 transition-colors"
                  @click="$refs.coverFileInput.click()"
                  @dragover.prevent
                  @drop.prevent="onCoverFileDrop"
                >
                  <input
                    ref="coverFileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    @change="onCoverFileSelect"
                  />
                  <p v-if="!coverFile" class="text-sm text-gray-500">
                    {{ t('ebooks.cover_drag_or_click') }}<br/>
                    <span class="text-xs text-gray-400">JPG, PNG, WEBP · max 5 MB</span>
                  </p>
                  <p v-else class="text-sm font-medium text-gray-700">{{ coverFile.name }}</p>
                </div>

                <p v-if="coverUploadError" class="text-xs text-red-600">{{ coverUploadError }}</p>

                <button
                  @click="submitCoverUpload"
                  :disabled="!coverFile || coverSaving"
                  class="w-full btn-primary"
                >
                  {{ coverSaving ? t('loading') : t('ebooks.save_cover') }}
                </button>
              </div>
            </div>
          </div>

          <!-- ── Tab 2: Re-extract from file ── -->
          <div v-if="activeCoverTab === 'extract'">
            <p class="text-sm text-gray-600 mb-4">{{ t('ebooks.reextract_hint') }}</p>
            <div class="flex items-center gap-3">
              <!-- Current cover mini preview -->
              <div class="w-16 h-24 flex-shrink-0 rounded-lg overflow-hidden border bg-gray-50 flex items-center justify-center">
                <img v-if="coverBlobUrl" :src="coverBlobUrl" class="w-full h-full object-cover" alt="current" />
                <span v-else class="text-2xl">{{ formatIcon(ebook?.file_format) }}</span>
              </div>
              <div class="flex-1">
                <p class="text-xs text-gray-500 mb-3">{{ t('ebooks.current_cover_label') }}: <strong>{{ coverSourceLabel }}</strong></p>
                <button
                  @click="doReextract"
                  :disabled="coverSaving"
                  class="w-full btn-primary"
                >
                  {{ coverSaving ? t('loading') : t('ebooks.reextract_cover') }}
                </button>
              </div>
            </div>
          </div>

          <!-- ── Tab 3: Search online ── -->
          <div v-if="activeCoverTab === 'online'">
            <p class="text-sm text-gray-600 mb-4">{{ t('ebooks.online_cover_hint') }}</p>
            <div class="space-y-3">
              <div>
                <label class="label text-xs">{{ t('ebooks.title_label') }}</label>
                <input v-model="onlineSearchTitle" type="text" class="input-field" />
              </div>
              <div>
                <label class="label text-xs">{{ t('ebooks.author_label') }}</label>
                <input v-model="onlineSearchAuthor" type="text" class="input-field" />
              </div>
              <button
                @click="doOnlineSearch"
                :disabled="coverSaving || !onlineSearchTitle"
                class="w-full btn-primary"
              >
                {{ coverSaving ? t('loading') : t('ebooks.search_cover_online') }}
              </button>
              <p v-if="onlineSearchResult !== null" :class="['text-sm text-center', onlineSearchResult ? 'text-green-600' : 'text-red-500']">
                {{ onlineSearchResult ? t('ebooks.cover_found') : t('ebooks.cover_not_found') }}
              </p>
            </div>
          </div>

        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useToastStore } from '../store/toast'
import { useAuthStore } from '../store/auth'
import { useAuthImage } from '../composables/useAuthImage'
import SearchableSelect from '../components/SearchableSelect.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toastStore = useToastStore()
const authStore = useAuthStore()

// ── E-book data ──
const ebook = ref(null)

// ── Cover (authenticated blob URL) ──
const { blobUrl: coverBlobUrl, loading: coverLoading, load: loadCover, revoke: revokeCover } = useAuthImage()

const coverSourceLabel = computed(() => {
  const src = ebook.value?.cover_source
  return {
    extracted: t('ebooks.cover_source_extracted'),
    fetched:   t('ebooks.cover_source_fetched'),
    custom:    t('ebooks.cover_source_custom'),
    default:   t('ebooks.cover_source_default'),
  }[src] ?? src ?? '—'
})

// ── Meta editing ──
const editingMeta = ref(false)
const savingMeta  = ref(false)
const metaForm    = reactive({ title: '', author: '', total_pages: 0, publisher_id: null, publisher_name: '' })

// Writers & publishers for selects
const writers    = ref([])
const publishers = ref([])

async function loadLists() {
  try { const r = await api.get('/writers');    writers.value    = r.data.data } catch {}
  try { const r = await api.get('/publishers'); publishers.value = r.data.data } catch {}
}

async function createWriterInline(name) {
  try {
    const r = await api.post('/writers', { name })
    writers.value.push(r.data.data)
    metaForm.author = r.data.data.name
    toastStore.success(t('writers.created'))
  } catch { toastStore.error(t('writers.save_failed')) }
}

async function createPublisherInline(name) {
  try {
    const r = await api.post('/publishers', { name })
    publishers.value.push(r.data.data)
    metaForm.publisher_name = r.data.data.name
    metaForm.publisher_id   = r.data.data.id
    toastStore.success(t('publishers.created'))
  } catch { toastStore.error(t('publishers.save_failed')) }
}

// ── Progress ──
const progressInput  = ref(0)
const savingProgress = ref(false)

// ── File open ── (now handled by EbookReaderView)
const opening = ref(false) // kept to avoid template errors during transition

// ── Permissions ──
const canEdit = computed(() => ['admin', 'user'].includes(authStore.user?.role))

// ── Cover modal state ──
const showCoverModal    = ref(false)
const activeCoverTab    = ref('upload')
const coverSaving       = ref(false)
const coverFile         = ref(null)
const coverPreviewUrl   = ref(null)
const coverUploadError  = ref('')
const onlineSearchTitle  = ref('')
const onlineSearchAuthor = ref('')
const onlineSearchResult = ref(null)
const coverFileInput    = ref(null)

const coverTabs = computed(() => [
  { id: 'upload',  icon: '⬆️',  label: t('ebooks.tab_upload_cover') },
  { id: 'extract', icon: '📄',  label: t('ebooks.tab_reextract') },
  { id: 'online',  icon: '🌐',  label: t('ebooks.tab_online') },
])

// ═══════════════════════════════════════════
// Load
// ═══════════════════════════════════════════

async function loadEbook() {
  try {
    const res = await api.get(`/ebooks/${route.params.id}`)
    ebook.value = res.data.data
    progressInput.value = ebook.value.current_page || 0
    // Load cover as authenticated blob
    if (ebook.value.cover_source !== 'default') {
      loadCover(`/ebooks/${route.params.id}/cover`)
    }
  } catch {
    toastStore.error(t('ebooks.load_failed'))
    router.push('/ebooks')
  }
}

// ═══════════════════════════════════════════
// Cover modal
// ═══════════════════════════════════════════

function openCoverModal() {
  onlineSearchTitle.value  = ebook.value?.title  || ''
  onlineSearchAuthor.value = ebook.value?.author || ''
  onlineSearchResult.value = null
  coverFile.value          = null
  coverPreviewUrl.value    = null
  coverUploadError.value   = ''
  activeCoverTab.value     = 'upload'
  showCoverModal.value     = true
}

function closeCoverModal() {
  showCoverModal.value = false
  if (coverPreviewUrl.value) {
    URL.revokeObjectURL(coverPreviewUrl.value)
    coverPreviewUrl.value = null
  }
}

// ── Upload tab ──
function onCoverFileSelect(e) {
  const f = e.target.files[0]
  if (f) setCoverFile(f)
}

function onCoverFileDrop(e) {
  const f = e.dataTransfer.files[0]
  if (f) setCoverFile(f)
}

function setCoverFile(f) {
  const allowed = ['image/jpeg', 'image/png', 'image/webp']
  if (!allowed.includes(f.type)) {
    coverUploadError.value = t('ebooks.cover_invalid_type')
    return
  }
  if (f.size > 5242880) {
    coverUploadError.value = t('ebooks.cover_too_large')
    return
  }
  coverUploadError.value = ''
  coverFile.value = f
  if (coverPreviewUrl.value) URL.revokeObjectURL(coverPreviewUrl.value)
  coverPreviewUrl.value = URL.createObjectURL(f)
}

async function submitCoverUpload() {
  if (!coverFile.value) return
  coverSaving.value = true
  coverUploadError.value = ''
  const form = new FormData()
  form.append('cover', coverFile.value)
  try {
    const res = await api.post(`/ebooks/${route.params.id}/cover`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    ebook.value = res.data.data
    // Reload cover blob
    revokeCover()
    await loadCover(`/ebooks/${route.params.id}/cover`)
    toastStore.success(t('ebooks.cover_updated'))
    closeCoverModal()
  } catch (err) {
    coverUploadError.value = err.response?.data?.error || t('ebooks.cover_update_failed')
  } finally {
    coverSaving.value = false
  }
}

// ── Re-extract tab ──
async function doReextract() {
  coverSaving.value = true
  try {
    const res = await api.post(`/ebooks/${route.params.id}/reextract-cover`)
    if (res.data.found) {
      // Reload ebook data + cover
      await loadEbook()
      toastStore.success(t('ebooks.cover_extracted'))
    } else {
      toastStore.error(t('ebooks.cover_not_found'))
    }
    closeCoverModal()
  } catch {
    toastStore.error(t('ebooks.cover_update_failed'))
  } finally {
    coverSaving.value = false
  }
}

// ── Online search tab ──
async function doOnlineSearch() {
  if (!onlineSearchTitle.value) return
  coverSaving.value = true
  onlineSearchResult.value = null

  // Temporarily update ebook title/author in the request context
  try {
    const res = await api.post(`/ebooks/${route.params.id}/refresh-cover`, {
      title:  onlineSearchTitle.value,
      author: onlineSearchAuthor.value,
    })
    onlineSearchResult.value = res.data.found
    if (res.data.found) {
      await loadEbook()
      closeCoverModal()
      toastStore.success(t('ebooks.cover_found'))
    }
  } catch {
    onlineSearchResult.value = false
    toastStore.error(t('ebooks.cover_update_failed'))
  } finally {
    coverSaving.value = false
  }
}

// ═══════════════════════════════════════════
// Meta editing
// ═══════════════════════════════════════════

function startEditMeta() {
  metaForm.title          = ebook.value.title
  metaForm.author         = ebook.value.author || ''
  metaForm.total_pages    = ebook.value.total_pages || 0
  metaForm.publisher_id   = ebook.value.publisher_id || null
  metaForm.publisher_name = ebook.value.publisher_name || ''
  editingMeta.value = true
}

async function saveMeta() {
  savingMeta.value = true
  try {
    const res = await api.put(`/ebooks/${route.params.id}`, {
      title:        metaForm.title,
      author:       metaForm.author,
      total_pages:  metaForm.total_pages,
      publisher_id: metaForm.publisher_id || null,
    })
    ebook.value = res.data.data
    editingMeta.value = false
    toastStore.success(t('ebooks.updated'))
  } catch (err) {
    toastStore.error(err.response?.data?.error || t('ebooks.update_failed'))
  } finally {
    savingMeta.value = false
  }
}

// ═══════════════════════════════════════════
// Progress
// ═══════════════════════════════════════════

async function saveProgress() {
  savingProgress.value = true
  try {
    const res = await api.post(`/ebooks/${route.params.id}/progress`, {
      current_page: progressInput.value,
    })
    ebook.value.current_page    = res.data.data.current_page
    ebook.value.read_percentage = res.data.data.read_percentage
    toastStore.success(t('ebooks.progress_updated'))
  } catch (err) {
    toastStore.error(err.response?.data?.error || t('ebooks.update_failed'))
  } finally {
    savingProgress.value = false
  }
}

// ═══════════════════════════════════════════
// Delete
// ═══════════════════════════════════════════

async function confirmDelete() {
  if (!confirm(t('ebooks.delete_confirm', { title: ebook.value.title }))) return
  try {
    await api.delete(`/ebooks/${route.params.id}`)
    toastStore.success(t('ebooks.deleted'))
    router.push('/ebooks')
  } catch {
    toastStore.error(t('ebooks.delete_failed'))
  }
}

// ═══════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════

function formatIcon(ext) {
  return { pdf: '📄', epub: '📖', mobi: '📚' }[ext] || '📁'
}

function formatBadgeClass(fmt) {
  return {
    pdf: 'bg-red-100 text-red-700',
    epub: 'bg-green-100 text-green-700',
    mobi: 'bg-blue-100 text-blue-700',
  }[fmt] || 'bg-gray-100 text-gray-700'
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString() : '—'
}

onMounted(() => { loadEbook(); loadLists() })
onUnmounted(revokeCover)
</script>

<style scoped>
.input-field {
  @apply w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none;
}
.btn-primary {
  @apply px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed;
}
.btn-secondary {
  @apply px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors;
}
.label {
  @apply block text-sm font-medium text-gray-700 mb-1;
}
</style>
