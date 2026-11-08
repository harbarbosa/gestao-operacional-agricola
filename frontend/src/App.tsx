import { useEffect, useState } from 'react'
import { db, saveDraft } from './db'
import './App.css'

export default function App() {
  const [online, setOnline] = useState(navigator.onLine)
  const [notes, setNotes] = useState('')
  const [date, setDate] = useState(() => { const now = new Date(); return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}` })
  const [ready, setReady] = useState(false)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('A carregar rascunho…')
  useEffect(() => {
    const on = () => setOnline(true)
    const off = () => setOnline(false)
    window.addEventListener('online', on)
    window.addEventListener('offline', off)
    let active = true
    db.drafts.get('demo').then(draft => {
      if (!active) return
      if (draft) { setNotes(draft.notes); setDate(draft.workDate) }
      setReady(true)
      setMessage(draft ? 'Rascunho recuperado do aparelho.' : '')
    }).catch(() => { if (active) setMessage('Não foi possível abrir o armazenamento local. O registo está indisponível.') })
    return () => { active = false; window.removeEventListener('online', on); window.removeEventListener('offline', off) }
  }, [])
  async function save(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    try {
      await saveDraft({ id: 'demo', workDate: date, notes, updatedAt: new Date().toISOString() })
      setMessage('Guardado apenas neste aparelho. Ainda não existe sincronização.')
    } catch { setMessage('Falha ao guardar. Mantenha esta página aberta e tente novamente.') }
    finally { setSaving(false) }
  }
  return <main>
    <header><span className="brand">GESTÃO AGRÍCOLA</span><span className="badge">{online ? 'Ligação disponível' : 'Sem ligação'}</span></header>
    <section className="intro"><p>BASE DE DESENVOLVIMENTO</p><h1>A operação começa no campo.</h1><p>Primeira estrutura do sistema de gestão de equipas, produção e serviços.</p></section>
    <aside><strong>Protótipo técnico</strong><p>Use apenas dados fictícios. Autenticação, contas individuais e sincronização ainda não estão implementadas. A ligação indicada não confirma disponibilidade do servidor.</p></aside>
    <section className="card"><h2>Rascunho de campo</h2><p>Guarde uma nota e reabra a aplicação para verificar a persistência local.</p>
      <form onSubmit={save}><label htmlFor="date">Data do trabalho</label><input id="date" type="date" required value={date} disabled={!ready} onChange={e => setDate(e.target.value)} />
        <label htmlFor="notes">Observações de demonstração</label><textarea id="notes" required maxLength={2000} value={notes} disabled={!ready} onChange={e => setNotes(e.target.value)} />
        <button disabled={!ready || saving}>{saving ? 'A guardar…' : 'Guardar no aparelho'}</button><p role="status" aria-live="polite">{message}</p>
      </form>
    </section>
    <footer>Uma empresa · Android e iPhone · Registo offline em desenvolvimento</footer>
  </main>
}
