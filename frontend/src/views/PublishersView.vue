<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">🏢 {{ t('publishers.title') }}</h1>
      <button v-if="isAdmin" @click="openCreateModal" class="btn-primary">➕ {{ t('publishers.add') }}</button>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
      <input v-model="searchQuery" @input="debouncedSearch" type="text"
             :placeholder="t('publishers.search_placeholder')" class="input-field max-w-md" />
    </div>

    <!-- Publishers List -->
    <div v-if="publishers.length === 0" class="text-center py-12 bg-white rounded-xl">
      <span class="text-6xl">🏢</span>
      <p class="mt-4 text-gray-500">{{ t('publishers.no_publishers') }}</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="pub in publishers" :key="pub.id"
           class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow">
        <div class="flex justify-between items-start">
          <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-gray-900">{{ pub.name }}</h3>
            <p v-if="pub.name_ar" class="text-sm text-gray-600" dir="rtl">{{ pub.name_ar }}</p>
            <p v-if="pub.city || pub.country" class="text-xs text-gray-500 mt-1">
              🌍 {{ [pub.city, pub.country].filter(Boolean).join(', ') }}
            </p>
            <p v-if="pub.website" class="text-xs text-blue-500 mt-1 truncate">🔗 {{ pub.website }}</p>
          </div>
          <span class="text-xs bg-primary-50 text-primary-700 px-2 py-1 rounded-full flex-shrink-0">
            {{ pub.books_count || 0 }} {{ t('nav.books') }}
          </span>
        </div>
        <div v-if="isAdmin" class="mt-3 flex space-x-2 rtl:space-x-reverse">
          <button @click="openEditModal(pub)" class="text-xs text-blue-600 hover:text-blue-800">✏️ {{ t('edit') }}</button>
          <button @click="deletePublisher(pub)" class="text-xs text-red-600 hover:text-red-800">🗑️ {{ t('delete') }}</button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-semibold">
          {{ editing ? '✏️ ' + t('publishers.edit') : '➕ ' + t('publishers.add') }}
        </h2>
        <form @submit.prevent="savePublisher" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="label">{{ t('publishers.name_en') }} *</label>
              <input v-model="form.name" type="text" required class="input-field" />
            </div>
            <div>
              <label class="label">{{ t('publishers.name_ar') }}</label>
              <input v-model="form.name_ar" type="text" class="input-field" dir="rtl" />
            </div>
            <div>
              <label class="label">{{ t('publishers.name_fr') }}</label>
              <input v-model="form.name_fr" type="text" class="input-field" />
            </div>
            <div class="md:col-span-2">
              <label class="label">{{ t('publishers.address') }}</label>
              <input v-model="form.address" type="text" class="input-field" />
            </div>
            <div>
              <label class="label">{{ t('publishers.city') }}</label>
              <input v-model="form.city" type="text" class="input-field" />
            </div>
            <div>
              <label class="label">{{ t('publishers.country') }}</label>
              <input v-model="form.country" type="text" class="input-field" />
            </div>
            <div>
              <label class="label">{{ t('publishers.phone') }}</label>
              <input v-model="form.phone" type="text" class="input-field" />
            </div>
            <div>
              <label class="label">{{ t('publishers.email') }}</label>
              <input v-model="form.email" type="email" class="input-field" />
            </div>
            <div class="md:col-span-2">
              <label class="label">{{ t('publishers.website') }}</label>
              <input v-model="form.website" type="url" class="input-field" placeholder="https://" />
            </div>
          </div>
          <div class="flex justify-end space-x-3 rtl:space-x-reverse">
            <button type="button" @click="showModal = false" class="px-4 py-2 border rounded-lg">{{ t('cancel') }}</button>
            <button type="submit" class="btn-primary">{{ editing ? t('save') : t('create') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useAuthStore } from '../store/auth'
import { useToastStore } from '../store/toast'

const { t } = useI18n()
const authStore = useAuthStore()
const toastStore = useToastStore()
const isAdmin = computed(() => authStore.user?.role === 'admin')

const publishers = ref([])
const showModal = ref(false)
const editing = ref(null)
const searchQuery = ref('')

const form = reactive({ name: '', name_ar: '', name_fr: '', address: '', city: '', country: '', phone: '', email: '', website: '' })

let searchTimeout = null
function debouncedSearch() { clearTimeout(searchTimeout); searchTimeout = setTimeout(loadPublishers, 300) }

async function loadPublishers() {
  try {
    const params = searchQuery.value ? { search: searchQuery.value } : {}
    const response = await api.get('/publishers', { params })
    publishers.value = response.data.data
  } catch (err) { console.error(err) }
}

function openCreateModal() {
  editing.value = null
  Object.assign(form, { name: '', name_ar: '', name_fr: '', address: '', city: '', country: '', phone: '', email: '', website: '' })
  showModal.value = true
}

function openEditModal(pub) {
  editing.value = pub
  Object.assign(form, { name: pub.name||'', name_ar: pub.name_ar||'', name_fr: pub.name_fr||'', address: pub.address||'', city: pub.city||'', country: pub.country||'', phone: pub.phone||'', email: pub.email||'', website: pub.website||'' })
  showModal.value = true
}

async function savePublisher() {
  try {
    if (editing.value) {
      await api.put(`/publishers/${editing.value.id}`, form)
      toastStore.success(t('publishers.updated'))
    } else {
      await api.post('/publishers', form)
      toastStore.success(t('publishers.created'))
    }
    showModal.value = false
    loadPublishers()
  } catch (err) { toastStore.error(err.response?.data?.error || t('publishers.save_failed')) }
}

async function deletePublisher(pub) {
  if (!confirm(t('publishers.delete_confirm', { name: pub.name }))) return
  try {
    await api.delete(`/publishers/${pub.id}`)
    toastStore.success(t('publishers.deleted'))
    loadPublishers()
  } catch (err) { toastStore.error(err.response?.data?.error || t('publishers.delete_failed')) }
}

onMounted(loadPublishers)
</script>

<style scoped>
.input-field { @apply w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none text-sm; }
.btn-primary { @apply px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition-colors text-sm; }
.label { @apply block text-sm font-medium text-gray-700 mb-1; }
</style>
