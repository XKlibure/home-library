<template>
  <div class="space-y-4">

    <!-- ── Page header ─────────────────────────────────────────── -->
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-gray-900">{{ t('books.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">
          {{ t('books.books_found', { count: pagination.total }) }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <router-link v-if="canScan" to="/books/scan" class="btn-ghost">
          📷 <span class="hidden sm:inline">{{ t('scan.scan_book') }}</span>
        </router-link>
        <router-link to="/books/add" class="btn-primary">
          ➕ <span class="hidden sm:inline">{{ t('books.add_book') }}</span>
        </router-link>
      </div>
    </div>

    <!-- ── Compact filter + toggle bar ─────────────────────────── -->
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex flex-wrap items-center gap-3">
      <!-- Search -->
      <div class="relative flex-1 min-w-48">
        <span class="absolute ltr:left-3 rtl:right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">🔍</span>
        <input
          v-model="filters.search"
          @input="debouncedSearch"
          type="text"
          :placeholder="t('books.search_placeholder')"
          class="w-full ltr:pl-8 rtl:pr-8 pr-3 pl-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
      </div>

      <!-- Quick filters -->
      <select v-model="filters.language" @change="resetAndLoad" class="filter-select">
        <option value="">{{ t('books.all_languages') }}</option>
        <option value="arabic">{{ t('languages.arabic') }}</option>
        <option value="english">{{ t('languages.english') }}</option>
        <option value="french">{{ t('languages.french') }}</option>
        <option value="other">{{ t('languages.other') }}</option>
      </select>

      <select v-model="filters.genre" @change="resetAndLoad" class="filter-select">
        <option value="">{{ t('books.all_genres') }}</option>
        <option v-for="g in genres" :key="g.id" :value="g.name">{{ g.name }}</option>
      </select>

      <select v-model="filters.read_status" @change="resetAndLoad" class="filter-select hidden md:block">
        <option value="">{{ t('books.all_status') }}</option>
        <option value="true">✅ {{ t('books.read') }}</option>
        <option value="false">📖 {{ t('books.unread') }}</option>
      </select>

      <!-- Spacer -->
      <div class="flex-1 hidden lg:block" />

      <!-- Sort -->
      <select v-model="filters.sort_by" @change="resetAndLoad" class="filter-select hidden lg:block">
        <option value="created_at">{{ t('books.recently_added') }}</option>
        <option value="title">{{ t('books.title_label') }}</option>
        <option value="author">{{ t('books.author_label') }}</option>
        <option value="publication_year">{{ t('books.publication_year') }}</option>
      </select>

      <!-- List / Grid toggle -->
      <div class="flex items-center bg-gray-100 rounded-lg p-0.5 gap-0.5">
        <button
          @click="viewMode = 'list'"
          :class="['p-1.5 rounded-md text-sm transition-colors', viewMode === 'list' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700']"
          title="List view"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <button
          @click="viewMode = 'grid'"
          :class="['p-1.5 rounded-md text-sm transition-colors', viewMode === 'grid' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700']"
          title="Grid view"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h7v7H4zM13 6h7v7h-7zM4 15h7v7H4zM13 15h7v7h-7z"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- ── Loading ──────────────────────────────────────────────── -->
    <div v-if="loading" class="text-center py-16">
      <div class="inline-block w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- ── Empty ────────────────────────────────────────────────── -->
    <div v-else-if="books.length === 0" class="text-center py-20 bg-white rounded-xl border border-gray-200">
      <div class="text-5xl mb-3">📭</div>
      <p class="text-gray-500 text-sm">{{ t('books.no_books') }}</p>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         LIST VIEW
    ════════════════════════════════════════════════════════════ -->
    <div v-else-if="viewMode === 'list'" class="bg-white rounded-xl border border-gray-200 overflow-hidden divide-y divide-gray-100">
      <div
        v-for="book in books"
        :key="book.id"
        @click="$router.push(`/books/${book.id}`)"
        class="group flex gap-4 px-4 py-3.5 hover:bg-blue-50/40 cursor-pointer transition-colors"
      >
        <!-- Cover thumbnail -->
        <div class="w-10 h-14 shrink-0 rounded-md overflow-hidden bg-gradient-to-br flex items-center justify-center shadow-sm"
             :class="bookGradient(book)">
          <img
            v-if="book.cover_image_url"
            :src="book.cover_image_url"
            :alt="book.title"
            class="w-full h-full object-cover"
            @error="e => e.target.style.display = 'none'"
          />
          <span v-else class="text-lg">📕</span>
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start gap-2 flex-wrap">
            <span
              class="font-semibold text-gray-900 text-sm leading-snug truncate max-w-sm"
              :dir="book.language === 'arabic' ? 'rtl' : 'ltr'"
            >{{ book.title }}</span>
            <span v-if="book.is_borrowed" class="shrink-0 text-xs bg-orange-100 text-orange-600 px-1.5 py-0.5 rounded-full font-medium">
              📤 {{ t('books.lent') }}
            </span>
          </div>
          <p class="text-xs text-gray-500 mt-0.5 truncate" :dir="book.language === 'arabic' ? 'rtl' : 'ltr'">
            {{ book.author }}
            <span v-if="book.genre" class="text-gray-400"> · {{ book.genre }}</span>
            <span v-if="book.publication_year" class="text-gray-400"> · {{ book.publication_year }}</span>
          </p>
          <p v-if="book.location_room" class="text-xs text-gray-400 mt-0.5">
            📍 {{ book.location_room }}{{ book.location_shelf ? ' / ' + book.location_shelf : '' }}
          </p>
        </div>

        <!-- Status + language -->
        <div class="shrink-0 flex flex-col items-end gap-1.5 pt-0.5">
          <span :class="['text-xs px-2 py-0.5 rounded-full font-medium',
                         book.read_status ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
            {{ book.read_status ? '✅ ' + t('books.read') : '📖 ' + t('books.unread') }}
          </span>
          <span class="text-xs text-gray-400 hidden sm:block">{{ t('languages.' + book.language) }}</span>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         GRID VIEW
    ════════════════════════════════════════════════════════════ -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div
        v-for="book in books"
        :key="book.id"
        @click="$router.push(`/books/${book.id}`)"
        class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-blue-200 cursor-pointer transition-all"
      >
        <div class="flex gap-3">
          <div class="w-12 h-16 shrink-0 rounded-md overflow-hidden bg-gradient-to-br shadow-sm flex items-center justify-center"
               :class="bookGradient(book)">
            <img v-if="book.cover_image_url" :src="book.cover_image_url" class="w-full h-full object-cover"
                 @error="e => e.target.style.display = 'none'" />
            <span v-else class="text-xl">📕</span>
          </div>
          <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-gray-900 text-sm leading-snug line-clamp-2" :dir="book.language === 'arabic' ? 'rtl' : 'ltr'">
              {{ book.title }}
            </h3>
            <p class="text-xs text-gray-500 mt-1 truncate">{{ book.author }}</p>
          </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-1.5">
          <span :class="['text-xs px-2 py-0.5 rounded-full font-medium',
                         book.read_status ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
            {{ book.read_status ? '✅ ' + t('books.read') : '📖 ' + t('books.unread') }}
          </span>
          <span v-if="book.genre" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ book.genre }}</span>
          <span v-if="book.is_borrowed" class="text-xs bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full">📤</span>
        </div>
      </div>
    </div>

    <!-- ── Pagination ────────────────────────────────────────────── -->
    <div v-if="pagination.last_page > 1" class="flex justify-center items-center gap-2 pt-2">
      <button @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="btn-page">
        ← {{ t('books.previous') }}
      </button>
      <button
        v-for="page in visiblePages" :key="page"
        @click="goToPage(page)"
        :class="['px-3 py-1.5 rounded-lg text-sm border transition-colors',
                 page === pagination.current_page
                   ? 'bg-blue-600 text-white border-blue-600'
                   : 'border-gray-300 hover:bg-gray-50 text-gray-700']"
      >{{ page }}</button>
      <button @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="btn-page">
        {{ t('books.next') }} →
      </button>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useAuthStore } from '../store/auth'

