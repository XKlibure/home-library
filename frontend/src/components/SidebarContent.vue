<template>
  <!-- Logo -->
  <div class="flex items-center gap-3 px-5 h-16 border-b border-slate-800 shrink-0">
    <img src="/logo.png" alt="Bookoholik" class="w-8 h-8 rounded object-contain" />
    <span class="text-base font-bold text-white tracking-tight">Bookoholik</span>
    <!-- Mobile close button -->
    <button v-if="$attrs.onClose" @click="$emit('close')"
            class="ltr:ml-auto rtl:mr-auto text-slate-400 hover:text-white p-1">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <!-- Scrollable nav -->
  <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">

    <router-link to="/" exact-active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
      <span class="nav-icon">🏠</span><span>{{ t('nav.dashboard') }}</span>
    </router-link>
    <router-link to="/books" active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
      <span class="nav-icon">📚</span><span>{{ t('nav.books') }}</span>
    </router-link>
    <router-link v-if="ebooksEnabled" to="/ebooks" active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
      <span class="nav-icon">📱</span><span>{{ t('nav.ebooks') }}</span>
    </router-link>
    <router-link to="/lending" active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
      <span class="nav-icon">🤝</span><span>{{ t('nav.lending') }}</span>
    </router-link>
    <router-link to="/reports" active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
      <span class="nav-icon">📊</span><span>{{ t('nav.reports') }}</span>
    </router-link>

    <div class="pt-5 pb-2 px-3">
      <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest">{{ t('nav.library') }}</p>
    </div>
    <router-link to="/writers"    active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
      <span class="nav-icon">✍️</span><span>{{ t('nav.writers') }}</span>
    </router-link>
    <router-link to="/genres"     active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
      <span class="nav-icon">🏷️</span><span>{{ t('nav.genres') }}</span>
    </router-link>
    <router-link to="/publishers" active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
      <span class="nav-icon">🏢</span><span>{{ t('nav.publishers') }}</span>
    </router-link>
    <router-link to="/locations"  active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
      <span class="nav-icon">📍</span><span>{{ t('nav.locations') }}</span>
    </router-link>

    <template v-if="isAdmin">
      <div class="pt-5 pb-2 px-3">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest">Admin</p>
      </div>
      <router-link to="/users"  active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
        <span class="nav-icon">👥</span><span>{{ t('nav.users') }}</span>
      </router-link>
      <router-link to="/backup" active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
        <span class="nav-icon">💾</span><span>{{ t('nav.backup') }}</span>
      </router-link>
    </template>
  </nav>

  <!-- Bottom strip -->
  <div class="shrink-0 px-3 py-3 border-t border-slate-800 space-y-0.5">
    <!-- Language switcher -->
    <div class="px-3 py-2">
      <select
        :value="locale"
        @change="$emit('update:locale', $event.target.value); $emit('switch-language')"
        class="w-full bg-slate-800 text-slate-300 text-xs rounded-lg px-2 py-1.5 border-0 outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
      >
        <option value="en">🇬🇧 English</option>
        <option value="ar">🇸🇦 العربية</option>
        <option value="fr">🇫🇷 Français</option>
      </select>
    </div>

    <router-link to="/settings" active-class="!bg-blue-600/20 !text-blue-400 font-medium" class="nav-link">
      <span class="nav-icon">⚙️</span><span>{{ t('nav.settings') }}</span>
    </router-link>

    <!-- User pill -->
    <div class="flex items-center gap-2.5 px-3 py-2.5">
      <div class="w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-bold shrink-0 uppercase">
        {{ user?.full_name?.[0] || '?' }}
      </div>
      <span class="text-sm text-slate-300 truncate flex-1 min-w-0">{{ user?.full_name }}</span>
    </div>

    <button
      @click="$emit('logout')"
      class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:bg-red-950/60 hover:text-red-400 transition-colors text-sm"
    >
      <span class="nav-icon">🚪</span><span>{{ t('logout') }}</span>
    </button>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
  isAdmin:       { type: Boolean, default: false },
  ebooksEnabled: { type: Boolean, default: false },
  locale:        { type: String,  default: 'en'  },
  user:          { type: Object,  default: null  },
})

defineEmits(['update:locale', 'switch-language', 'logout', 'close'])
</script>

<style scoped>
.nav-link {
  @apply flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-400
         hover:bg-slate-800 hover:text-slate-100 transition-colors;
}
.nav-icon {
  @apply text-base w-5 text-center shrink-0;
}
</style>
