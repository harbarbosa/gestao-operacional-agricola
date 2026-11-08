export interface CurrentUser {
  id: number
  username: string
  groups: string[]
  permissions: string[]
}

export class ApiError extends Error {
  status: number
  constructor(status: number, message: string) { super(message); this.status = status }
}

export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  if (options.body) {
    headers.set('Content-Type', 'application/json')
    const tokenResponse = await fetch('/api/v1/auth/csrf', { credentials: 'same-origin', cache: 'no-store' })
    if (!tokenResponse.ok) throw new ApiError(tokenResponse.status, 'Não foi possível preparar a sessão.')
    const token = await tokenResponse.json() as { token: string }
    headers.set('X-CSRF-TOKEN', token.token)
  }
  const response = await fetch(`/api/v1${path}`, { ...options, headers, credentials: 'same-origin', cache: 'no-store' })
  const data = await response.json().catch(() => null) as (T & { error?: string }) | null
  if (!response.ok) throw new ApiError(response.status, data?.error ?? 'Não foi possível concluir o pedido. Atualize a sessão e tente novamente.')
  if (!data) throw new ApiError(response.status, 'Resposta inválida do servidor.')
  return data
}
