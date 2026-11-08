import { expect, test } from 'vitest'
import { attendeePayload, type AttendeeForm } from './field'
const row: AttendeeForm = { key: 'row', worker: '4', present: true, timeIn: '08:00', timeOut: '17:00', nextDay: false, quantity: '1,2500', score: '4', notes: 'Exemplo' }

test('quantidade permanece decimal em texto e horários não definem remuneração', () => {
  expect(attendeePayload(row)).toEqual({ worker_id: 4, present: true, time_in: '08:00', time_out: '17:00', exit_next_day: false, payment_quantity: '1.2500', score: 4, notes: 'Exemplo' })
})
test('ausência não envia quantidades, avaliação ou horários antigos', () => {
  expect(attendeePayload({ ...row, present: false, nextDay: true })).toEqual({ worker_id: 4, present: false, time_in: null, time_out: null, exit_next_day: false, payment_quantity: null, score: null, notes: 'Exemplo' })
})
test('entrada sem saída e quantidade em branco podem permanecer em rascunho', () => {
  expect(attendeePayload({ ...row, timeOut: '', quantity: '', score: '' })).toMatchObject({ time_in: '08:00', time_out: null, payment_quantity: null, score: null })
})