const { t }     = useI18n()
const authStore = useAuthStore()
const canScan   = computed(() => ['admin', 'user'].includes(authStore.user?.role))

// Persist view mode
const viewMode = ref(localStorage.getItem('books_view') || 'list')
watch(viewMode, v => localStorage.setItem('books_view', v))

const books      = ref([])
const genres     = ref([])
const loading    = ref(false)
const pagination = ref({ total: 0, current_page: 1, last_page: 1, per_page: 25 })

const filters = reactive({
  search: '', language: '', genre: '', read_status: '',
  borrowed: '', sort_by: 'created_at', sort_dir: 'DESC', page: 1,
})

const visiblePages = computed(() => {
  const pages = [], cur = pagination.value.current_page, last = pagination.value.last_page
  for (let i = Math.max(1, cur - 2); i <= Math.min(last, cur + 2); i++) pages.push(i)
  return pages
})

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { filters.page = 1; loadBooks() }, 300)
}

function resetAndLoad() { filters.page = 1; loadBooks() }

async function loadBooks() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''))
    const res = await api.get('/books', { params })
    books.value      = res.data.data
    pagination.value = res.data.pagination
  } catch { /* silent */ } finally { loading.value = false }
}

async function loadGenres() {
  try { genres.value = (await api.get('/genres')).data.data } catch {}
}

function goToPage(page) {
  if (page < 1 || page > pagination.value.last_page) return
  filters.page = page; loadBooks()
}

// Cover gradient varies by language for visual variety
const gradients = {
  arabic:  'from-emerald-100 to-teal-200',
  english: 'from-blue-100 to-indigo-200',
  french:  'from-rose-100 to-pink-200',
  other:   'from-amber-100 to-orange-200',
}
function bookGradient(book) {
  return gradients[book.language] || gradients.other
}

onMounted(() => { loadBooks(); loadGenres() })
</script>

<style scoped>
.filter-select {
  @apply px-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500 bg-white text-gray-700 cursor-pointer;
}
.btn-primary {
  @apply flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors;
}
.btn-ghost {
  @apply flex items-center gap-1.5 px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors;
}
.btn-page {
  @apply px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors;
}
</style>
