import { ref } from 'vue'

const isUserMenuOpen = ref(false)

export function useNavState() {
  return { isUserMenuOpen }
}
