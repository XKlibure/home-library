<template>
  <div class="fixed inset-0 bg-gray-950 z-50 flex flex-col select-none">

    <!-- ── Top bar ────────────────────────────────────────────── -->
    <div class="flex items-center justify-between px-4 py-2 bg-gray-900 border-b border-gray-700 text-white shrink-0">
      <button @click="goBack" class="text-sm text-gray-300 hover:text-white flex items-center gap-1">
        ← {{ t('back') }}
      </button>

      <span class="text-sm font-medium truncate max-w-xs text-gray-200">{{ ebook?.title }}</span>

      <div class="flex items-center gap-3 text-xs text-gray-400">
        <span v-if="totalPages > 0">
          {{ currentPage }} / {{ totalPages }}
        </span>
        <span v-if="totalPages > 0" class="text-indigo-400 font-semibold">
          {{ percentage }}%
        </span>
        <span v-if="saving" class="text-gray-500 italic">{{ t('ebooks.saving_progress') }}</span>

        <!-- Fullscreen toggle -->
        <button
          @click="toggleFullscreen"
          class="p-1.5 rounded-lg hover:bg-gray-700 transition-colors text-gray-400 hover:text-white"
          :title="isFullscreen ? t('ebooks.exit_fullscreen') : t('ebooks.enter_fullscreen')"
        >
          <!-- Exit fullscreen -->
          <svg v-if="isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 9V4M9 9H4M15 9h5M15 9V4M9 15v5M9 15H4M15 15h5M15 15v5"/>
          </svg>
          <!-- Enter fullscreen -->
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- ── Loading ────────────────────────────────────────────── -->
    <div v-if="loadingFile" class="flex-1 flex flex-col items-center justify-center text-white gap-4">
      <div class="w-10 h-10 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
      <p class="text-sm text-gray-400">{{ t('ebooks.loading_reader') }}</p>
    </div>

    <!-- ── Error ──────────────────────────────────────────────── -->
    <div v-else-if="loadError" class="flex-1 flex flex-col items-center justify-center text-white gap-4">
      <span class="text-5xl">⚠️</span>
      <p class="text-red-400">{{ loadError }}</p>
      <button @click="goBack" class="px-4 py-2 bg-gray-700 rounded-lg text-sm hover:bg-gray-600">
        {{ t('back') }}
      </button>
    </div>

    <!-- ── MOBI: no in-browser reader ────────────────────────── -->
    <div v-else-if="format === 'mobi'" class="flex-1 flex flex-col items-center justify-center text-white gap-4 px-4">
      <span class="text-6xl">📚</span>
      <p class="text-lg font-semibold">{{ ebook?.title }}</p>
      <p class="text-gray-400 text-sm text-center max-w-sm">{{ t('ebooks.mobi_no_reader') }}</p>
      <button @click="downloadMobi" class="px-5 py-2 bg-indigo-600 rounded-lg hover:bg-indigo-700 text-sm font-medium">
        📥 {{ t('ebooks.download_to_open') }}
      </button>
    </div>

    <!-- ── PDF reader ─────────────────────────────────────────── -->
    <div v-else-if="format === 'pdf'" class="flex-1 flex flex-col overflow-hidden">
      <!-- Scrollable canvas area -->
      <div
        ref="pdfScrollArea"
        class="flex-1 overflow-y-auto overflow-x-hidden flex justify-center bg-gray-950 py-4"
      >
        <canvas
          ref="pdfCanvas"
          class="shadow-2xl max-w-full"
          style="display: block;"
        ></canvas>
      </div>

      <!-- Bottom nav bar -->
      <div class="shrink-0 flex items-center justify-between px-6 py-3 bg-gray-900 border-t border-gray-700 text-white">
        <button
          @click="prevPage"
          :disabled="currentPage <= 1"
          class="px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 disabled:opacity-30 disabled:cursor-not-allowed text-sm font-medium"
        >
          ← {{ t('books.previous') }}
        </button>

        <!-- Jump to page -->
        <div class="flex items-center gap-2 text-sm">
          <span class="text-gray-400">{{ t('ebooks.go_to_page') }}</span>
          <input
            v-model.number="jumpInput"
            @keyup.enter="jumpToPage"
            type="number"
            :min="1"
            :max="totalPages"
            class="w-16 text-center bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          <span class="text-gray-500">/ {{ totalPages }}</span>
        </div>

        <button
          @click="nextPage"
          :disabled="currentPage >= totalPages"
          class="px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 disabled:opacity-30 disabled:cursor-not-allowed text-sm font-medium"
        >
          {{ t('books.next') }} →
        </button>
      </div>

      <!-- Progress bar -->
      <div class="h-1 bg-gray-800 shrink-0">
        <div
          class="h-full bg-indigo-500 transition-all duration-300"
          :style="{ width: percentage + '%' }"
        ></div>
      </div>
    </div>

    <!-- ── EPUB reader ────────────────────────────────────────── -->
    <div v-else-if="format === 'epub'" class="flex-1 flex flex-col overflow-hidden bg-white">
      <div ref="epubArea" class="flex-1 overflow-hidden"></div>

      <!-- Bottom nav bar -->
      <div class="shrink-0 flex items-center justify-between px-6 py-3 bg-gray-100 border-t border-gray-200 text-gray-700">
        <button
          @click="epubPrev"
          class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-sm font-medium"
        >
          ← {{ t('books.previous') }}
        </button>
        <span class="text-sm text-gray-500">{{ percentage }}%</span>
        <button
          @click="epubNext"
          class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-sm font-medium"
        >
          {{ t('books.next') }} →
        </button>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useToastStore } from '../store/toast'
