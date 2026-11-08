import { afterEach, expect, test, vi } from 'vitest'
import { api, ApiError } from './api'

afterEach(() => vi.unstubAllGlobals())

test('obtém CSRF antes de enviar credenciais e usa sessão da mesma origem', async () => {
  const fetch = vi.fn().mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf-demo' })))
    .mockResolvedValueOnce(new Response(JSON.stringify({ user: { id: 1 } })))
  vi.stubGlobal('fetch', fetch)
  await api('/auth/login', { method: 'POST', body: JSON.stringify({ email: 'demo@example.test', password: 'ficticia' }) })
  expect(fetch.mock.calls[0][0]).toBe('/api/v1/auth/csrf')
  expect(fetch.mock.calls[1][1].credentials).toBe('same-origin')
  expect(fetch.mock.calls[1][1].headers.get('X-CSRF-TOKEN')).toBe('csrf-demo')
})

test('preserva 401 para que a interface limpe a sessão expirada', async () => {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ error: 'Sessão expirada' }), { status: 401 })))
  await expect(api('/auth/me')).rejects.toMatchObject({ status: 401, message: 'Sessão expirada' })
})

test('uma resposta inválida não é apresentada como sucesso', async () => {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<html>erro</html>')))
  await expect(api('/auth/me')).rejects.toBeInstanceOf(ApiError)
})
