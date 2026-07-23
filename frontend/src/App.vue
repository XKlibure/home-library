<template>
  <div v-if="isAuthenticated" class="flex h-screen bg-slate-50 overflow-hidden">

    <!-- ════════════ DESKTOP SIDEBAR (always visible on lg+) ════════════ -->
    <aside class="hidden lg:flex flex-col w-64 shrink-0 bg-slate-900 overflow-y-auto">
      <SidebarContent :is-admin="isAdmin" :ebooks-enabled="ebooksEnabled"
                      v-model:locale="currentLocale"
                      :user="user"
                      @switch-language="switchLanguage"
                      @logout="logout" />
    </aside>

    <!-- ════════════ MOBILE DRAWER (overlay below lg) ═══════════════════ -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="sidebarOpen" class="fixed inset-0 z-50 flex lg:hidden" @click.self="sidebarOpen = false">
          <!-- Backdrop -->
          <div class="absolute inset-0 bg-black/60" @click="sidebarOpen = false" />
          <!-- Sidebar panel -->
          <aside class="relative flex flex-col w-72 max-w-[85vw] bg-slate-900 h-full overflow-y-auto shadow-2xl">
            <SidebarContent :is-admin="isAdmin" :ebooks-enabled="ebooksEnabled"
                            v-model:locale="currentLocale"
                            :user="user"
                            @switch-language="switchLanguage"
                            @logout="logout"
                            @close="sidebarOpen = false" />
          </aside>
        </div>
      </Transition>
    </Teleport>

    <!-- ════════════ MAIN CONTENT ════════════════════════════════════════ -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

      <!-- Mobile top bar (hamburger + logo) -->
      <header class="lg:hidden flex items-center gap-3 px-4 h-14 bg-white border-b border-gray-200 shrink-0">
        <button @click="sidebarOpen = true" class="p-1.5 rounded-lg text-gray-600 hover:bg-gray-100" aria-label="Open menu">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <img src="/logo.png" class="w-7 h-7 object-contain" alt="Bookoholik" />
        <span class="font-semibold text-gray-800 text-sm">Bookoholik</span>
      </header>

      <!-- Page output -->
      <main class="flex-1 overflow-y-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <router-view />
        </div>
      </main>
    </div>
  </div>

  <!-- ════════════ UNAUTHENTICATED: login/register ══════════════════════ -->
  <div v-else class="min-h-screen bg-gray-50">
    <router-view />
  </div>

  <!-- Force password change (blocks everything until done) -->
  <ForcePasswordChangeModal
    v-if="isAuthenticated && mustChangePassword"
    @done="onPasswordChanged"
  />

  <!-- Toast notifications -->
  <div class="fixed top-4 ltr:right-4 rtl:left-4 z-[60] space-y-2 pointer-events-none">
    <transition-group name="toast">
      <div
        v-for="toast in toasts" :key="toast.id"
        :class="[
          'px-4 py-3 rounded-xl shadow-lg text-white text-sm max-w-sm pointer-events-auto',
          toast.type === 'success' ? 'bg-emerald-500' :
          toast.type === 'error'   ? 'bg-red-500'     : 'bg-blue-500'
        ]"
      >{{ toast.message }}</div>
    </transition-group>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore }   from './store/auth'
import { useToastStore }  from './store/toast'
import { useEbookPlugin } from './store/ebookPlugin'
import { setLocale }      from './i18n'
import SidebarContent           from './components/SidebarContent.vue'
import ForcePasswordChangeModal from './components/ForcePasswordChangeModal.vue'

const { t }       = useI18n()
const router      = useRouter()
const authStore   = useAuthStore()
const toastStore  = useToastStore()
const ebookPlugin = useEbookPlugin()

const sidebarOpen   = ref(false)
const currentLocale = ref(localStorage.getItem('app_locale') || 'en')

const isAuthenticated = computed(() => authStore.isAuthenticated)
const user            = computed(() => authStore.user)
const isAdmin         = computed(() => authStore.user?.role === 'admin')
const ebooksEnabled   = computed(() => ebookPlugin.enabled)
const toasts          = computed(() => toastStore.toasts)

const mustChangePassword = computed(() => !!authStore.user?.must_change_password)

function onPasswordChanged() {
  if (authStore.user) {
    authStore.user = { ...authStore.user, must_change_password: false }
    localStorage.setItem('auth_user', JSON.stringify(authStore.user))
  }
}

router.afterEach(() => { sidebarOpen.value = false })

onMounted(() => {
  if (authStore.isAuthenticated) ebookPlugin.load()
})

function switchLanguage() { setLocale(currentLocale.value) }
function logout() { authStore.logout(); router.push('/login') }
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

.toast-enter-active { transition: all 0.3s ease; }
.toast-leave-active { transition: all 0.2s ease; }
.toast-enter-from   { opacity: 0; transform: translateX(1rem); }
.toast-leave-to     { opacity: 0; transform: translateX(1rem); }
</style>