// PDF.js — static import so Vite bundles the worker as a local asset.
// This avoids any CDN dependency and works on local networks / offline.
import * as pdfjsLib from 'pdfjs-dist'
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl

const { t }     = useI18n()
const route     = useRoute()
const router    = useRouter()
const toastStore = useToastStore()

// ── State ──────────────────────────────────────────────────────
const ebook       = ref(null)
const format      = computed(() => ebook.value?.file_format)
const loadingFile = ref(true)
const loadError   = ref('')

// Fullscreen
const isFullscreen = ref(false)

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen().catch(() => {})
  } else {
    document.exitFullscreen()
  }
}

function onFullscreenChange() {
  isFullscreen.value = !!document.fullscreenElement
  // Re-render current PDF page so it fills the new viewport size
  if (format.value === 'pdf' && pdfDoc && currentPage.value > 0) {
    renderPage(currentPage.value)
  }
}

// Keyboard shortcuts
function onKeydown(e) {
  // Don't intercept if typing in an input
  if (e.target.tagName === 'INPUT') return

  switch (e.key) {
    case 'f':
    case 'F':
      e.preventDefault()
      toggleFullscreen()
      break
    case 'ArrowRight':
    case 'ArrowDown':
    case ' ':
      e.preventDefault()
      if (format.value === 'pdf')  nextPage()
      if (format.value === 'epub') epubNext()
      break
    case 'ArrowLeft':
    case 'ArrowUp':
      e.preventDefault()
      if (format.value === 'pdf')  prevPage()
      if (format.value === 'epub') epubPrev()
      break
    case 'Escape':
      if (!document.fullscreenElement) goBack()
      break
  }
}

// Progress
const currentPage = ref(0)
const totalPages  = ref(0)
const percentage  = computed(() => {
  if (!totalPages.value) return 0
  return Math.round((currentPage.value / totalPages.value) * 100)
})
const saving = ref(false)

// PDF
const pdfCanvas   = ref(null)
const pdfScrollArea = ref(null)
const jumpInput   = ref(1)
let pdfDoc        = null

// EPUB
const epubArea    = ref(null)
let epubBook      = null
let epubRendition = null

// File blob URL
let fileBlobUrl   = null

// Debounce timer for auto-saving progress
let saveTimer = null

// ── Load ───────────────────────────────────────────────────────

onMounted(async () => {
  document.addEventListener('fullscreenchange', onFullscreenChange)
  document.addEventListener('keydown', onKeydown)
  try {
    // Load ebook metadata
    const meta = await api.get(`/ebooks/${route.params.id}`)
    ebook.value = meta.data.data

    // Restore previously saved page
    currentPage.value = ebook.value.current_page || 1
    totalPages.value  = ebook.value.total_pages  || 0
    jumpInput.value   = currentPage.value

    // Fetch file as blob (authenticated)
    const res = await api.get(`/ebooks/${route.params.id}/open`, {
      responseType: 'blob',
      timeout: 0,
    })

    const mimeMap = {
      pdf:  'application/pdf',
      epub: 'application/epub+zip',
      mobi: 'application/x-mobipocket-ebook',
    }
    const mime = mimeMap[ebook.value.file_format] || 'application/octet-stream'
    fileBlobUrl = URL.createObjectURL(new Blob([res.data], { type: mime }))

    loadingFile.value = false

    // Init reader based on format
    if (ebook.value.file_format === 'pdf') {
      await initPdf()
    } else if (ebook.value.file_format === 'epub') {
      await initEpub()
    }

  } catch (err) {
    loadingFile.value = false
    loadError.value = err.response?.data?.error || t('ebooks.open_failed')
  }
})

