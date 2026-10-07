import { createI18n } from 'vue-i18n'
import en from './locales/en.json'
import ru from './locales/ru.json'
import { languageCode } from './telegram'

const supported = ['ru', 'en']
const language = languageCode.slice(0, 2).toLowerCase()

export const i18n = createI18n({
  legacy: false,
  locale: supported.includes(language) ? language : 'en',
  fallbackLocale: 'en',
  messages: { ru, en },
})

document.documentElement.lang = i18n.global.locale.value
