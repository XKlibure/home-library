<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">📷 {{ t('scan.title') }}</h1>
      <router-link to="/books" class="text-sm text-gray-500 hover:text-gray-700">{{ t('books.back_to_books') }}</router-link>
    </div>

    <!-- Scan Mode Selection -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <div class="grid grid-cols-2 gap-4">
        <button @click="scanMode = 'cover'" 
                :class="['scan-mode-btn', scanMode === 'cover' ? 'active' : '']">
          <span class="text-3xl">📖</span>
          <span class="text-sm font-medium">{{ t('scan.front_cover') }}</span>
          <span class="text-xs text-gray-500">{{ t('scan.cover_hint') }}</span>
        </button>
        <button @click="scanMode = 'back'" 
                :class="['scan-mode-btn', scanMode === 'back' ? 'active' : '']">
          <span class="text-3xl">📋</span>
          <span class="text-sm font-medium">{{ t('scan.back_page') }}</span>
          <span class="text-xs text-gray-500">{{ t('scan.back_hint') }}</span>
        </button>
      </div>
    </div>

    <!-- Camera / Image Capture -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-800">
        {{ scanMode === 'cover' ? '📖 ' + t('scan.capture_cover') : '📋 ' + t('scan.capture_back') }}
      </h2>

      <!-- Camera preview -->
      <div v-if="showCamera" class="relative">
        <video ref="videoRef" autoplay playsinline class="w-full rounded-lg bg-black"></video>
        <button @click="capturePhoto" class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-white rounded-full p-4 shadow-lg hover:bg-gray-100">
          <span class="text-2xl">📸</span>
        </button>
      </div>

      <!-- Captured image preview -->
      <div v-if="capturedImage" class="relative">
        <img :src="capturedImage" class="w-full rounded-lg" alt="Captured" />
        <button @click="retake" class="absolute top-2 ltr:right-2 rtl:left-2 bg-white rounded-full p-2 shadow">
          ✕
        </button>
      </div>

      <!-- Canvas for capture (hidden) -->
      <canvas ref="canvasRef" class="hidden"></canvas>

      <!-- Analyze / Retake after capture -->
      <div v-if="capturedImage" class="flex gap-3">
        <button @click="analyzeImage" :disabled="analyzing" class="btn-primary flex-1">
          {{ analyzing ? t('scan.analyzing') : '🔍 ' + t('scan.analyze') }}
        </button>
        <button @click="retake" class="btn-secondary flex-1">
          🔄 {{ t('scan.retake') }}
        </button>
      </div>

      <!-- Action buttons (show only when no image captured) -->
      <div v-if="!capturedImage" class="flex flex-col gap-3">
        <!-- Primary: Take photo with camera -->
        <label class="btn-primary flex items-center justify-center cursor-pointer w-full py-3">
          📷 {{ t('scan.take_photo') }}
          <input type="file" accept="image/*" capture="environment" @change="handleFileUpload" class="hidden" />
        </label>
        <!-- Secondary: Browse gallery/files (no capture attribute = opens file picker) -->
        <label class="btn-secondary flex items-center justify-center cursor-pointer w-full py-3">
          📁 {{ t('scan.upload_photo') }}
          <input type="file" accept=".jpg,.jpeg,.png,.webp,.heic" @change="handleFileUpload" class="hidden" />
        </label>
        <!-- Optional: Live camera (only works on HTTPS or localhost) -->
        <button v-if="supportsLiveCamera" @click="startCamera" class="btn-secondary w-full py-3">
          🎥 {{ t('scan.live_camera') }}
        </button>
      </div>
    </div>

    <!-- Results -->
    <div v-if="result" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-800">📝 {{ t('scan.results') }}</h2>

      <!-- Editable parsed fields -->
      <div class="space-y-3">
        <p class="text-xs text-gray-500">{{ t('scan.correct_hint') }}</p>
        <div>
          <label class="label">{{ t('books.title_label') }}</label>
          <input v-model="editableResult.title" type="text" class="input-field" dir="auto"
                 :placeholder="t('books.title_placeholder')" />
        </div>
        <div>
          <label class="label">{{ t('books.author_label') }}</label>
          <input v-model="editableResult.author" type="text" class="input-field" dir="auto"
                 :placeholder="t('books.author_placeholder')" />
        </div>
        <div v-if="scanMode === 'back'" class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">ISBN</label>
            <input v-model="editableResult.isbn" type="text" class="input-field" />
          </div>
          <div>
            <label class="label">{{ t('books.publisher') }}</label>
            <input v-model="editableResult.edition_house" type="text" class="input-field" dir="auto" />
          </div>
          <div>
            <label class="label">{{ t('books.publication_year') }}</label>
            <input v-model="editableResult.publication_year" type="number" class="input-field" />
          </div>
        </div>
      </div>

      <!-- Search button -->
      <button @click="searchBook" class="btn-primary w-full py-3">
        🔍 {{ t('scan.search_library') }}
      </button>

      <!-- Database matches -->
      <div v-if="searchDone && matches.length > 0" class="border-t pt-4">
        <h3 class="text-sm font-semibold text-green-700 mb-2">✅ {{ t('scan.book_found') }}</h3>
        <div v-for="book in matches" :key="book.id" 
             class="flex items-center justify-between p-3 bg-green-50 rounded-lg mb-2 cursor-pointer hover:bg-green-100"
             @click="$router.push(`/books/${book.id}`)">
          <div>
            <p class="font-medium text-gray-900">{{ book.title }}</p>
            <p class="text-sm text-gray-600">{{ book.author }}</p>
          </div>
          <span class="text-sm text-green-600">{{ t('scan.view_book') }} →</span>
        </div>
      </div>

      <!-- Not found — Add book -->
      <div v-if="searchDone && matches.length === 0" class="border-t pt-4">
        <h3 class="text-sm font-semibold text-orange-700 mb-2">📭 {{ t('scan.not_found') }}</h3>
        <p class="text-sm text-gray-600 mb-3">{{ t('scan.not_found_hint') }}</p>
        <button @click="addBook" class="btn-primary w-full">
          ➕ {{ t('scan.add_book') }}
        </button>
      </div>

      <!-- Raw OCR (collapsible for debug) -->
      <details class="text-xs text-gray-400 mt-4">
        <summary class="cursor-pointer">{{ t('scan.raw_ocr') }}</summary>
        <pre class="mt-2 bg-gray-50 p-2 rounded whitespace-pre-wrap">{{ result.extracted_text }}</pre>
      </details>
    </div>

    <!-- Error -->
    <div v-if="errorMsg" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
      {{ errorMsg }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '../services/api'

const { t } = useI18n()
const router = useRouter()

const scanMode = ref('cover')
const showCamera = ref(false)
const capturedImage = ref(null)
const analyzing = ref(false)
const result = ref(null)
const errorMsg = ref('')
const matches = ref([])
const searchDone = ref(false)

// Editable fields the user can correct after OCR
const editableResult = ref({
  title: '',
  author: '',
  isbn: '',
  edition_house: '',
  publication_year: null,
})

// Live camera only works on HTTPS or localhost
const supportsLiveCamera = computed(() => {
  return window.location.protocol === 'https:' || 
         window.location.hostname === 'localhost' || 
         window.location.hostname === '127.0.0.1'
})

const videoRef = ref(null)
const canvasRef = ref(null)
let mediaStream = null

async function startCamera() {
  try {
    errorMsg.value = ''
    mediaStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } }
    })
    showCamera.value = true
    // Wait for DOM update
    setTimeout(() => {
      if (videoRef.value) {
        videoRef.value.srcObject = mediaStream
      }
    }, 100)
  } catch (err) {
    errorMsg.value = t('scan.camera_error')
    console.error('Camera access failed:', err)
  }
}

