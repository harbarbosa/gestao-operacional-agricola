import Dexie,{type EntityTable} from 'dexie'
import type {CurrentUser} from './api'
import type {AttendeeForm,WorkLog} from './field'
import type {WorkerInfo} from './WorkerHistory'
export type OfflineWorker=WorkerInfo&{phone:string;is_active:boolean;owner?:number;requestId?:string;remoteId?:number;error?:string}
export type FieldContract={id:number;name:string;status:string;starts_on:string;client_id:number;client_name:string;site_id:number;site_name:string;service_id:number;service_name:string;payment_unit_id:number;payment_unit_name:string;billing_unit_id:number;billing_unit_name:string}
export type Snapshot={owner:number;prepared_at:string;contracts:FieldContract[];workers:OfflineWorker[];logs:WorkLog[]}
export type DayDraft={id:string;owner:number;contractId:number;date:string;rows:AttendeeForm[];pools:{pool_key:string;name:string;quantity:string}[];notes:string;billing:string|null;revision:number;requestId:string;syncedRevision:number;remoteId?:number;remoteVersion:number;finish:boolean;submitRequest:string;status:WorkLog['status'];error?:string;updated:number}
export type LocalPhoto={id:string;owner:number;draftId:string;blob:Blob;description:string;worker:string;pool:string;synced:boolean;remoteId?:number;error?:string;skipped?:boolean}
export type WorkerPhoto={key:string;owner:number;workerId:number;blob:Blob;requestId:string;remoteVersion:number;synced:boolean;error?:string}
type Profile={owner:number;user:CurrentUser;expires:number}
export const offlineDb=new Dexie('agroperi-field-v1') as Dexie&{profiles:EntityTable<Profile,'owner'>;snapshots:EntityTable<Snapshot,'owner'>;drafts:EntityTable<DayDraft,'id'>;workers:EntityTable<OfflineWorker&{owner:number},'id'>;photos:EntityTable<LocalPhoto,'id'>;portraits:EntityTable<WorkerPhoto,'key'>}
offlineDb.version(1).stores({profiles:'owner',snapshots:'owner',drafts:'id,owner,updated',workers:'id,owner',photos:'id,owner,draftId'})
offlineDb.version(2).stores({portraits:'key,owner,workerId'})
let mode=false
export const setOfflineMode=(value:boolean)=>{mode=value}
export const offlineMode=()=>mode
export const activeOwner=()=>{try{return Number(localStorage.getItem('agroperi-field-owner'))||0}catch{return 0}}
export const notifyOffline=()=>{if(typeof window!=='undefined')window.dispatchEvent(new Event('agroperi-offline'))}
export function deactivateOffline(){try{localStorage.removeItem('agroperi-field-owner')}catch{/* no browser storage */}mode=false}
export async function rememberOfflineUser(user:CurrentUser){if(activeOwner()!==user.id)deactivateOffline();if(typeof indexedDB==='undefined')return;if(!user.permissions.includes('field.record')){deactivateOffline();return;}await offlineDb.profiles.put({owner:user.id,user,expires:Date.now()+7*86400000});localStorage.setItem('agroperi-field-owner',String(user.id))}
export async function offlineUser(){if(typeof indexedDB==='undefined')return null;const p=await offlineDb.profiles.get(activeOwner());return p&&p.owner===activeOwner()&&p.expires>Date.now()?p.user:null}
export async function getSnapshot(owner:number){return offlineDb.snapshots.get(owner)}
export async function allWorkers(owner:number){const s=await getSnapshot(owner);const local=await offlineDb.workers.where('owner').equals(owner).toArray();const mapped=new Set(local.map(w=>w.remoteId).filter(Boolean));return [...(s?.workers??[]).filter(w=>!mapped.has(w.id)),...local.map(w=>{const fresh=s?.workers.find(r=>r.id===w.remoteId);return fresh?{...w,...fresh,id:w.id,remoteId:w.remoteId,owner}:w})]}
export async function saveLocalDraft(draft:DayDraft){await offlineDb.transaction('rw',offlineDb.drafts,async()=>{const old=await offlineDb.drafts.get(draft.id);if(old&&old.owner!==draft.owner)throw Error('Trabalho de outro utilizador.');if(old&&old.revision>=draft.revision)throw Error('Este trabalho mudou noutro separador. Reabra os dados guardados antes de continuar.');await offlineDb.drafts.put({...draft,remoteId:old?.remoteId??draft.remoteId,remoteVersion:old?.remoteVersion??draft.remoteVersion,syncedRevision:old?.syncedRevision??draft.syncedRevision})});notifyOffline()}
export async function createLocalWorker(values:{name:string;phone:string}){
 const user=await offlineUser();if(!user)throw Error('Inicie sessão com internet antes de preparar o campo.');if(!values.name.trim())throw Error('Preencha o nome completo.')
 const id=-Number(BigInt('0x'+crypto.randomUUID().replaceAll('-','').slice(0,12)))
 const item:OfflineWorker&{owner:number}={owner:user.id,id,name:values.name.trim(),phone:values.phone,is_active:true,is_blocked:false,block_reason:null,version:1,evaluation_count:0,performance_avg:null,low_performance:false,evaluations:[],requestId:crypto.randomUUID()};await offlineDb.workers.put(item);notifyOffline();return {item}
}
export async function importSnapshot(s:Snapshot){
 if(s.owner!==activeOwner())throw Error('A preparação pertence a outro utilizador.');
 const aliases=new Map((await offlineDb.workers.where('owner').equals(s.owner).toArray()).filter(w=>w.remoteId).map(w=>[w.remoteId!,w.id]));
 await offlineDb.transaction('rw',offlineDb.snapshots,offlineDb.drafts,async()=>{
  await offlineDb.snapshots.put(s)
  for(const log of s.logs){const old=await offlineDb.drafts.get(log.request_id);if(old&&(old.revision>old.syncedRevision||old.finish&&old.status==='draft'))continue
   const d:DayDraft={id:log.request_id,owner:s.owner,contractId:Number(log.harvest_id),date:log.work_date,rows:log.attendees.map(a=>({key:String(a.worker_id),worker:String(aliases.get(a.worker_id)??a.worker_id),present:a.present,timeIn:a.time_in??'',timeOut:a.time_out??'',nextDay:a.exit_next_day,quantity:a.payment_quantity??'',score:String(a.score??''),notes:a.notes??'',poolKey:a.pool_key??''})),pools:log.pools??[],notes:log.notes??'',billing:log.billing_quantity,revision:0,requestId:crypto.randomUUID(),syncedRevision:0,remoteId:log.id,remoteVersion:log.version,finish:false,submitRequest:crypto.randomUUID(),status:log.status,updated:Date.now()};await offlineDb.drafts.put(d)
  }
 });notifyOffline()
}
export async function offlineRead(path:string):Promise<unknown|undefined>{
 const user=await offlineUser();if(!user)return undefined;const s=await getSnapshot(user.id);if(!s||activeOwner()!==user.id)return undefined
 const url=new URL(path,'https://offline.local');const match=url.pathname.match(/^\/(?:field\/options\/workers|workers)(?:\/(-?\d+)(?:\/history)?)?$/)
 if(match){const workers=await allWorkers(user.id);if(match[1]){const item=workers.find(w=>String(w.id)===match[1]||String(w.remoteId)===match[1]);if(!item)throw Error('Trabalhador não preparado neste dispositivo.');return {item}}const q=(url.searchParams.get('q')??'').toLocaleLowerCase('pt-PT');const items=workers.filter(w=>w.name.toLocaleLowerCase('pt-PT').includes(q));return {items:items.slice(0,25),total:items.length,page:1,pageSize:25}}
 return undefined
}

export async function storeWorkerPhoto(workerId:number,form:FormData){
 const user=await offlineUser();if(!user)throw Error('Inicie sessão com internet antes de guardar fotografias offline.');const blob=form.get('photo');if(!(blob instanceof Blob)||blob.size>1024*1024||!['image/jpeg','image/png','image/webp'].includes(blob.type))throw Error('Fotografia inválida.');
 await offlineDb.portraits.put({key:`${user.id}:${workerId}`,owner:user.id,workerId,blob,requestId:String(form.get('request_id')),remoteVersion:Number(form.get('version')),synced:false});notifyOffline();return {photo_version:Number(form.get('version'))+1}
}
