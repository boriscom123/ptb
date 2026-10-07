import { i18n } from './i18n'

export function fullName(user) {
  return [user.first_name, user.last_name].filter(Boolean).join(' ')
}

const units = [
  ['year', 31536000],
  ['month', 2592000],
  ['day', 86400],
  ['hour', 3600],
  ['minute', 60],
]

// «5 минут назад», «вчера» и т.п.
export function relativeTime(iso) {
  if (!iso) return i18n.global.t('user.never')

  const seconds = (new Date(iso).getTime() - Date.now()) / 1000
  const format = new Intl.RelativeTimeFormat(i18n.global.locale.value, { numeric: 'auto' })

  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) return format.format(Math.round(seconds / size), unit)
  }

  return format.format(0, 'minute')
}

export function dateTime(iso) {
  if (!iso) return i18n.global.t('user.never')

  return new Intl.DateTimeFormat(i18n.global.locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
}