function capturePhoto() {
  if (!videoRef.value || !canvasRef.value) return

  const video = videoRef.value
  const canvas = canvasRef.value
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  canvas.getContext('2d').drawImage(video, 0, 0)

  capturedImage.value = canvas.toDataURL('image/jpeg', 0.85)
  stopCamera()
}

function handleFileUpload(event) {
  const file = event.target.files[0]
  if (!file) return

  errorMsg.value = ''
  result.value = null

  // Compress image before storing (smaller = faster API + less tokens)
  compressImage(file, 800).then(dataUrl => {
    capturedImage.value = dataUrl
  }).catch(() => {
    // Fallback to raw read if compression fails
    const reader = new FileReader()
    reader.onload = (e) => {
      capturedImage.value = e.target.result
    }
    reader.readAsDataURL(file)
  })

  // Reset the input so same file can be selected again
  event.target.value = ''
}

/**
 * Compress image to max width while maintaining aspect ratio
 */
function compressImage(file, maxWidth) {
  return new Promise((resolve, reject) => {
    const img = new Image()
    img.onload = () => {
      const canvas = document.createElement('canvas')
      let width = img.width
      let height = img.height

      if (width > maxWidth) {
        height = (height * maxWidth) / width
        width = maxWidth
      }

      canvas.width = width
      canvas.height = height
      const ctx = canvas.getContext('2d')
      ctx.drawImage(img, 0, 0, width, height)
      resolve(canvas.toDataURL('image/jpeg', 0.7))
    }
    img.onerror = reject
    img.src = URL.createObjectURL(file)
  })
}

