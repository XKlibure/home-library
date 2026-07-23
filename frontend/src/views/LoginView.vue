<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 to-blue-100">
    <div class="max-w-md w-full mx-4">

      <!-- Logo + title -->
      <div class="text-center mb-8">
        <img src="/logo.png" alt="Bookoholik" class="w-28 h-28 mx-auto object-contain" />
        <h1 class="mt-4 text-3xl font-bold text-gray-900">{{ t('app_name') }}</h1>
        <p class="mt-1 text-gray-500">{{ t('app_subtitle') }}</p>
      </div>

      <!-- Language switcher -->
      <div class="flex justify-center mb-5">
        <select v-model="currentLocale" @change="switchLanguage"
                class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 bg-white focus:ring-2 focus:ring-primary-500 outline-none">
          <option value="en">🇬🇧 English</option>
          <option value="ar">🇸🇦 العربية</option>
          <option value="fr">🇫🇷 Français</option>
        </select>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-8 space-y-5">

        <!-- Sign In form -->
        <h2 class="text-xl font-semibold text-gray-800">{{ t('auth.sign_in') }}</h2>

        <form @submit.prevent="handleLogin" class="space-y-4">
          <div>
            <label class="label">{{ t('auth.username') }}</label>
            <input v-model="form.username" type="text" required autocomplete="username"
                   class="input-field" :placeholder="t('auth.username')" />
          </div>
          <div>
            <label class="label">{{ t('auth.password') }}</label>
            <input v-model="form.password" type="password" required autocomplete="current-password"
                   class="input-field" placeholder="••••••••" />
            <!-- Forgot password link -->
            <div class="text-right mt-1">
              <router-link to="/forgot-password" class="text-xs text-primary-600 hover:text-primary-800">
                {{ t('auth.forgot_password') }}
              </router-link>
            </div>
          </div>

          <div v-if="errorMsg" class="text-sm text-red-600 bg-red-50 p-3 rounded-lg">{{ errorMsg }}</div>

          <button type="submit" :disabled="loading" class="w-full btn-primary flex items-center justify-center gap-2">
            <svg v-if="loading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            {{ loading ? t('loading') : t('auth.sign_in') }}
          </button>
        </form>

        <!-- Divider -->
        <div class="relative">
          <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200" /></div>
          <div class="relative flex justify-center"><span class="bg-white px-3 text-xs text-gray-400">{{ t('auth.no_account_hint') }}</span></div>
        </div>

        <!-- Request access -->
        <router-link to="/request-access"
                     class="flex items-center justify-center gap-2 w-full py-2.5 border-2 border-primary-200 text-primary-700 rounded-lg hover:bg-primary-50 transition-colors text-sm font-medium">
          ✉️ {{ t('auth.request_access') }}
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../store/auth'
import { useToastStore } from '../store/toast'
import { setLocale } from '../i18n'

const { t }      = useI18n()
const router     = useRouter()
const authStore  = useAuthStore()
const toastStore = useToastStore()

const loading       = ref(false)
const errorMsg      = ref('')
const currentLocale = ref(localStorage.getItem('app_locale') || 'en')
const form = reactive({ username: '', password: '' })

function switchLanguage() { setLocale(currentLocale.value) }

async function handleLogin() {
  loading.value  = true
  errorMsg.value = ''
  try {
    await authStore.login({ username: form.username, password: form.password })
    toastStore.success(t('auth.welcome_back'))
    router.push('/')
    // Note: must_change_password modal is shown by App.vue automatically
  } catch (err) {
    errorMsg.value = err.response?.data?.error || err.response?.data?.errors?.[0] || t('auth.error_occurred')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.input-field { @apply w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors text-sm; }
.btn-primary { @apply px-4 py-2.5 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors disabled:opacity-50; }
.label       { @apply block text-sm font-medium text-gray-700 mb-1; }
</style>
