import { ref } from 'vue'
import api from '../services/api'

/**
 * Loads an authenticated API image as a local blob URL.
 *
 * Lifecycle cleanup (URL.revokeObjectURL) is intentionally NOT done here —
 * calling onUnmounted() outside of a component's setup() silently fails in
 * Vue 3. Each component that uses this composable is responsible for calling
 * revoke() in its own onUnmounted hook.
 *
 * Usage inside setup():
 *   const { blobUrl, loading, load, revoke } = useAuthImage()
 *   onUnmounted(revoke)
 *   load('/ebooks/123/cover')
 */
export function useAuthImage() {
  const blobUrl  = ref(null)
  const loading  = ref(false)
  const failed   = ref(false)
  let   currentUrl = null

  async function load(apiPath) {
    if (!apiPath) return
    loading.value = true
    failed.value  = false

    // Revoke previous blob to avoid memory leaks
    revoke()

    try {
      const res = await api.get(apiPath, { responseType: 'blob' })

      // A tiny response means the backend returned a JSON error, not an image
      if (!res.data || res.data.size < 200) {
        failed.value = true
        return
      }

      currentUrl    = URL.createObjectURL(res.data)
      blobUrl.value = currentUrl
    } catch {
      failed.value = true
    } finally {
      loading.value = false
    }
  }

  function revoke() {
    if (currentUrl) {
      URL.revokeObjectURL(currentUrl)
      currentUrl    = null
      blobUrl.value = null
    }
  }

  return { blobUrl, loading, failed, load, revoke }
}
