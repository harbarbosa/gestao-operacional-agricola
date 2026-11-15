import {expect,it} from 'vitest'
import {productionTotal,poolShares,pendingEvaluations,closureProblems} from './fieldView'
import type {AttendeeForm} from './field'
const row=(id:string,quantity:string,poolKey='',score='4',present=true):AttendeeForm=>({key:id,worker:id,quantity,poolKey,score,present,notes:'',timeIn:'',timeOut:'',nextDay:false})
it('totaliza grupos uma única vez e ignora ausências',()=>expect(productionTotal({rows:[row('1','10'),row('2','','g'),row('3','','g'),row('4','100','', '4',false)],pools:[{pool_key:'g',name:'Grupo',quantity:'50'}]})).toBe('60'))
it('reparte até quatro casas e atribui o resíduo por id como no servidor',()=>{expect(poolShares('1',['3','1','2'])).toEqual({'1':'0.3334','2':'0.3333','3':'0.3333'});expect(poolShares('0',[])).toEqual({});expect(poolShares('50',['2','1'])).toEqual({'1':'25','2':'25'})})
it('avalia apenas os presentes e preserva zero de produção',()=>{expect(pendingEvaluations({rows:[row('1','0','',''),row('2','','','',false)]}).map(r=>r.worker)).toEqual(['1']);expect(productionTotal({rows:[row('1','0')],pools:[]})).toBe('0')})

it('exige produção individual e pelo menos dois participantes em cada grupo antes de encerrar',()=>{expect(closureProblems({rows:[row('1','','g')],pools:[{pool_key:'g',name:'Grupo 1',quantity:'50'}]})).toHaveLength(1);expect(closureProblems({rows:[row('1','')],pools:[]})).toHaveLength(1);expect(closureProblems({rows:[row('1','0')],pools:[]})).toEqual([])})
