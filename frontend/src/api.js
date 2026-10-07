import { useAuthStore } from './stores/auth'
import { i18n } from './i18n'

export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

async function send(method, path, body, token) {
  const headers = { Accept: 'application/json', 'Accept-Language': i18n.global.locale.value }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (token) headers.Authorization = `Bearer ${token}`

  return fetch(`/api${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
}

async function parse(response) {
  const data = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(response.status, data?.message || i18n.global.t('errors.generic'), data?.errors)
  }

  return data
}

// Запрос с JWT; при истёкшем токене один раз переавторизуется по initData и повторяет запрос
export async function request(method, path, body) {
  const auth = useAuthStore()

  let response = await send(method, path, body, auth.token)
  if (response.status === 401 && auth.token) {
    await auth.login()
    response = await send(method, path, body, auth.token)
  }

  return parse(response)
}

export const api = {
  login: (initData) => send('POST', '/auth/telegram', { init_data: initData }).then(parse),
  users: (params) => request('GET', `/admin/users?${new URLSearchParams(params)}`),
  user: (id) => request('GET', `/admin/users/${id}`),
  updateUser: (id, data) => request('PATCH', `/admin/users/${id}`, data),
  chats: () => request('GET', '/admin/chats'),
  chat: (id) => request('GET', `/admin/chats/${id}`),
  updateChat: (id, data) => request('PATCH', `/admin/chats/${id}`, data),
  updateRule: (id, rule, data) => request('PUT', `/admin/chats/${id}/rules/${rule}`, data),
  chatEvents: (id, page) => request('GET', `/admin/chats/${id}/events?page=${page}`),
}
