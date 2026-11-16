// @vitest-environment happy-dom
import {act,useState} from 'react'
import {createRoot,type Root} from 'react-dom/client'
import {beforeEach,afterEach,expect,it,vi} from 'vitest'
import FieldParticipants from './FieldParticipants'
import type {AttendeeForm} from './field'
vi.mock('./WorkerPortrait',()=>({default:()=> <span>AM</span>}))
vi.mock('./WorkerHistory',()=>({default:()=> <p>Histórico</p>,workerStatus:()=>''}))
const initial:AttendeeForm={key:'a',worker:'1',present:true,timeIn:'',timeOut:'',nextDay:false,quantity:'10',score:'',notes:'',poolKey:''}
let root:Root,host:HTMLDivElement,latest:AttendeeForm[]
function Harness(){const [rows,setRows]=useState([initial]),[active,setActive]=useState<string|null>(null);latest=rows;return <FieldParticipants rows={rows} onChange={setRows} pools={[]} unit="caixas" disabled={false} names={{'1':'Ana Martins'}} info={{}} active={active} onOpen={setActive} onPhoto={vi.fn()} saveState="Guardado no dispositivo" photos={()=>null}/>}
beforeEach(()=>{Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});HTMLDialogElement.prototype.showModal=function(){this.open=true};HTMLDialogElement.prototype.close=function(){this.open=false};host=document.createElement('div');document.body.append(host);root=createRoot(host)})
afterEach(async()=>{await act(async()=>root.unmount());host.remove()})
async function render(){await act(async()=>root.render(<Harness/>))}
async function open(){await act(async()=>host.querySelector<HTMLButtonElement>('.field-person')!.click())}
it('mantém os cards sem ações concorrentes ou campos de lançamento',async()=>{await render();expect(host.querySelector('input')).toBeNull();expect(host.textContent).not.toContain('Ver histórico');expect(host.textContent).not.toContain('Fotografia da produção');expect(host.querySelector('.field-person')!.textContent).toContain('Por avaliar')})
it('grava cada alteração e reabre sem apagar a produção ou duplicar o trabalhador',async()=>{await render();await open();const input=host.querySelector<HTMLInputElement>('#field-quantity')!;await act(async()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(input,'24');input.dispatchEvent(new Event('input',{bubbles:true}))});await act(async()=>[...host.querySelectorAll<HTMLButtonElement>('.field-rating button')].find(b=>b.textContent==='4')!.click());expect(latest).toHaveLength(1);expect(latest[0]).toMatchObject({quantity:'24',score:'4',key:'a'});await act(async()=>[...host.querySelectorAll('button')].find(b=>b.textContent==='Concluir e voltar')!.click());await open();expect(host.querySelector<HTMLInputElement>('#field-quantity')!.value).toBe('24');expect(host.querySelector('.field-rating button[aria-pressed=true]')!.textContent).toBe('4');expect(host.querySelector('input[type=time]')).toBeNull()})
it('pede confirmação antes de remover um trabalhador com lançamentos',async()=>{await render();await open();await act(async()=>[...host.querySelectorAll('button')].find(b=>b.textContent==='Remover trabalhador do trabalho')!.click());expect(latest).toHaveLength(1);expect(document.querySelector('dialog')!.textContent).toContain('Este trabalhador tem lançamentos');await act(async()=>[...document.querySelectorAll('dialog button')].find(b=>b.textContent==='Confirmar remoção')!.dispatchEvent(new MouseEvent('click',{bubbles:true})));expect(latest).toHaveLength(0)})
