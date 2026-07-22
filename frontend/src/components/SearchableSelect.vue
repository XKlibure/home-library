<template>
  <div class="relative" ref="wrapper">
    <!-- Trigger -->
    <button
      type="button"
      @click="toggle"
      class="w-full flex items-center justify-between px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-left transition-colors"
      :class="isOpen ? 'border-indigo-500 ring-2 ring-indigo-500' : 'hover:border-gray-400'"
    >
      <span :class="modelValue ? 'text-gray-900' : 'text-gray-400'">
        {{ modelValue || placeholder }}
      </span>
      <svg
        class="w-4 h-4 text-gray-400 flex-shrink-0 ml-2 transition-transform"
        :class="isOpen ? 'rotate-180' : ''"
        fill="none" stroke="currentColor" viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <!-- Dropdown (teleported to body so it's never clipped) -->
    <Teleport to="body">
      <div v-if="isOpen" class="fixed inset-0 z-40" @click="close" />

      <div
        v-if="isOpen"
        :style="dropdownStyle"
        class="fixed z-50 bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden"
      >
        <!-- Search input -->
        <div class="p-2 border-b border-gray-100">
          <input
            ref="searchInput"
            v-model="query"
            type="text"
            class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400"
            :placeholder="searchPlaceholder"
            @keydown.escape="close"
            @keydown.enter.prevent="handleEnter"
            @keydown.arrow-down.prevent="moveDown"
            @keydown.arrow-up.prevent="moveUp"
          />
        </div>

        <!-- Options list -->
        <div class="max-h-52 overflow-y-auto" ref="listEl">
          <div
            v-for="(item, idx) in filtered"
            :key="item.id"
            @click="pick(item)"
            @mouseenter="highlighted = idx"
            class="px-4 py-2.5 text-sm cursor-pointer flex items-center justify-between"
            :class="[
              highlighted === idx ? 'bg-indigo-50 text-indigo-700' : 'hover:bg-gray-50',
              isSelected(item) ? 'font-semibold' : ''
            ]"
          >
            <span>{{ item.name }}</span>
            <span v-if="item.name_ar" class="text-gray-400 text-xs ml-2 truncate max-w-24">{{ item.name_ar }}</span>
          </div>

          <!-- Empty state when list has items but query has no match -->
          <div v-if="filtered.length === 0 && items.length > 0" class="px-4 py-3 text-sm text-gray-400 text-center">
            {{ t('ebooks.no_match') }}
          </div>

          <!-- Empty state when list is empty -->
          <div v-if="items.length === 0" class="px-4 py-3 text-sm text-gray-400 text-center">
            {{ t('ebooks.list_empty') }}
          </div>
        </div>

        <!-- Create new option (shown when typed value has no exact match) -->
        <div
          v-if="query.trim() && !hasExactMatch"
          @click="requestCreate"
          class="px-4 py-2.5 text-sm text-indigo-600 hover:bg-indigo-50 cursor-pointer flex items-center gap-2 border-t border-gray-100 font-medium"
        >
          <span>➕</span>
          {{ createLabel }} "<strong>{{ query.trim() }}</strong>"
        </div>

        <!-- Clear selection -->
        <div
          v-if="modelValue"
          @click="clear"
          class="px-4 py-2 text-xs text-gray-400 hover:text-red-500 hover:bg-red-50 cursor-pointer border-t border-gray-100 text-center transition-colors"
        >
          ✕ {{ t('ebooks.clear_selection') }}
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  modelValue:       { type: String,  default: '' },
  items:            { type: Array,   default: () => [] },
  placeholder:      { type: String,  default: '' },
  searchPlaceholder:{ type: String,  default: '' },
  createLabel:      { type: String,  default: '➕ Add' },
  loading:          { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'select', 'create'])

// ── state ──────────────────────────────────────────────────────
const isOpen      = ref(false)
const query       = ref('')
const highlighted = ref(0)
const wrapper     = ref(null)
const searchInput = ref(null)
const listEl      = ref(null)
const dropdownStyle = ref({})

// ── computed ───────────────────────────────────────────────────

const filtered = computed(() => {
  const q = query.value.toLowerCase().trim()
  if (!q) return props.items
  return props.items.filter(item =>
    item.name?.toLowerCase().includes(q) ||
    item.name_ar?.toLowerCase().includes(q) ||
    item.name_fr?.toLowerCase().includes(q)
  )
})

const hasExactMatch = computed(() => {
  const q = query.value.trim().toLowerCase()
  return props.items.some(item => item.name?.toLowerCase() === q)
})

// ── open / close ───────────────────────────────────────────────

function toggle() {
  isOpen.value ? close() : open()
}

function open() {
  if (!wrapper.value) return
  const rect        = wrapper.value.getBoundingClientRect()
  const spaceBelow  = window.innerHeight - rect.bottom
  const dropMaxH    = 300

  const base = {
    width:    rect.width + 'px',
    left:     rect.left + 'px',
    minWidth: '180px',
  }

  if (spaceBelow < dropMaxH && rect.top > dropMaxH) {
    // Open upward
    dropdownStyle.value = { ...base, bottom: (window.innerHeight - rect.top + 4) + 'px' }
  } else {
    // Open downward
    dropdownStyle.value = { ...base, top: (rect.bottom + 4) + 'px' }
  }

  isOpen.value  = true
  query.value   = ''
  highlighted.value = 0
  nextTick(() => searchInput.value?.focus())
}

function close() {
  isOpen.value = false
  query.value  = ''
}

// ── interactions ───────────────────────────────────────────────

function pick(item) {
  emit('update:modelValue', item.name)
  emit('select', item)
  close()
}

function clear() {
  emit('update:modelValue', '')
  emit('select', null)
  close()
}

function requestCreate() {
  const name = query.value.trim()
  if (name) {
    emit('create', name)
    close()
  }
}

function isSelected(item) {
  return props.modelValue === item.name
}

// Keyboard navigation
function moveDown() {
  highlighted.value = Math.min(highlighted.value + 1, filtered.value.length - 1)
}

function moveUp() {
  highlighted.value = Math.max(highlighted.value - 1, 0)
}

function handleEnter() {
  if (filtered.value[highlighted.value]) {
    pick(filtered.value[highlighted.value])
  } else if (query.value.trim() && !hasExactMatch.value) {
    requestCreate()
  }
}
</script>
