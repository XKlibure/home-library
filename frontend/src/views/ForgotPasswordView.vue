<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 to-blue-100 p-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md p-8 space-y-6">

      <div class="text-center space-y-1">
        <div class="text-4xl">🔑</div>
        <h1 class="text-xl font-bold text-gray-900">{{ t('auth.forgot_password_title') }}</h1>
        <p class="text-sm text-gray-500">{{ t('auth.forgot_password_desc') }}</p>
      </div>

      <!-- Sent state -->
      <div v-if="sent" class="text-center space-y-4">
        <div class="text-5xl">📧</div>
        <p class="text-gray-700 font-medium">{{ t('auth.reset_link_sent') }}</p>
        <p class="text-sm text-gray-500">{{ t('auth.reset_link_hint') }}</p>
        <router-link to="/login" class="block text-sm text-primary-600 hover:text-primary-800 font-medium">
          ← {{ t('auth.back_to_login') }}
        </router-link>
      </div>

      <!-- Form -->
      <form v-else @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="label">{{ t('auth.email') }}</label>
          <input v-model="email" type="email" required autocomplete="email"
                 class="input-field" placeholder="email@example.com" />
        </div>

        <div v-if="error" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ error }}</div>

        <button type="submit" :disabled="loading" class="w-full btn-primary">
          {{ loading ? t('loading') : t('auth.send_reset_link') }}
        </button>

        <router-link to="/login" class="block text-center text-sm text-gray-500 hover:text-gray-700">
          ← {{ t('auth.back_to_login') }}
        </router-link>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'

const { t }   = useI18n()
const email   = ref('')
const loading = ref(false)
const sent    = ref(false)
const error   = ref('')

async function submit() {
  loading.value = true
  error.value   = ''
  try {
    await api.post('/auth/forgot-password', { email: email.value })
    sent.value = true
  } catch (err) {
    error.value = err.response?.data?.error || t('auth.error_occurred')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.input-field { @apply w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none text-sm; }
.btn-primary { @apply px-4 py-2.5 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700 transition-colors disabled:opacity-50 w-full; }
.label       { @apply block text-sm font-medium text-gray-700 mb-1; }
</style>
