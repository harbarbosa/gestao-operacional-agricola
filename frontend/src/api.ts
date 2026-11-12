import {offlineMode,storeWorkerPhoto,offlineRead,createLocalWorker,rememberOfflineUser,offlineUser,deactivateOffline} from './offlineStore'
export interface CurrentUser {
  id: number
  username: string
  groups: string[]
  permissions: string[]
}

export class ApiError extends Error {
  status: number
  fields: Record<string, string>
  constructor(status: number, message: string, fields: Record<string, string> = {}) { super(message); this.status = status; this.fields = fields }
}

async function requestFetch(url:string,options:RequestInit){
  const timeout=!options.signal&&typeof AbortSignal!=='undefined'&&typeof AbortSignal.timeout==='function'
  try{return await fetch(url,{...options,...(timeout?{signal:AbortSignal.timeout(30000)}:{})})}
  catch(error){if(timeout&&error instanceof Error&&['TimeoutError','AbortError'].includes(error.name))throw new TypeError('A ligação demorou demasiado. O envio não foi confirmado.');throw error}
}

export async function networkApi<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  if (options.body) {
    if (!(options.body instanceof FormData)) headers.set('Content-Type', 'application/json')
    const tokenResponse = await requestFetch('/api/v1/auth/csrf', { credentials: 'same-origin', cache: 'no-store' })
    if (!tokenResponse.ok) throw new ApiError(tokenResponse.status, 'Não foi possível preparar a sessão.')
    const token = await tokenResponse.json() as { token: string }
    headers.set('X-CSRF-TOKEN', token.token)
  }
  const response = await requestFetch(`/api/v1${path}`, { ...options, headers, credentials: 'same-origin', cache: 'no-store' })
  const data = await response.json().catch(() => null) as (T & { error?: string; fields?: Record<string, string> }) | null
  if (!response.ok) throw new ApiError(response.status, data?.error ?? 'Não foi possível concluir o pedido. Atualize a sessão e tente novamente.', data?.fields ?? {})
  if (!data) throw new ApiError(response.status, 'Resposta inválida do servidor.')
  return data
}

export async function api<T>(path:string,options:RequestInit={}):Promise<T>{
  const portrait=path.match(/^\/workers\/(-?\d+)\/photo$/)
  if(offlineMode()&&portrait&&options.method==='POST'&&options.body instanceof FormData)return await storeWorkerPhoto(Number(portrait[1]),options.body) as T
  if(offlineMode()&&path==='/field/workers'&&options.method==='POST'){
    const input=JSON.parse(String(options.body));return await createLocalWorker({name:input.name,phone:input.phone??''}) as T
  }
  if(offlineMode()&&!options.method){const cached=await offlineRead(path);if(cached!==undefined)return cached as T}
  try{
    const result=await networkApi<T>(path,options)
    if(path==='/auth/me'||path==='/auth/login'){const user=(result as {user:CurrentUser}).user;try{await rememberOfflineUser(user)}catch{/* online operation stays available when storage is unavailable */}}
    if(path==='/auth/logout')deactivateOffline()
    return result
  }catch(error){
    if(error instanceof ApiError&&error.status===401&&['/auth/me','/auth/login'].includes(path))deactivateOffline()
    if(error instanceof TypeError){
      if(path==='/auth/me'){const user=await offlineUser().catch(()=>null);if(user)return {user} as T}
      if(path==='/auth/logout'){deactivateOffline();return {} as T}
      if(!options.method){const cached=await offlineRead(path).catch(()=>undefined);if(cached!==undefined)return cached as T}
    }
    throw error
  }
}
