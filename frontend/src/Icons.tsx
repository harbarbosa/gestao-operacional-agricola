export function Brand() {
  return <div className="brand"><svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#bce84b" d="M24 39C20 17 29 7 45 6c0 19-8 30-21 33Z"/><path fill="#80b92e" d="M22 38C5 36 2 25 3 14c14 0 22 9 19 24Z"/><path d="m21 42 13-23" fill="none" stroke="#edf7d9" strokeWidth="2"/></svg><span>Agroperi</span></div>
}
const paths: Record<string, string> = {
 menu: 'M3 6h18M3 12h18M3 18h18',
 edit: 'm14 5 5 5M4 20l5-1L21 7l-5-5L4 14ZM3 22h18',
 trash: 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7',
 view: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
 end: 'M5 3v18M5 4h14l-3 5 3 5H5',
 home: 'm3 10 9-7 9 7v11h-6v-7H9v7H3Z',
 clipboard: 'M8 5H5v16h14V5h-3M9 3h6v5H9ZM8 12h8M8 16h6',
 workers: 'M16 21v-3a4 4 0 0 0-8 0v3M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
 teams: 'M16 21v-3a4 4 0 0 0-8 0v3M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM20 21v-4a4 4 0 0 0-3-4M4 21v-4a4 4 0 0 1 3-4M19 4a4 4 0 0 1 0 6M5 4a4 4 0 0 0 0 6',
 clients: 'm3 9 5-5 4 2 4-2 5 5-4 9-4 3-5-3ZM7 10l4-3 6 5-4 5-5-4',
 sites: 'M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
 services: 'm3 8 9-5 9 5-9 5ZM3 12l9 5 9-5M3 16l9 5 9-5',
 prices: 'M3 6h18v13H3ZM8 10h8M8 15h4',
 units: 'M4 4h16v16H4ZM8 4v5M12 4v3M16 4v5',
 settings: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1Z',
 calendar: 'M4 5h16v16H4ZM8 2v6M16 2v6M4 10h16',
 chart: 'M4 21V13M10 21V8M16 21V3M22 21H2',
 arrow: 'M4 7h16l-4-4M20 17H4l4 4',
}
export function Icon({name}: {name: string}) { return <svg className="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={paths[name] ?? paths.clipboard}/></svg> }
