// Обёртка над Telegram WebApp API (https://core.telegram.org/bots/webapps)

export const webApp = window.Telegram?.WebApp

export const initData = webApp?.initData ?? ''

export const isInTelegram = initData !== ''

export const languageCode = webApp?.initDataUnsafe?.user?.language_code ?? navigator.language

export function init() {
  if (!isInTelegram) return

  // Внутри Telegram цвета берутся только из его темы, без системной тёмной темы браузера
  document.documentElement.dataset.telegram = ''
  webApp.ready()
  webApp.expand()
}

export function confirm(message) {
  if (!isInTelegram) return Promise.resolve(window.confirm(message))

  return new Promise((resolve) => webApp.showConfirm(message, resolve))
}

export function alert(message) {
  if (!isInTelegram) return Promise.resolve(window.alert(message))

  return new Promise((resolve) => webApp.showAlert(message, resolve))
}

export function haptic(type = 'success') {
  webApp?.HapticFeedback?.notificationOccurred(type)
}

// Системная кнопка «Назад» в шапке Telegram
export function showBackButton(onClick) {
  if (!isInTelegram) return () => {}

  webApp.BackButton.onClick(onClick)
  webApp.BackButton.show()

  return () => {
    webApp.BackButton.offClick(onClick)
    webApp.BackButton.hide()
  }
}
