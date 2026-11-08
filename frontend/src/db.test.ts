import 'fake-indexeddb/auto'
import { afterEach, expect, test } from 'vitest'
import { FieldDatabase, saveDraft } from './db'
const name = 'test-agricola'
afterEach(async () => { await new FieldDatabase(name).delete() })
test('recupera rascunho após reabrir e atualiza sem duplicar', async () => {
  const first = new FieldDatabase(name)
  const draft = { id: 'demo', workDate: '2026-11-08', notes: 'Exemplo', updatedAt: '2026-11-08T10:00:00Z' }
  await saveDraft(draft, first)
  first.close()
  const reopened = new FieldDatabase(name)
  expect(await reopened.drafts.get('demo')).toEqual(draft)
  await saveDraft({ ...draft, notes: 'Atualizado' }, reopened)
  expect(await reopened.drafts.count()).toBe(1)
  expect((await reopened.drafts.get('demo'))?.notes).toBe('Atualizado')
  reopened.close()
})
