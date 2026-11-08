import { canonicalDecimal } from './pricing'

export interface Attendee {
  worker_id: number; worker_name: string; present: boolean; time_in: string | null; time_out: string | null
  exit_next_day: boolean; payment_quantity: string | null; score: number | null; notes: string | null
}
export interface WorkLog {
  id: number; request_id: string; version: number; status: 'draft' | 'canceled'; work_date: string
  team_id: number; site_id: number; client_id: number; service_id: number
  team_name: string; site_name: string; client_name: string; service_name: string; responsible_name: string | null
  payment_unit_id: number; payment_unit_name: string; payment_unit_code: string
  billing_unit_id: number; billing_unit_name: string; billing_unit_code: string
  notes: string | null; billing_quantity: string | null; attendees: Attendee[]; cancel_reason: string | null
}
export interface WorkLogList { items: WorkLog[]; total: number; page: number; pageSize: number }
export interface AttendeeForm {
  key: string; worker: string; present: boolean; timeIn: string; timeOut: string; nextDay: boolean; quantity: string; score: string; notes: string
}
export function attendeePayload(row: AttendeeForm) {
  return { worker_id: Number(row.worker), present: row.present,
    time_in: row.present && row.timeIn ? row.timeIn : null, time_out: row.present && row.timeOut ? row.timeOut : null,
    exit_next_day: row.present && row.nextDay, payment_quantity: row.present && row.quantity ? canonicalDecimal(row.quantity) : null,
    score: row.present && row.score ? Number(row.score) : null, notes: row.notes,
  }
}
