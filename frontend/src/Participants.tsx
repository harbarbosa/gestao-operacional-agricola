import {useEffect,useState,type SubmitEvent} from 'react'
import Modal from './Modal'
import WorkerHistory,{workerStatus,type WorkerInfo} from './WorkerHistory'
import {ReferenceField} from './Catalog'
import {api} from './api'
import {formatNumber,maskDecimal} from './locale'
import type {AttendeeForm} from './field'

type Pool={pool_key:string;name:string;quantity:string}
type Props={rows:AttendeeForm[];onChange:(rows:AttendeeForm[])=>void;pools:Pool[];unit:string;disabled:boolean;canAdd:boolean;initialNames:Record<string,string>;onPhoto?:(worker:string)=>void;onNamesChange?:(names:Record<string,string>)=>void}
export default function Participants({rows,onChange,pools,unit,disabled,canAdd,initialNames,onPhoto,onNamesChange}:Props){
 const [history,setHistory]=useState<number|null>(null)
 const [info,setInfo]=useState<Record<string,WorkerInfo>>({})
 const [editing,setEditing]=useState<AttendeeForm|null>(null)
 const [names,setNames]=useState<Record<string,string>>(initialNames)
 const [message,setMessage]=useState('')
 const ids=[...new Set(rows.map(row=>row.worker).filter(Boolean))].join(',')
 useEffect(()=>{let active=true;for(const id of ids.split(',').filter(Boolean))api<{item:WorkerInfo}>(`/field/options/workers/${id}`).then(r=>{if(active){setNames(old=>({...old,[id]:r.item.name}));onNamesChange?.({[id]:r.item.name});setInfo(old=>({...old,[id]:r.item}))}}).catch(()=>{});return()=>{active=false}},[ids])
 useEffect(()=>{let active=true;if(editing?.worker)api<{item:WorkerInfo}>(`/field/options/workers/${editing.worker}`).then(r=>{if(active)setInfo(old=>({...old,[editing.worker]:r.item}))}).catch(()=>{});return()=>{active=false}},[editing?.worker])

 function add(){setMessage('');setEditing({key:crypto.randomUUID(),worker:'',present:true,timeIn:'',timeOut:'',nextDay:false,quantity:'',score:'',notes:'',poolKey:''})}
 function update(changes:Partial<AttendeeForm>){setEditing(old=>old?{...old,...changes}:null)}
 function apply(e:SubmitEvent<HTMLFormElement>){e.preventDefault();e.stopPropagation();if(!editing)return;if(info[editing.worker]?.is_blocked){setMessage('Trabalhador bloqueado. Consulte o histórico.');return}if(!editing.worker){setMessage('Selecione um trabalhador.');return}if(rows.some(r=>r.key!==editing.key&&r.worker===editing.worker)){setMessage('Este trabalhador já está no diário.');return}onChange(rows.some(r=>r.key===editing.key)?rows.map(r=>r.key===editing.key?editing:r):[...rows,editing]);setEditing(null)}
 const row=editing
 const context={payment:unit}
 const busy=disabled
 function change(key:string,patch:Partial<AttendeeForm>){onChange(rows.map(r=>r.key===key?{...r,...patch}:r))}
 function increment(r:AttendeeForm,delta:number){const q=Number(r.quantity.replaceAll(' ','').replace(',','.'))||0;change(r.key,{quantity:maskDecimal(String(Math.max(0,Math.round((q+delta)*10000)/10000)))})}
 return <><div className="participant-list" role="list" aria-label="Trabalhadores do diário">{rows.map(r=>{
 const name=names[r.worker]??`Trabalhador #${r.worker}`
 const group=pools.find(p=>p.pool_key===r.poolKey)
 return <div className="participant-item" role="listitem" key={r.key}><button type="button" className="participant-open" disabled={disabled} onClick={()=>{setMessage('');setEditing({...r})}} aria-label={`Abrir trabalhador ${name}`}><span className="participant-name">{name}<small>{r.present?'Presente':'Ausente'}</small></span><span className="participant-production">{group?group.name:r.quantity?`${formatNumber(r.quantity.replaceAll(' ','').replace(',','.'))} ${unit}`:'Sem produção registada'}</span><span className="participant-score">{r.score?`Avaliação ${r.score}/5`:'Por avaliar'}</span><span aria-hidden="true">›</span></button><div className="participant-quick">{info[r.worker]&&<span className={info[r.worker].is_blocked?'worker-blocked':info[r.worker].low_performance?'worker-warning':'worker-quality'}>{workerStatus(info[r.worker])}</span>}<button type="button" disabled={disabled} onClick={()=>setHistory(Number(r.worker))}>Histórico</button><div className="quantity-stepper"><button type="button" aria-label={`Diminuir produção de ${name}`} disabled={disabled||!r.present||!!r.poolKey||info[r.worker]?.is_blocked} onClick={()=>increment(r,-1)}>−</button><input aria-label={`Produção de ${name}`} inputMode="decimal" value={r.quantity} placeholder="0" disabled={disabled||!r.present||!!r.poolKey||info[r.worker]?.is_blocked} onChange={e=>change(r.key,{quantity:maskDecimal(e.target.value)})}/><button type="button" aria-label={`Aumentar produção de ${name}`} disabled={disabled||!r.present||!!r.poolKey||info[r.worker]?.is_blocked} onClick={()=>increment(r,1)}>+</button><small>{group?group.name:unit}</small></div><div className={`quick-rating ${r.present&&!r.score?'rating-pending':''}`} aria-label={`Avaliação de ${name}`}>{[1,2,3,4,5].map(score=><button key={score} type="button" aria-label={`Avaliar ${name} com ${score}`} aria-pressed={r.score===String(score)} disabled={disabled||!r.present} onClick={()=>change(r.key,{score:String(score)})}>{score}</button>)}</div>{onPhoto&&<button type="button" disabled={disabled} onClick={()=>onPhoto(r.worker)}>Fotografia</button>}</div><button type="button" className="icon-action danger participant-remove" aria-label={`Remover trabalhador ${name}`} title="Remover participante" disabled={disabled} onClick={()=>onChange(rows.filter(item=>item.key!==r.key))}>×</button></div>
 })}</div>{!rows.length&&<p className="participant-empty">Nenhum trabalhador adicionado.</p>}<button type="button" disabled={disabled||!canAdd||rows.length>=100} onClick={add}>Adicionar participante</button>
 {history!==null&&<WorkerHistory id={history} onClose={()=>setHistory(null)}/>}
 {row&&<Modal title={rows.some(r=>r.key===row.key)?'Editar participante':'Adicionar participante'} busy={disabled} onClose={()=>setEditing(null)}><form className="record-form" onSubmit={apply}><fieldset disabled={busy}>
      <label htmlFor={`worker-${row.key}`}>Trabalhador</label><ReferenceField field={{ key: `worker-${row.key}`, label: 'Trabalhador', resource: 'workers', source: 'field', required: true }} value={row.worker} onChange={value => update({ worker: value })} disabled={busy} />
      <label className="checkbox"><input type="checkbox" checked={row.present} onChange={e => update({ present: e.target.checked, ...(!e.target.checked ? { timeIn: '', timeOut: '', nextDay: false, quantity: '', score: '', poolKey: '' } : {}) })} />Presente</label>
      <div className="field-grid"><div><label htmlFor={`in-${row.key}`}>Entrada</label><input id={`in-${row.key}`} type="time" lang="pt-PT" value={row.timeIn} disabled={!row.present} onChange={e => update({ timeIn: e.target.value })} /></div><div><label htmlFor={`out-${row.key}`}>Saída</label><input id={`out-${row.key}`} type="time" lang="pt-PT" value={row.timeOut} disabled={!row.present} onChange={e => update({ timeOut: e.target.value })} /></div></div>
      <label className="checkbox"><input type="checkbox" checked={row.nextDay} disabled={!row.present} onChange={e => update({ nextDay: e.target.checked })} />Saída no dia seguinte</label>
      {pools.length > 0 && <><label htmlFor={`group-${row.key}`}>Grupo de produção</label><select id={`group-${row.key}`} value={row.poolKey ?? ''} disabled={!row.present} onChange={event => update({poolKey:event.target.value,quantity:''})}><option value="">Produção individual</option>{pools.map(pool=><option key={pool.pool_key} value={pool.pool_key}>{pool.name} · divisão igual</option>)}</select></>}
      <label htmlFor={`quantity-${row.key}`}>Quantidade ({context?.payment ?? 'unidade a confirmar'})</label><input id={`quantity-${row.key}`} inputMode="decimal" maxLength={20} value={row.quantity} disabled={!row.present || !context || !!row.poolKey} onChange={e => update({ quantity: maskDecimal(e.target.value) })} />
      <label htmlFor={`score-${row.key}`}>Nota de desempenho (1 a 5)</label><select id={`score-${row.key}`} value={row.score} disabled={!row.present} onChange={e => update({ score: e.target.value })}><option value="">A avaliar</option>{[1, 2, 3, 4, 5].map(score => <option key={score} value={score}>{score}</option>)}</select>
      <label htmlFor={`notes-${row.key}`}>Observações individuais</label><textarea id={`notes-${row.key}`} maxLength={500} value={row.notes} onChange={e => update({ notes: e.target.value })} />
 {row.worker&&info[row.worker]&&<p className={info[row.worker].is_blocked?'worker-blocked':info[row.worker].low_performance?'worker-warning':''}>{workerStatus(info[row.worker])}<button type="button" onClick={()=>setHistory(Number(row.worker))}>Ver histórico</button></p>}
 </fieldset><p role="alert">{message}</p><div className="actions"><button disabled={disabled}>Confirmar participante</button><button type="button" disabled={disabled} onClick={()=>setEditing(null)}>Cancelar</button></div></form></Modal>}
 </>
}
