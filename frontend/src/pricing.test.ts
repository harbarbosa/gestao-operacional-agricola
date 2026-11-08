import { expect, test } from 'vitest'
import { canonicalDecimal, scopeFor } from './pricing'

test('preserva quatro casas e valores mínimos sem ponto flutuante', () => {
  expect(canonicalDecimal(' 0,0001 ')).toBe('0.0001')
  expect(canonicalDecimal('99999999.9999')).toBe('99999999.9999')
  expect(canonicalDecimal('00012,50')).toBe('12.50')
  expect(canonicalDecimal('0')).toBe('0')
})

test('rejeita separador de milhares, expoentes, negativos e casas excessivas', () => {
  for (const value of ['1.234,56', '1e3', '-2', '0.00001', '', '100000000']) expect(() => canonicalDecimal(value)).toThrow()
})

test('trabalhador só pertence a pagamento e cliente só a cobrança', () => {
  expect(scopeFor('payment', '4')).toEqual({ kind: 'payment', worker_id: 4, client_id: null })
  expect(scopeFor('billing', '4')).toEqual({ kind: 'billing', worker_id: null, client_id: 4 })
  expect(scopeFor('payment', '')).toEqual({ kind: 'payment', worker_id: null, client_id: null })
})
