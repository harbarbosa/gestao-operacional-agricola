import Dexie, { type Table } from 'dexie'

export interface Draft {
  id: string
  workDate: string
  notes: string
  updatedAt: string
}

export class FieldDatabase extends Dexie {
  drafts!: Table<Draft, string>
  constructor(name = 'agricola-prototype') {
    super(name)
    this.version(1).stores({ drafts: 'id, workDate, updatedAt' })
  }
}
export const db = new FieldDatabase()
export async function saveDraft(draft: Draft, database = db) {
  await database.drafts.put(draft)
}
