import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth' // Si necesitas acceder al usuario logueado

export function useNavigation() {
  const router = useRouter()
  const auth = useAuthStore()

  const goProfile = (userId = null) => {
    // 1. Si pasamos un ID específico (ej: click en un comentario)
    if (userId) {
      router.push({ name: 'user-profile', params: { id: userId } })
      return
    }

    // 2. Si no pasamos ID, intentamos ir al del usuario logueado (ej: click en "Mi Perfil")
    if (auth.user?.id) {
      router.push({ name: 'user-profile', params: { id: auth.user.id } })
      return
    }
    
    // 3. Opcional: Si no hay ID y no está logueado, mandar al login o no hacer nada
    console.warn('No se pudo navegar al perfil: Falta ID')
  }

  // Retornamos la función para que quien use este archivo pueda usarla
  return {
    goProfile
  }
}