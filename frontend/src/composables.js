import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { showBackButton } from './telegram'

// Системная кнопка «Назад» Telegram на вложенных экранах
export function useBackButton(fallback) {
  const router = useRouter()
  const back = () => (window.history.state?.back ? router.back() : router.push(fallback()))

  let hide = () => {}
  onMounted(() => (hide = showBackButton(back)))
  onUnmounted(() => hide())
}
