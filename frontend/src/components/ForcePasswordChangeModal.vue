<template>
  <!-- Full-screen blocking overlay — user cannot dismiss this -->
  <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 space-y-6">

      <!-- Header -->
      <div class="text-center space-y-2">
        <div class="text-4xl">🔑</div>
        <h2 class="text-xl font-bold text-gray-900">{{ t('auth.force_change_title') }}</h2>
        <p class="text-sm text-gray-500">{{ t('auth.force_change_desc') }}</p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="label">{{ t('settings.new_password') }}</label>
          <input
            v-model="form.new_password"
            type="password"
            required
            autocomplete="new-password"
            class="input-field"
            :placeholder="t('settings.new_password')"
          />
        </div>
        <div>
          <label class="label">{{ t('settings.confirm_password') }}</label>
          <input
            v-model="form.confirm_password"
            type="password"
            required
            autocomplete="new-password"
            class="input-field"
            :placeholder="t('settings.confirm_password')"
          />
        </div>

        <p class="text-xs text-gray-400">{{ t('settings.password_requirements') }}</p>

        <div v-if="error" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ error }}</div>

        <button type="submit" :disabled="loading" class="w-full btn-primary">
          {{ loading ? t('loading') : t('auth.set_password') }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useAuthStore } from '../store/auth'
import { useToastStore } from '../store/toast'

const { t }       = useI18n()
const authStore   = useAuthStore()
const toastStore  = useToastStore()
const emit        = defineEmits(['done'])

const loading = ref(false)
const error   = ref('')
const form    = reactive({ new_password: '', confirm_password: '' })

async function submit() {
  error.value = ''

  if (form.new_password !== form.confirm_password) {
    error.value = t('settings.passwords_no_match')
    return
  }
  if (form.new_password.length < 10) {
    error.value = t('settings.password_too_short')
    return
  }

  loading.value = true
  try {
    const res = await api.post('/auth/change-initial-password', {
      new_password:     form.new_password,
      confirm_password: form.confirm_password,
    })

    // Update local user state — clear must_change_password
    if (res.data.user) {
      authStore.user = { ...authStore.user, ...res.data.user }
      localStorage.setItem('auth_user', JSON.stringify(authStore.user))
    } else {
      await authStore.refreshUser()
    }

    toastStore.success(t('auth.password_set_success'))
    emit('done')
  } catch (err) {
    error.value = err.response?.data?.error || t('settings.update_failed')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.input-field {
  @apply w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm;
}
.btn-primary {
  @apply px-4 py-2.5 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition-colors disabled:opacity-50;
}
.label {
  @apply block text-sm font-medium text-gray-700 mb-1;
}
</style>
