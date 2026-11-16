import {networkApi,ApiError,type CurrentUser} from './api'
import {activeOwner,rememberOfflineUser,offlineDb,importSnapshot,notifyOffline,type Snapshot,type DayDraft} from './offlineStore'
import {attendeePayload} from './field'
import {canonicalDecimal} from './pricing'
let running:Promise<void>|null=null
export async function prepareOffline(owner:number){const user=(await networkApi<{user:CurrentUser}>('/auth/me')).user;if(user.id!==owner||activeOwner()!==owner)throw Error('Inicie sessão na conta que preparou estes dados.');await rememberOfflineUser(user);const s=await networkApi<Snapshot>('/field/offline');await importSnapshot(s);try{await navigator.storage?.persist?.()}catch{/* preparation remains durable within the browser quota */}return s}
export function syncOffline(owner:number):Promise<void>{if(running)return running;running=(async()=>{if(typeof navigator!=='undefined'&&navigator.locks){await navigator.locks.request(`agroperi-sync-${owner}`,()=>perform(owner))}else await perform(owner)})().finally(()=>{running=null;notifyOffline()});return running}
async function perform(owner:number){
 if(activeOwner()!==owner)throw Error('Inicie sessão na conta destes dados.');const user=(await networkApi<{user:CurrentUser}>('/auth/me')).user;if(user.id!==owner)throw Error('A sessão atual pertence a outro utilizador. Inicie sessão na conta original.');await rememberOfflineUser(user)
 const workers=await offlineDb.workers.where('owner').equals(owner).toArray()
 for(const w of workers)if(!w.remoteId){try{const r=await networkApi<{item:{id:number}}>('/field/offline/apply',{method:'POST',body:JSON.stringify({kind:'worker',request_id:w.requestId,payload:{name:w.name,phone:w.phone}})});await offlineDb.workers.update(w.id,{remoteId:r.item.id,error:undefined})}catch(e){await offlineDb.workers.update(w.id,{error:message(e)});throw e}}
 const mapping=new Map((await offlineDb.workers.where('owner').equals(owner).toArray()).map(w=>[w.id,w.remoteId]))
 const portraits=await offlineDb.portraits.where('owner').equals(owner).toArray()
 for(const p of portraits.filter(p=>!p.synced)){try{const worker=p.workerId<0?mapping.get(p.workerId):p.workerId;if(!worker)throw Error('Trabalhador ainda pendente de envio.');const form=new FormData();form.append('photo',p.blob,'worker.jpg');form.append('request_id',p.requestId);form.append('version',String(p.remoteVersion));const r=await networkApi<{photo_version:number}>(`/workers/${worker}/photo`,{method:'POST',body:form});await offlineDb.portraits.update(p.key,{remoteVersion:r.photo_version,synced:true,error:undefined})}catch(error){await offlineDb.portraits.update(p.key,{error:message(error)});if(error instanceof TypeError||error instanceof ApiError&&error.status===401)throw error}}
 const drafts=await offlineDb.drafts.where('owner').equals(owner).sortBy('updated')
 for(const captured of drafts){if(activeOwner()!==owner)throw Error('A conta mudou durante o envio.');let draft=await offlineDb.drafts.get(captured.id);if(!draft||draft.status!=='draft'||draft.error)continue
  try{
   const data=await offlineDb.snapshots.get(owner);const contract=data?.contracts.find(c=>Number(c.id)===draft!.contractId);if(!contract)throw Error('Atualize a preparação: contrato indisponível neste dispositivo.')
   if(draft.revision>draft.syncedRevision){const d=draft;const body={kind:'work',request_id:d.requestId,payload:{client_id:d.id,version:d.remoteVersion,data:{harvest_id:d.contractId,work_date:d.date,notes:d.notes,billing_quantity:d.billing,attendees:d.rows.map(r=>{const row=attendeePayload(r);if(row.worker_id<0){const id=mapping.get(row.worker_id);if(!id)throw Error('Trabalhador ainda pendente de envio.');row.worker_id=id}return row}),pools:d.pools.map(p=>({...p,quantity:canonicalDecimal(p.quantity)}))}}}
    const result=await networkApi<{item:{id:number;version:number}}>('/field/offline/apply',{method:'POST',body:JSON.stringify(body)})
    await offlineDb.transaction('rw',offlineDb.drafts,async()=>{const current=await offlineDb.drafts.get(d.id);if(current?.owner===owner)await offlineDb.drafts.update(d.id,{remoteId:result.item.id,remoteVersion:result.item.version,syncedRevision:d.revision,error:undefined})});draft=await offlineDb.drafts.get(d.id)
   }
   if(!draft?.remoteId)continue
   // Never upload a photograph against an older participant list.
   if(draft.revision>draft.syncedRevision)continue
   const photos=await offlineDb.photos.where('draftId').equals(draft.id).toArray()
   for(const p of photos.filter(p=>p.owner===owner&&!p.synced&&!p.skipped)){const form=new FormData();form.append('request_id',p.id);form.append('photo',p.blob,'production.jpg');form.append('description',p.description);if(p.worker){const worker=Number(p.worker);form.append('worker_id',String(worker<0?mapping.get(worker):worker))}if(p.pool)form.append('pool_key',p.pool);const result=await networkApi<{id:number}>(`/work-logs/${draft.remoteId}/photos`,{method:'POST',body:form});await offlineDb.photos.update(p.id,{synced:true,remoteId:result.id,error:undefined})}
   draft=await offlineDb.drafts.get(draft.id)
   if(draft?.finish&&draft.revision===draft.syncedRevision){const result=await networkApi<{item:{version:number}}>('/field/offline/apply',{method:'POST',body:JSON.stringify({kind:'submit',request_id:draft.submitRequest,payload:{id:draft.remoteId,version:draft.remoteVersion}})});await offlineDb.drafts.update(draft.id,{status:'submitted',remoteVersion:result.item.version,error:undefined})}
  }catch(e){if(e instanceof TypeError||e instanceof ApiError&&e.status===401)throw e;await offlineDb.drafts.update(captured.id,{error:message(e)});notifyOffline()}
 }
 notifyOffline()
}
export function message(error:unknown){return error instanceof TypeError?'Sem ligação ao servidor. Os dados continuam guardados neste dispositivo.':error instanceof Error?error.message:'Não foi possível confirmar o envio.'}
export async function retryDraft(draft:DayDraft){await offlineDb.drafts.update(draft.id,{error:undefined});notifyOffline()}
