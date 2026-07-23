<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 to-blue-100 p-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md p-8 space-y-6">

      <!-- Success state -->
      <div v-if="sent" class="text-center space-y-4">
        <div class="text-5xl">📨</div>
        <h2 class="text-lg font-bold text-gray-900">{{ t('auth.request_sent_title') }}</h2>
        <p class="text-sm text-gray-500">{{ t('auth.request_sent_desc') }}</p>
        <router-link to="/login" class="block text-sm text-primary-600 hover:text-primary-800 font-medium">
          ← {{ t('auth.back_to_login') }}
        </router-link>
      </div>

      <!-- Form -->
      <template v-else>
        <div class="text-center space-y-1">
          <div class="text-4xl">✉️</div>
          <h1 class="text-xl font-bold text-gray-900">{{ t('auth.request_access_title') }}</h1>
          <p class="text-sm text-gray-500">{{ t('auth.request_access_desc') }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="label">{{ t('auth.email') }} *</label>
            <input v-model="form.email" type="email" required autocomplete="email"
                   class="input-field" placeholder="email@example.com" />
          </div>
          <div>
            <label class="label">{{ t('auth.request_message') }}</label>
            <textarea v-model="form.message" rows="3" class="input-field resize-none"
                      :placeholder="t('auth.request_message_placeholder')"></textarea>
          </div>

        <div v-if="error" 
             :class="['px-3 py-2 rounded-lg text-sm',
                      isDuplicate ? 'bg-amber-50 text-amber-700 border border-amber-200' 
                                  : 'bg-red-50 text-red-600']"
        >{{ error }}</div>

          <button type="submit" :disabled="loading" class="w-full btn-primary">
            {{ loading ? t('loading') : t('auth.send_request') }}
          </button>

          <router-link to="/login" class="block text-center text-sm text-gray-400 hover:text-gray-600">
            ← {{ t('auth.back_to_login') }}
          </router-link>
        </form>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'

const { t }    = useI18n()
const loading    = ref(false)
const sent       = ref(false)
const error      = ref('')
const isDuplicate = ref(false)
const form       = reactive({ email: '', message: '' })

async function submit() {
  loading.value   = true
  error.value     = ''
  isDuplicate.value = false
  try {
    await api.post('/auth/request-access', { email: form.email, message: form.message })
    sent.value = true
  } catch (err) {
    isDuplicate.value = err.response?.status === 409
    error.value = err.response?.data?.error || t('auth.error_occurred')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.input-field { @apply w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none text-sm; }
.btn-primary { @apply px-4 py-2.5 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700 transition-colors disabled:opacity-50; }
.label       { @apply block text-sm font-medium text-gray-700 mb-1; }
</style>