function retake() {
  capturedImage.value = null
  result.value = null
  errorMsg.value = ''
  matches.value = []
  searchDone.value = false
  editableResult.value = { title: '', author: '', isbn: '', edition_house: '', publication_year: null }
}

function stopCamera() {
  showCamera.value = false
  if (mediaStream) {
    mediaStream.getTracks().forEach(track => track.stop())
    mediaStream = null
  }
}

async function analyzeImage() {
  if (!capturedImage.value) return

  analyzing.value = true
  errorMsg.value = ''
  result.value = null
  matches.value = []
  searchDone.value = false

  try {
    const endpoint = scanMode.value === 'cover' ? '/scan/cover' : '/scan/back'
    // Use longer timeout for OCR processing
    const response = await api.post(endpoint, { image: capturedImage.value }, { timeout: 60000 })
    
    // Check if response contains an error field
    if (response.data.error) {
      errorMsg.value = response.data.error
    } else if (response.data.data?.parsed?._error) {
      errorMsg.value = response.data.data.parsed._error
    } else {
      result.value = response.data.data
      // Pre-fill editable fields from OCR result
      const p = result.value.parsed || {}
      editableResult.value = {
        title: p.title || '',
        author: p.author || '',
        isbn: p.isbn || '',
        edition_house: p.edition_house || '',
        publication_year: p.publication_year || null,
      }
      // If OCR found matches automatically, show them
      if (result.value.matches?.length > 0) {
        matches.value = result.value.matches
        searchDone.value = true
      }
    }
  } catch (err) {
    errorMsg.value = err.response?.data?.error || err.message || t('scan.analyze_failed')
    console.error('Scan error:', err)
  } finally {
    analyzing.value = false
  }
}

async function searchBook() {
  if (!editableResult.value.title && !editableResult.value.author && !editableResult.value.isbn) {
    errorMsg.value = t('scan.enter_something')
    return
  }

  try {
    // Search by title/author in the books list
    const params = {}
    if (editableResult.value.title) params.search = editableResult.value.title
    if (editableResult.value.author) params.author = editableResult.value.author
    
    const response = await api.get('/books', { params })
    matches.value = response.data.data || []
    searchDone.value = true
  } catch (err) {
    errorMsg.value = err.response?.data?.error || t('scan.analyze_failed')
  }
}

function addBook() {
  // Pre-fill book form with user-corrected data
  const params = new URLSearchParams()
  const e = editableResult.value
  if (e.title) params.set('title', e.title)
  if (e.author) params.set('author', e.author)
  if (e.isbn) params.set('isbn', e.isbn)
  if (e.edition_house) params.set('edition_house', e.edition_house)
  if (e.publication_year) params.set('publication_year', e.publication_year)
  router.push(`/books/add?${params.toString()}`)
}

onUnmounted(stopCamera)
</script>

<style scoped>
.scan-mode-btn {
  @apply flex flex-col items-center justify-center p-4 rounded-lg border-2 border-gray-200 transition-all cursor-pointer hover:border-primary-300;
}
.scan-mode-btn.active {
  @apply border-primary-500 bg-primary-50;
}
.btn-primary {
  @apply px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition-colors text-sm disabled:opacity-50;
}
.btn-secondary {
  @apply px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors text-sm;
}
.input-field {
  @apply w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none text-sm;
}
.label {
  @apply block text-sm font-medium text-gray-700 mb-1;
}
</style>
