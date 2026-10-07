import { createI18n } from 'vue-i18n'
import { describe, expect, it } from 'vitest'
import en from '../src/locales/en.json'
import ru from '../src/locales/ru.json'

const locales = { ru, en }

function keys(messages, prefix = '') {
  return Object.entries(messages).flatMap(([key, value]) =>
    typeof value === 'object' ? keys(value, `${prefix}${key}.`) : [`${prefix}${key}`],
  )
}

describe('i18n', () => {
  it('ru and en have the same keys', () => {
    expect(keys(ru).sort()).toEqual(keys(en).sort())
  })

  // Спецсимволы vue-i18n (@, {, }, |) в тексте ломают компиляцию сообщения и всю страницу
  it.each(Object.keys(locales))('all %s messages compile', (locale) => {
    const i18n = createI18n({ legacy: false, locale, messages: locales, missingWarn: false, fallbackWarn: false })

    for (const key of keys(locales[locale])) {
      expect(() => i18n.global.t(key, { count: 1, name: 'x', role: 'y' }), key).not.toThrow()
    }
  })
})
