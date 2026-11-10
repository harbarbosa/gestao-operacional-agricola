import { expect, test } from 'vitest'
import { formatCurrency, formatDate, formatDateTime, formatNumber, maskDecimal, maskPhone } from './locale'
import { canonicalDecimal } from './pricing'
test('formata datas portuguesas e usa Lisboa com horário de verão', () => {
  expect(formatDate('2026-11-09')).toBe('09/11/2026')
  expect(formatDateTime('2026-07-10 08:00:00')).toContain('09:00')
  expect(formatDateTime('2026-11-10 08:00:00')).toContain('08:00')
})
test('mantém precisão decimal e máscaras compatíveis com os payloads', () => {
  expect(formatCurrency('1234.5000')).toBe('1 234,50 €')
  expect(formatNumber('99999999.9999')).toBe('99 999 999,9999')
  expect(maskDecimal('1234,50')).toBe('1 234,50')
  expect(canonicalDecimal(maskDecimal('99999999,9999'))).toBe('99999999.9999')
})
test('aplica máscara portuguesa e preserva prefixos internacionais explícitos', () => {
  expect(maskPhone('912345678')).toBe('+351 912 345 678')
  expect(maskPhone('+351912345678')).toBe('+351 912 345 678')
  expect(maskPhone('')).toBe('')
  expect(maskPhone('+5516999999999')).toMatch(/^\+55/)
})
