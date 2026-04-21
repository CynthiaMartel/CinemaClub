/**
 * Resuelve la URL correcta de un avatar.
 * - Si no hay avatar → null (el componente muestra la inicial)
 * - Si es URL completa (Cloudinary) → la devuelve directamente
 * - Si es ruta local legacy (/storage/) → construye la URL local
 */
export function avatarUrl(avatar) {
  if (!avatar) return null
  if (avatar.startsWith('http://') || avatar.startsWith('https://')) return avatar
  return `/storage/${avatar}`
}
