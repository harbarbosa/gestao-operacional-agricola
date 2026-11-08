import { expect, test } from 'vitest'
import { payloadFor } from './catalog'

test('unidades de pagamento e cobrança são independentes e não recebe campos ocultos', () => {
  const data = payloadFor('services', { name: ' Colheita ', payment_unit_id: '1', billing_unit_id: '3', role: 'admin' }, true, 2)
  expect(data).toEqual({ name: 'Colheita', notes: '', payment_unit_id: 1, billing_unit_id: 3, is_active: true, version: 2 })
  expect(data).not.toHaveProperty('role')
})

test('trabalhador pode ficar sem equipa e desativação usa booleano', () => {
  const data = payloadFor('workers', { name: 'Exemplo', team_id: '' }, false)
  expect(data.team_id).toBeNull()
  expect(data.is_active).toBe(false)
  expect(data).not.toHaveProperty('version')
})