onUnmounted(() => {
  clearTimeout(saveTimer)
  document.removeEventListener('fullscreenchange', onFullscreenChange)
  document.removeEventListener('keydown', onKeydown)
  // Exit fullscreen if still active when navigating away
  if (document.fullscreenElement) document.exitFullscreen().catch(() => {})
  if (fileBlobUrl) URL.revokeObjectURL(fileBlobUrl)
  if (epubBook) epubBook.destroy()
})

// ── PDF ────────────────────────────────────────────────────────

async function initPdf() {
  // pdfjsLib + workerSrc already configured via static imports at module level
  pdfDoc = await pdfjsLib.getDocument(fileBlobUrl).promise

  // Update total pages in DB if not yet set
  const detectedPages = pdfDoc.numPages
  if (!totalPages.value || totalPages.value !== detectedPages) {
    totalPages.value = detectedPages
    // Persist total pages silently
    api.put(`/ebooks/${route.params.id}`, { total_pages: detectedPages }).catch(() => {})
  }

  // Start from saved page (or page 1)
  await renderPage(Math.max(1, Math.min(currentPage.value, totalPages.value)))
}

async function renderPage(pageNum) {
  if (!pdfDoc) return
  pageNum = Math.max(1, Math.min(pageNum, pdfDoc.numPages))

  const page    = await pdfDoc.getPage(pageNum)
  const canvas  = pdfCanvas.value
  const ctx     = canvas.getContext('2d')

  // Fit to 90% of scroll area width
  const containerWidth = pdfScrollArea.value?.clientWidth || window.innerWidth
  const targetWidth    = Math.min(containerWidth * 0.9, 900)
  const viewport       = page.getViewport({ scale: 1 })
  const scale          = targetWidth / viewport.width
  const scaled         = page.getViewport({ scale })

  canvas.width  = scaled.width
  canvas.height = scaled.height

  await page.render({ canvasContext: ctx, viewport: scaled }).promise

  // Update state
  currentPage.value = pageNum
  jumpInput.value   = pageNum

  // Scroll to top of canvas area
  if (pdfScrollArea.value) pdfScrollArea.value.scrollTop = 0

  // Schedule auto-save (debounced 1.5 s)
  scheduleSave()
}

async function nextPage() {
  if (currentPage.value < totalPages.value) {
    await renderPage(currentPage.value + 1)
  }
}

async function prevPage() {
  if (currentPage.value > 1) {
    await renderPage(currentPage.value - 1)
  }
}

async function jumpToPage() {
  const n = parseInt(jumpInput.value)
  if (!isNaN(n)) await renderPage(n)
}

// ── EPUB ───────────────────────────────────────────────────────

async function initEpub() {
  const Epub = (await import('epubjs')).default
  epubBook = new Epub(fileBlobUrl)

  epubRendition = epubBook.renderTo(epubArea.value, {
    width:  '100%',
    height: '100%',
    flow:   'paginated',
  })

  // Restore saved position (epub.js uses CFI strings; use percentage as fallback)
  const savedPct = totalPages.value
    ? (currentPage.value / totalPages.value)
    : 0

  await epubRendition.display(savedPct > 0 ? epubBook.locations.cfiFromPercentage(savedPct) : undefined)
    .catch(() => epubRendition.display())

  // Track page changes
  epubRendition.on('relocated', location => {
    const pct       = location.start.percentage || 0
    const estimatedPage = Math.max(1, Math.round(pct * (totalPages.value || 100)))
    currentPage.value   = estimatedPage
    scheduleSave()
  })

  // Generate locations for accurate percentage
  await epubBook.locations.generate(1000).catch(() => {})
}

function epubPrev() { epubRendition?.prev() }
function epubNext() { epubRendition?.next() }

// ── MOBI ───────────────────────────────────────────────────────

async function downloadMobi() {
  if (!fileBlobUrl) return
  const a = document.createElement('a')
  a.href     = fileBlobUrl
  a.download = ebook.value.file_name || `ebook.mobi`
  a.style.display = 'none'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

// ── Progress auto-save ─────────────────────────────────────────

function scheduleSave() {
  clearTimeout(saveTimer)
  saveTimer = setTimeout(saveProgress, 1500)
}

async function saveProgress() {
  if (!ebook.value || currentPage.value < 1) return
  saving.value = true
  try {
    await api.post(`/ebooks/${route.params.id}/progress`, {
      current_page: currentPage.value,
    })
  } catch {
    // Silent fail — progress save is non-critical
  } finally {
    saving.value = false
  }
}

// ── Navigation ─────────────────────────────────────────────────

function goBack() {
  // Save immediately before leaving
  clearTimeout(saveTimer)
  saveProgress().finally(() => router.push(`/ebooks/${route.params.id}`))
}
</script>
