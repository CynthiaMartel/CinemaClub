import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export function useNavigation() {
  const router = useRouter()
  const auth = useAuthStore()

  const goProfile = (username = null) => {
    if (username) {
      router.push({ name: 'user-profile', params: { username } })
      return
    }

    if (auth.user?.name) {
      router.push({ name: 'user-profile', params: { username: auth.user.name } })
      return
    }

    console.warn('No se pudo navegar al perfil: Falta username')
  }

  return {
    goProfile
  }
}
