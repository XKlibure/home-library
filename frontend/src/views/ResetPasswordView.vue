<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 to-blue-100 p-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md p-8 space-y-6">

      <!-- Validating -->
      <div v-if="validating" class="text-center py-8">
        <div class="inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="mt-3 text-sm text-gray-500">{{ t('loading') }}</p>
      </div>

      <!-- Expired / Invalid -->
      <div v-else-if="tokenError" class="text-center space-y-4">
        <div class="text-5xl">⏰</div>
        <h2 class="text-lg font-bold text-gray-900">{{ t('auth.token_expired_title') }}</h2>
        <p class="text-sm text-gray-500">{{ t('auth.token_expired_desc') }}</p>
        <router-link to="/forgot-password" class="btn-primary inline-block">
          {{ t('auth.request_new_link') }}
        </router-link>
        <div><router-link to="/login" class="text-sm text-gray-400 hover:text-gray-600">← {{ t('auth.back_to_login') }}</router-link></div>
      </div>

      <!-- Success -->
      <div v-else-if="done" class="text-center space-y-4">
        <div class="text-5xl">✅</div>
        <h2 class="text-lg font-bold text-gray-900">{{ t('auth.password_reset_success') }}</h2>
        <router-link to="/login" class="btn-primary inline-block">{{ t('auth.sign_in') }}</router-link>
      </div>

      <!-- Reset form -->
      <template v-else>
        <div class="text-center space-y-1">
          <div class="text-4xl">🔒</div>
          <h1 class="text-xl font-bold text-gray-900">{{ t('auth.reset_password_title') }}</h1>
          <p class="text-sm text-gray-500">{{ t('auth.reset_password_desc') }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="label">{{ t('settings.new_password') }}</label>
            <input v-model="form.password" type="password" required autocomplete="new-password"
                   class="input-field" placeholder="••••••••••" />
          </div>
          <div>
            <label class="label">{{ t('settings.confirm_password') }}</label>
            <input v-model="form.confirm" type="password" required autocomplete="new-password"
                   class="input-field" placeholder="••••••••••" />
          </div>
          <p class="text-xs text-gray-400">{{ t('settings.password_requirements') }}</p>

          <div v-if="error" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ error }}</div>

          <button type="submit" :disabled="loading" class="w-full btn-primary">
            {{ loading ? t('loading') : t('auth.set_new_password') }}
          </button>
        </form>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '../services/api'

const { t }     = useI18n()
const route     = useRoute()
const token     = route.query.token || ''

const validating = ref(true)
const tokenError = ref(false)
const done       = ref(false)
const loading    = ref(false)
const error      = ref('')
const form       = reactive({ password: '', confirm: '' })

onMounted(async () => {
  if (!token) { tokenError.value = true; validating.value = false; return }
  try {
    const res = await api.get(`/auth/reset-password/validate?token=${encodeURIComponent(token)}`)
    if (!res.data.valid) tokenError.value = true
  } catch {
    tokenError.value = true
  } finally {
    validating.value = false
  }
})

async function submit() {
  error.value = ''
  if (form.password !== form.confirm) { error.value = t('settings.passwords_no_match'); return }
  if (form.password.length < 10)      { error.value = t('settings.password_too_short'); return }

  loading.value = true
  try {
    await api.post('/auth/reset-password', {
      token,
      password:              form.password,
      password_confirmation: form.confirm,
    })
    done.value = true
  } catch (err) {
    const reason = err.response?.data?.reason
    if (reason === 'expired') { tokenError.value = true; return }
    error.value = err.response?.data?.error || t('auth.error_occurred')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.input-field { @apply w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none text-sm; }
.btn-primary { @apply px-5 py-2.5 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700 transition-colors disabled:opacity-50; }
.label       { @apply block text-sm font-medium text-gray-700 mb-1; }
</style>
