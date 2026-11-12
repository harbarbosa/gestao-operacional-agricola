import 'fake-indexeddb/auto'
import {beforeEach,afterAll,expect,it,vi} from 'vitest'
import {api,ApiError} from './api'
import {offlineDb,rememberOfflineUser,activeOwner,setOfflineMode} from './offlineStore'
const user={id:9,username:'Campo',groups:['supervisor'],permissions:['field.record']}
beforeEach(async()=>{const s=new Map<string,string>();vi.stubGlobal('localStorage',{getItem:(k:string)=>s.get(k)??null,setItem:(k:string,v:string)=>s.set(k,v),removeItem:(k:string)=>s.delete(k)});await offlineDb.profiles.clear();await rememberOfflineUser(user);setOfflineMode(false)})
afterAll(()=>{offlineDb.close();vi.unstubAllGlobals()})
it('permite reabrir offline apenas uma conta previamente autenticada',async()=>{vi.stubGlobal('fetch',vi.fn().mockRejectedValue(new TypeError('Sem rede')));expect(await api('/auth/me')).toEqual({user});await api('/auth/logout',{method:'POST',body:'{}'});expect(activeOwner()).toBe(0);await expect(api('/auth/me')).rejects.toThrow('Sem rede');expect(await offlineDb.profiles.get(9)).toBeDefined()})
it('não transforma uma rejeição do servidor em autenticação offline',async()=>{vi.stubGlobal('fetch',vi.fn().mockResolvedValue(new Response(JSON.stringify({error:'Conta desativada'}),{status:401})));await expect(api('/auth/me')).rejects.toBeInstanceOf(ApiError)})
it('uma falha de armazenamento ao trocar de conta não expõe a preparação da conta anterior',async()=>{vi.stubGlobal('fetch',vi.fn().mockResolvedValue(new Response(JSON.stringify({user:{...user,id:10,username:'Outra conta'}}),{status:200})));const failure=vi.spyOn(offlineDb.profiles,'put').mockRejectedValueOnce(new DOMException('Quota','QuotaExceededError'));expect(await api('/auth/me')).toMatchObject({user:{id:10}});expect(activeOwner()).toBe(0);failure.mockRestore()})
