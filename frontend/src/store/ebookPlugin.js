import { reactive } from 'vue'
import api from '../services/api'

/**
 * Lightweight reactive store for the e-book plugin state.
 * Not using Pinia to stay consistent with the existing non-Pinia stores pattern.
 */
const state = reactive({
  enabled: false,
  loaded: false,
})

export function useEbookPlugin() {
  async function load() {
    if (state.loaded) return
    try {
      const res = await api.get('/ebook-plugin/status')
      state.enabled = res.data.enabled === true
      state.loaded = true
    } catch {
      state.enabled = false
    }
  }

  async function enable() {
    await api.post('/ebook-plugin/enable')
    state.enabled = true
  }

  async function disable() {
    await api.post('/ebook-plugin/disable')
    state.enabled = false
  }

  function reset() {
    state.enabled = false
    state.loaded = false
  }

  return {
    get enabled() { return state.enabled },
    get loaded()  { return state.loaded },
    load,
    enable,
    disable,
    reset,
  }
}
