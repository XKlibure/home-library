<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">📍 {{ t('locations.title') }}</h1>
      <button v-if="isAdmin" @click="openAddressModal()" class="btn-primary">➕ {{ t('locations.add_address') }}</button>
    </div>

    <!-- Addresses List -->
    <div v-if="addresses.length === 0" class="text-center py-12 bg-white rounded-xl">
      <span class="text-6xl">🏠</span>
      <p class="mt-4 text-gray-500">{{ t('locations.no_addresses') }}</p>
    </div>

    <div v-for="address in addresses" :key="address.id" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
      <!-- Address Header -->
      <div class="flex items-start justify-between mb-4">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-gray-900">🏠 {{ address.name }}</h2>
            <span v-if="address.is_primary" class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">⭐ {{ t('locations.primary') }}</span>
          </div>
          <p v-if="address.street || address.city" class="text-sm text-gray-500 mt-1">
            {{ [address.street, address.city, address.state_province, address.country].filter(Boolean).join(', ') }}
          </p>
          <p class="text-xs text-gray-400 mt-1">
            {{ address.rooms_count }} {{ t('locations.rooms') }} · {{ address.shelves_count }} {{ t('locations.shelves') }} · {{ address.books_count }} {{ t('nav.books') }}
          </p>
        </div>
        <div v-if="isAdmin" class="flex space-x-2 rtl:space-x-reverse flex-shrink-0">
          <button @click="openAddressModal(address)" class="text-xs text-blue-600">✏️</button>
          <button @click="deleteAddress(address)" class="text-xs text-red-600">🗑️</button>
        </div>
      </div>

      <!-- Expand/Collapse Rooms -->
      <button @click="toggleExpand(address.id)" class="text-sm text-primary-600 hover:text-primary-800 mb-3">
        {{ expandedAddresses.includes(address.id) ? '▼' : '▶' }} {{ t('locations.show_rooms') }}
      </button>

      <!-- Rooms -->
      <div v-if="expandedAddresses.includes(address.id)" class="ml-4 space-y-3 border-l-2 border-primary-100 pl-4">
        <div v-if="isAdmin" class="mb-2">
          <button @click="openRoomModal(address.id)" class="text-xs btn-secondary">➕ {{ t('locations.add_room') }}</button>
        </div>

        <div v-for="room in address.rooms || []" :key="room.id" class="bg-gray-50 rounded-lg p-3">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium text-gray-800">🚪 {{ room.name }}</h3>
              <p v-if="room.floor" class="text-xs text-gray-500">{{ t('locations.floor') }}: {{ room.floor }}</p>
              <p class="text-xs text-gray-400">{{ room.shelves_count }} {{ t('locations.shelves') }} · {{ room.books_count }} {{ t('nav.books') }}</p>
            </div>
            <div v-if="isAdmin" class="flex space-x-2 rtl:space-x-reverse">
              <button @click="openRoomModal(address.id, room)" class="text-xs text-blue-600">✏️</button>
              <button @click="deleteRoom(room)" class="text-xs text-red-600">🗑️</button>
            </div>
          </div>

          <!-- Shelves -->
          <div v-if="room.shelves?.length" class="mt-2 ml-4 space-y-1">
            <div v-for="shelf in room.shelves" :key="shelf.id" 
                 class="flex items-center justify-between text-xs bg-white rounded p-2 border">
              <span>📚 {{ shelf.name }} <span class="text-gray-400">({{ shelf.books_count }} {{ t('nav.books') }})</span></span>
              <div v-if="isAdmin" class="flex space-x-2 rtl:space-x-reverse">
                <button @click="openShelfModal(room.id, shelf)" class="text-blue-600">✏️</button>
                <button @click="deleteShelf(shelf)" class="text-red-600">🗑️</button>
              </div>
            </div>
          </div>
          <button v-if="isAdmin" @click="openShelfModal(room.id)" class="text-xs text-primary-600 mt-2">
            ➕ {{ t('locations.add_shelf') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Address Modal -->
    <div v-if="showAddressModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4">
        <h2 class="text-lg font-semibold">{{ editingAddress ? '✏️' : '➕' }} {{ t('locations.address') }}</h2>
        <form @submit.prevent="saveAddress" class="space-y-3">
          <div>
            <label class="label">{{ t('locations.name') }} *</label>
            <input v-model="addressForm.name" type="text" required class="input-field" :placeholder="t('locations.name_placeholder')" />
          </div>
          <div><label class="label">{{ t('locations.street') }}</label><input v-model="addressForm.street" type="text" class="input-field" /></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="label">{{ t('locations.city') }}</label><input v-model="addressForm.city" type="text" class="input-field" /></div>
            <div><label class="label">{{ t('locations.country') }}</label><input v-model="addressForm.country" type="text" class="input-field" /></div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="label">{{ t('locations.state') }}</label><input v-model="addressForm.state_province" type="text" class="input-field" /></div>
            <div><label class="label">{{ t('locations.postal_code') }}</label><input v-model="addressForm.postal_code" type="text" class="input-field" /></div>
          </div>
          <div class="flex items-center space-x-2 rtl:space-x-reverse">
            <input v-model="addressForm.is_primary" type="checkbox" id="is_primary" class="w-4 h-4" />
            <label for="is_primary" class="text-sm">{{ t('locations.set_primary') }}</label>
          </div>
          <div class="flex justify-end space-x-3 rtl:space-x-reverse">
            <button type="button" @click="showAddressModal = false" class="px-4 py-2 border rounded-lg">{{ t('cancel') }}</button>
            <button type="submit" class="btn-primary">{{ editingAddress ? t('save') : t('create') }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Room Modal -->
    <div v-if="showRoomModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-sm w-full p-6 space-y-4">
        <h2 class="text-lg font-semibold">🚪 {{ t('locations.room') }}</h2>
        <form @submit.prevent="saveRoom" class="space-y-3">
          <div><label class="label">{{ t('locations.name') }} *</label><input v-model="roomForm.name" type="text" required class="input-field" /></div>
          <div><label class="label">{{ t('locations.floor') }}</label><input v-model="roomForm.floor" type="text" class="input-field" /></div>
          <div><label class="label">{{ t('locations.description') }}</label><input v-model="roomForm.description" type="text" class="input-field" /></div>
          <div class="flex justify-end space-x-3 rtl:space-x-reverse">
            <button type="button" @click="showRoomModal = false" class="px-4 py-2 border rounded-lg">{{ t('cancel') }}</button>
            <button type="submit" class="btn-primary">{{ editingRoom ? t('save') : t('create') }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Shelf Modal -->
    <div v-if="showShelfModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-sm w-full p-6 space-y-4">
        <h2 class="text-lg font-semibold">📚 {{ t('locations.shelf') }}</h2>
        <form @submit.prevent="saveShelf" class="space-y-3">
          <div><label class="label">{{ t('locations.name') }} *</label><input v-model="shelfForm.name" type="text" required class="input-field" /></div>
          <div><label class="label">{{ t('locations.capacity') }}</label><input v-model.number="shelfForm.capacity" type="number" class="input-field" /></div>
          <div><label class="label">{{ t('locations.description') }}</label><input v-model="shelfForm.description" type="text" class="input-field" /></div>
          <div class="flex justify-end space-x-3 rtl:space-x-reverse">
            <button type="button" @click="showShelfModal = false" class="px-4 py-2 border rounded-lg">{{ t('cancel') }}</button>
            <button type="submit" class="btn-primary">{{ editingShelf ? t('save') : t('create') }}</button>
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

const addresses = ref([])
const expandedAddresses = ref([])

// Modals
const showAddressModal = ref(false)
const showRoomModal = ref(false)
const showShelfModal = ref(false)
const editingAddress = ref(null)
const editingRoom = ref(null)
const editingShelf = ref(null)

const addressForm = reactive({ name: '', street: '', city: '', state_province: '', postal_code: '', country: '', is_primary: false })
const roomForm = reactive({ address_id: '', name: '', description: '', floor: '' })
const shelfForm = reactive({ room_id: '', name: '', description: '', capacity: null })

function toggleExpand(id) {
  const idx = expandedAddresses.value.indexOf(id)
  if (idx >= 0) { expandedAddresses.value.splice(idx, 1) }
  else { expandedAddresses.value.push(id); loadAddressDetails(id) }
}

async function loadAddresses() {
  try {
    const response = await api.get('/locations')
    addresses.value = response.data.data
  } catch (err) { console.error(err) }
}

async function loadAddressDetails(id) {
  try {
    const response = await api.get(`/locations/${id}`)
    const idx = addresses.value.findIndex(a => a.id === id)
    if (idx >= 0) { addresses.value[idx] = { ...addresses.value[idx], ...response.data.data } }
  } catch (err) { console.error(err) }
}

function openAddressModal(address = null) {
  editingAddress.value = address
  if (address) { Object.assign(addressForm, { name: address.name||'', street: address.street||'', city: address.city||'', state_province: address.state_province||'', postal_code: address.postal_code||'', country: address.country||'', is_primary: address.is_primary }) }
  else { Object.assign(addressForm, { name: '', street: '', city: '', state_province: '', postal_code: '', country: '', is_primary: false }) }
  showAddressModal.value = true
}

async function saveAddress() {
  try {
    if (editingAddress.value) { await api.put(`/locations/${editingAddress.value.id}`, addressForm) }
    else { await api.post('/locations', addressForm) }
    showAddressModal.value = false
    toastStore.success(t('locations.saved'))
    loadAddresses()
  } catch (err) { toastStore.error(err.response?.data?.error || 'Error') }
}

async function deleteAddress(address) {
  if (!confirm(t('locations.delete_confirm', { name: address.name }))) return
  try { await api.delete(`/locations/${address.id}`); loadAddresses(); toastStore.success(t('locations.deleted')) }
  catch (err) { toastStore.error('Error') }
}

function openRoomModal(addressId, room = null) {
  editingRoom.value = room
  roomForm.address_id = addressId
  if (room) { Object.assign(roomForm, { name: room.name||'', description: room.description||'', floor: room.floor||'' }) }
  else { Object.assign(roomForm, { name: '', description: '', floor: '' }) }
  showRoomModal.value = true
}

async function saveRoom() {
  try {
    if (editingRoom.value) { await api.put(`/rooms/${editingRoom.value.id}`, roomForm) }
    else { await api.post('/rooms', roomForm) }
    showRoomModal.value = false
    toastStore.success(t('locations.saved'))
    loadAddressDetails(roomForm.address_id)
  } catch (err) { toastStore.error(err.response?.data?.errors?.[0] || err.response?.data?.error || 'Error') }
}

async function deleteRoom(room) {
  if (!confirm(t('locations.delete_room_confirm'))) return
  try { await api.delete(`/rooms/${room.id}`); loadAddresses(); toastStore.success(t('locations.deleted')) }
  catch (err) { toastStore.error('Error') }
}

function openShelfModal(roomId, shelf = null) {
  editingShelf.value = shelf
  shelfForm.room_id = roomId
  if (shelf) { Object.assign(shelfForm, { name: shelf.name||'', description: shelf.description||'', capacity: shelf.capacity }) }
  else { Object.assign(shelfForm, { name: '', description: '', capacity: null }) }
  showShelfModal.value = true
}

async function saveShelf() {
  try {
    if (editingShelf.value) { await api.put(`/shelves/${editingShelf.value.id}`, shelfForm) }
    else { await api.post('/shelves', shelfForm) }
    showShelfModal.value = false
    toastStore.success(t('locations.saved'))
    loadAddresses()
  } catch (err) { toastStore.error(err.response?.data?.errors?.[0] || err.response?.data?.error || 'Error') }
}

async function deleteShelf(shelf) {
  if (!confirm(t('locations.delete_shelf_confirm'))) return
  try { await api.delete(`/shelves/${shelf.id}`); loadAddresses(); toastStore.success(t('locations.deleted')) }
  catch (err) { toastStore.error('Error') }
}

onMounted(loadAddresses)
</script>

<style scoped>
.input-field { @apply w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none text-sm; }
.btn-primary { @apply px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition-colors text-sm; }
.btn-secondary { @apply px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 text-xs hover:bg-gray-50; }
.label { @apply block text-sm font-medium text-gray-700 mb-1; }
</style>
