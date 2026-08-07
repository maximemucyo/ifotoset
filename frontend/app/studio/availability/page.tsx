'use client'

import { useState } from 'react'
import {
  Calendar, Clock, Plus, Loader2, Sparkles, X, Trash2,
  AlertCircle, CheckCircle2, Globe, CalendarDays, RefreshCw, AlertTriangle
} from 'lucide-react'
import {
  useAvailabilitySettings,
  useUpdateAvailabilitySettingsMutation,
  useAvailabilityExceptions,
  useCreateAvailabilityExceptionMutation,
  useDeleteAvailabilityExceptionMutation,
  useBlockedSlots,
  useCreateBlockedSlotMutation,
  useDeleteBlockedSlotMutation,
  WeeklySetting
} from '@/lib/queries/availability'

const DAYS_OF_WEEK = [
  'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'
]

// Standard time intervals list for H:i select inputs
const TIME_OPTIONS = Array.from({ length: 48 }, (_, i) => {
  const hour = Math.floor(i / 2)
  const minute = i % 2 === 0 ? '00' : '30'
  const displayHour = hour.toString().padStart(2, '0')
  return `${displayHour}:${minute}`
})

export default function AvailabilitySettings() {
  const [activeTab, setActiveTab] = useState<'weekly' | 'exceptions' | 'blocked'>('weekly')
  const [successMsg, setSuccessMsg] = useState<string | null>(null)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  // Settings Queries & Mutations
  const { data: settingsData, isLoading: isSettingsLoading } = useAvailabilitySettings()
  const updateSettingsMutation = useUpdateAvailabilitySettingsMutation()

  // Exceptions Queries & Mutations
  const { data: exceptionsData, isLoading: isExceptionsLoading } = useAvailabilityExceptions()
  const createExceptionMutation = useCreateAvailabilityExceptionMutation()
  const deleteExceptionMutation = useDeleteAvailabilityExceptionMutation()

  // Blocked Slots Queries & Mutations
  const { data: blockedData, isLoading: isBlockedLoading } = useBlockedSlots()
  const createBlockedMutation = useCreateBlockedSlotMutation()
  const deleteBlockedMutation = useDeleteBlockedSlotMutation()

  // Modals & Form states
  const [isExceptionOpen, setIsExceptionOpen] = useState(false)
  const [isBlockedOpen, setIsBlockedOpen] = useState(false)

  // Local state for weekly schedule form
  const [localSettings, setLocalSettings] = useState<WeeklySetting[]>([])
  const [localTimezone, setLocalTimezone] = useState('Africa/Kigali')
  const [localInterval, setLocalInterval] = useState(30)

  // Local state for adding exception
  const [exceptionForm, setExceptionForm] = useState({
    date: '',
    start_time: '09:00',
    end_time: '17:00',
    is_closed: false,
  })

  // Local state for adding blocked slot
  const [blockedForm, setBlockedForm] = useState({
    starts_at: '',
    ends_at: '',
    reason: '',
  })

  // Populate local weekly settings when query completes
  useState(() => {
    if (settingsData) {
      setLocalSettings(settingsData.settings)
      setLocalTimezone(settingsData.timezone)
      setLocalInterval(settingsData.slot_interval_minutes)
    }
  })

  // We can also trigger state update with useEffect once loaded
  const handleLoadSettings = () => {
    if (settingsData) {
      setLocalSettings(settingsData.settings)
      setLocalTimezone(settingsData.timezone)
      setLocalInterval(settingsData.slot_interval_minutes)
    }
  }

  // Auto load on completion
  if (settingsData && localSettings.length === 0) {
    handleLoadSettings()
  }

  // ── Weekly Schedule Update ──
  const handleWeeklySubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setSuccessMsg(null)
    setErrorMsg(null)

    updateSettingsMutation.mutate({
      settings: localSettings,
      timezone: localTimezone,
      slot_interval_minutes: Number(localInterval),
    }, {
      onSuccess: () => {
        setSuccessMsg('Weekly working schedule and timezone preferences saved successfully!')
        setTimeout(() => setSuccessMsg(null), 3000)
      },
      onError: (err: any) => {
        setErrorMsg(err.message || 'Failed to save weekly schedule.')
      }
    })
  }

  // ── Exceptions Update ──
  const handleExceptionSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setSuccessMsg(null)
    setErrorMsg(null)

    createExceptionMutation.mutate({
      date: exceptionForm.date,
      start_time: exceptionForm.is_closed ? null : exceptionForm.start_time,
      end_time: exceptionForm.is_closed ? null : exceptionForm.end_time,
      is_closed: exceptionForm.is_closed,
    }, {
      onSuccess: () => {
        setIsExceptionOpen(false)
        setSuccessMsg('Date exception policy saved.')
        setTimeout(() => setSuccessMsg(null), 3000)
        setExceptionForm({ date: '', start_time: '09:00', end_time: '17:00', is_closed: false })
      },
      onError: (err: any) => {
        setErrorMsg(err.message || 'Failed to save date exception.')
      }
    })
  }

  // ── Blocked Range Update ──
  const handleBlockedSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setSuccessMsg(null)
    setErrorMsg(null)

    createBlockedMutation.mutate({
      starts_at: new Date(blockedForm.starts_at).toISOString(),
      ends_at: new Date(blockedForm.ends_at).toISOString(),
      reason: blockedForm.reason || null,
    }, {
      onSuccess: () => {
        setIsBlockedOpen(false)
        setSuccessMsg('Time range blocked successfully.')
        setTimeout(() => setSuccessMsg(null), 3000)
        setBlockedForm({ starts_at: '', ends_at: '', reason: '' })
      },
      onError: (err: any) => {
        setErrorMsg(err.message || 'Failed to block range.')
      }
    })
  }

  const handleDeleteException = (uuid: string) => {
    if (!confirm('Remove this custom date exception?')) return
    deleteExceptionMutation.mutate(uuid)
  }

  const handleDeleteBlocked = (uuid: string) => {
    if (!confirm('Remove this manual blackout slot?')) return
    deleteBlockedMutation.mutate(uuid)
  }

  const isLoading = isSettingsLoading || isExceptionsLoading || isBlockedLoading

  const labelCls = 'block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5'
  const inputCls = 'w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary'

  return (
    <main className="flex-1 flex flex-col h-screen overflow-hidden bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-4 md:p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 flex-shrink-0">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-foreground tracking-tight">Availability</h1>
          <p className="text-xs md:text-sm text-muted-foreground mt-1">Configure your online appointment scheduler hours and blackout rules</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-border bg-card-muted/30 px-4 pt-3 flex flex-shrink-0 gap-4">
        <button
          onClick={() => setActiveTab('weekly')}
          className={`pb-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all ${
            activeTab === 'weekly' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          Weekly Schedule
        </button>
        <button
          onClick={() => setActiveTab('exceptions')}
          className={`pb-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all ${
            activeTab === 'exceptions' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          Exceptions / Holidays
        </button>
        <button
          onClick={() => setActiveTab('blocked')}
          className={`pb-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all ${
            activeTab === 'blocked' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          Blocked Blackouts
        </button>
      </div>

      {/* Notifications banner */}
      {(successMsg || errorMsg) && (
        <div className="px-4 md:px-6 pt-4 flex-shrink-0">
          {successMsg && (
            <div className="p-3.5 bg-green-500/10 border border-green-500/20 text-green-700 dark:text-green-500 rounded-xl text-xs font-semibold flex items-center gap-2">
              <CheckCircle2 size={16} /> {successMsg}
            </div>
          )}
          {errorMsg && (
            <div className="p-3.5 bg-destructive/10 border border-destructive/20 text-destructive rounded-xl text-xs font-semibold flex items-center gap-2">
              <AlertTriangle size={16} className="text-destructive" /> {errorMsg}
            </div>
          )}
        </div>
      )}

      {/* Content wrapper */}
      <div className="flex-1 overflow-y-auto p-4 md:p-6">
        {isLoading && localSettings.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-64 text-muted-foreground gap-2">
            <Loader2 className="animate-spin text-primary" size={32} />
            <span className="text-sm">Loading availability settings…</span>
          </div>
        ) : (
          <div className="max-w-4xl mx-auto space-y-6">
            
            {/* ── Weekly Working Schedule Form ── */}
            {activeTab === 'weekly' && (
              <form onSubmit={handleWeeklySubmit} className="space-y-6">
                <div className="bg-card border border-border rounded-xl p-5 md:p-6 space-y-6 shadow-sm">
                  <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-border">
                    <h3 className="text-base font-bold text-foreground flex items-center gap-2">
                      <Clock className="text-primary" size={18} /> Default Working hours
                    </h3>

                    {/* Timezone config */}
                    <div className="flex flex-wrap items-center gap-4 text-sm">
                      <div className="flex items-center gap-2">
                        <Globe size={16} className="text-muted-foreground" />
                        <select
                          value={localTimezone}
                          onChange={(e) => setLocalTimezone(e.target.value)}
                          className="bg-background border border-border rounded-lg text-xs font-semibold px-2 py-1 focus:outline-none focus:border-primary"
                        >
                          <option value="Africa/Kigali">Africa/Kigali (GMT+2)</option>
                          <option value="Europe/London">Europe/London (GMT+1)</option>
                          <option value="America/New_York">America/New_York (GMT-4)</option>
                          <option value="UTC">UTC (GMT+0)</option>
                        </select>
                      </div>

                      {/* Interval config */}
                      <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">Grid:</span>
                        <select
                          value={localInterval}
                          onChange={(e) => setLocalInterval(Number(e.target.value))}
                          className="bg-background border border-border rounded-lg text-xs font-semibold px-2 py-1 focus:outline-none focus:border-primary"
                        >
                          <option value="15">15 min</option>
                          <option value="30">30 min</option>
                          <option value="60">60 min</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  {/* Day picker list */}
                  <div className="space-y-4">
                    {DAYS_OF_WEEK.map((dayName, idx) => {
                      const dayConfig = localSettings.find(s => s.day_of_week === idx) || {
                        day_of_week: idx,
                        start_time: '09:00',
                        end_time: '17:00',
                        is_active: false
                      }

                      const toggleActive = () => {
                        const next = localSettings.map(s => {
                          if (s.day_of_week === idx) {
                            return { ...s, is_active: !s.is_active }
                          }
                          return s
                        })
                        setLocalSettings(next)
                      }

                      const changeTimes = (key: 'start_time' | 'end_time', val: string) => {
                        const next = localSettings.map(s => {
                          if (s.day_of_week === idx) {
                            return { ...s, [key]: val }
                          }
                          return s
                        })
                        setLocalSettings(next)
                      }

                      return (
                        <div key={idx} className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-3 bg-secondary/25 border border-border/40 rounded-xl">
                          <div className="flex items-center gap-3">
                            <input
                              type="checkbox"
                              checked={dayConfig.is_active}
                              onChange={toggleActive}
                              className="w-4.5 h-4.5 text-primary bg-background border-border rounded focus:ring-primary"
                            />
                            <span className="text-sm font-bold text-foreground w-28">{dayName}</span>
                          </div>

                          {dayConfig.is_active ? (
                            <div className="flex items-center gap-2">
                              <select
                                value={dayConfig.start_time}
                                onChange={(e) => changeTimes('start_time', e.target.value)}
                                className="bg-background border border-border rounded-lg text-xs font-semibold px-3 py-1.5 focus:outline-none focus:border-primary text-foreground"
                              >
                                {TIME_OPTIONS.map(opt => <option key={opt} value={opt}>{opt}</option>)}
                              </select>
                              <span className="text-xs text-muted-foreground">to</span>
                              <select
                                value={dayConfig.end_time}
                                onChange={(e) => changeTimes('end_time', e.target.value)}
                                className="bg-background border border-border rounded-lg text-xs font-semibold px-3 py-1.5 focus:outline-none focus:border-primary text-foreground"
                              >
                                {TIME_OPTIONS.map(opt => <option key={opt} value={opt}>{opt}</option>)}
                              </select>
                            </div>
                          ) : (
                            <span className="text-xs text-muted-foreground italic sm:mr-10">Closed / Unavailable</span>
                          )}
                        </div>
                      )
                    })}
                  </div>
                </div>

                <div className="flex justify-end">
                  <button
                    type="submit"
                    disabled={updateSettingsMutation.isPending}
                    className="px-6 py-3 bg-primary hover:bg-accent text-primary-foreground font-bold text-sm rounded-xl transition-all shadow-md flex items-center gap-2"
                  >
                    {updateSettingsMutation.isPending && <Loader2 size={16} className="animate-spin" />}
                    Save Settings
                  </button>
                </div>
              </form>
            )}

            {/* ── Availability Exceptions / Holidays ── */}
            {activeTab === 'exceptions' && (
              <div className="space-y-6 animate-in fade-in-30">
                <div className="flex justify-between items-center">
                  <h3 className="text-base font-bold text-foreground">Date Exceptions & Overrides</h3>
                  <button
                    onClick={() => setIsExceptionOpen(true)}
                    className="px-4 py-2 bg-primary text-primary-foreground font-semibold text-xs rounded-lg hover:bg-accent transition-all flex items-center gap-1.5 shadow-sm"
                  >
                    <Plus size={14} /> Add Exception
                  </button>
                </div>

                <div className="bg-card border border-border rounded-xl overflow-hidden shadow-sm">
                  {exceptionsData?.exceptions.length === 0 ? (
                    <div className="text-center py-12 text-muted-foreground text-sm space-y-1">
                      <p>No date exceptions configured.</p>
                      <p className="text-xs text-muted-foreground/60">Standard weekly default schedules apply on all dates.</p>
                    </div>
                  ) : (
                    <div className="divide-y divide-border">
                      {exceptionsData?.exceptions.map((ex) => (
                        <div key={ex.uuid} className="p-4 flex items-center justify-between hover:bg-secondary/15 transition-colors">
                          <div className="space-y-1">
                            <p className="text-sm font-bold text-foreground">
                              {new Date(ex.date).toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' })}
                            </p>
                            <p className="text-xs">
                              {ex.is_closed ? (
                                <span className="text-red-500 font-bold uppercase tracking-wider">Closed (All Day)</span>
                              ) : (
                                <span className="text-muted-foreground">
                                  Custom hours: <strong className="text-foreground">{ex.start_time} - {ex.end_time}</strong>
                                </span>
                              )}
                            </p>
                          </div>
                          <button
                            onClick={() => handleDeleteException(ex.uuid)}
                            className="p-2 hover:bg-red-50 hover:text-red-600 rounded-lg text-muted-foreground transition-all"
                            title="Delete exception"
                          >
                            <Trash2 size={16} />
                          </button>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* ── Blocked Blackouts ── */}
            {activeTab === 'blocked' && (
              <div className="space-y-6 animate-in fade-in-30">
                <div className="flex justify-between items-center">
                  <h3 className="text-base font-bold text-foreground">Blackout Ranges & Busy Times</h3>
                  <button
                    onClick={() => setIsBlockedOpen(true)}
                    className="px-4 py-2 bg-primary text-primary-foreground font-semibold text-xs rounded-lg hover:bg-accent transition-all flex items-center gap-1.5 shadow-sm"
                  >
                    <Plus size={14} /> Block Time
                  </button>
                </div>

                <div className="bg-card border border-border rounded-xl overflow-hidden shadow-sm">
                  {blockedData?.blocked_slots.length === 0 ? (
                    <div className="text-center py-12 text-muted-foreground text-sm space-y-1">
                      <p>No blackout periods blocked.</p>
                      <p className="text-xs text-muted-foreground/60">Add manual blackouts to block out vacations, travel, or private events.</p>
                    </div>
                  ) : (
                    <div className="divide-y divide-border">
                      {blockedData?.blocked_slots.map((bl) => (
                        <div key={bl.uuid} className="p-4 flex items-center justify-between hover:bg-secondary/15 transition-colors">
                          <div className="space-y-1">
                            <p className="text-sm font-bold text-foreground">
                              {bl.reason || 'Blocked Out / Blackout Period'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                              Starts: <strong className="text-foreground">{new Date(bl.starts_at).toLocaleString()}</strong><br />
                              Ends: <strong className="text-foreground">{new Date(bl.ends_at).toLocaleString()}</strong>
                            </p>
                            <span className="inline-block text-[9px] bg-secondary text-foreground px-1.5 py-0.5 rounded font-bold uppercase tracking-wider mt-1">
                              Source: {bl.source}
                            </span>
                          </div>
                          <button
                            onClick={() => handleDeleteBlocked(bl.uuid)}
                            className="p-2 hover:bg-red-50 hover:text-red-600 rounded-lg text-muted-foreground transition-all"
                            title="Unblock slot"
                          >
                            <Trash2 size={16} />
                          </button>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}

          </div>
        )}
      </div>

      {/* Exception Modal Form */}
      {isExceptionOpen && (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-2xl w-full max-w-md shadow-2xl p-6 relative">
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-lg font-bold text-foreground flex items-center gap-2">
                <CalendarDays className="text-primary" size={18} /> Add Date Exception
              </h3>
              <button
                onClick={() => setIsExceptionOpen(false)}
                className="p-1 hover:bg-secondary rounded text-muted-foreground hover:text-foreground"
              >
                <X size={20} />
              </button>
            </div>

            <form onSubmit={handleExceptionSubmit} className="space-y-4">
              <div>
                <label className={labelCls}>Select Date *</label>
                <input
                  type="date"
                  required
                  value={exceptionForm.date}
                  onChange={e => setExceptionForm({ ...exceptionForm, date: e.target.value })}
                  className={inputCls}
                />
              </div>

              <div className="flex items-center gap-2 py-2">
                <input
                  type="checkbox"
                  id="is_closed"
                  checked={exceptionForm.is_closed}
                  onChange={e => setExceptionForm({ ...exceptionForm, is_closed: e.target.checked })}
                  className="w-4 h-4 text-primary bg-background border-border rounded focus:ring-primary"
                />
                <label htmlFor="is_closed" className="text-xs font-bold uppercase tracking-wider text-foreground cursor-pointer">
                  Fully Closed on this Date
                </label>
              </div>

              {!exceptionForm.is_closed && (
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className={labelCls}>Start time *</label>
                    <select
                      value={exceptionForm.start_time}
                      onChange={e => setExceptionForm({ ...exceptionForm, start_time: e.target.value })}
                      className={inputCls}
                    >
                      {TIME_OPTIONS.map(opt => <option key={opt} value={opt}>{opt}</option>)}
                    </select>
                  </div>
                  <div>
                    <label className={labelCls}>End time *</label>
                    <select
                      value={exceptionForm.end_time}
                      onChange={e => setExceptionForm({ ...exceptionForm, end_time: e.target.value })}
                      className={inputCls}
                    >
                      {TIME_OPTIONS.map(opt => <option key={opt} value={opt}>{opt}</option>)}
                    </select>
                  </div>
                </div>
              )}

              <div className="flex gap-3 border-t border-border pt-4 mt-6">
                <button
                  type="button"
                  onClick={() => setIsExceptionOpen(false)}
                  className="flex-1 py-2.5 bg-secondary text-foreground rounded-lg hover:bg-muted font-semibold text-xs"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createExceptionMutation.isPending}
                  className="flex-1 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm"
                >
                  {createExceptionMutation.isPending && <Loader2 size={12} className="animate-spin" />}
                  Save Exception
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Blocked Slot Modal Form */}
      {isBlockedOpen && (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-2xl w-full max-w-md shadow-2xl p-6 relative">
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-lg font-bold text-foreground flex items-center gap-2">
                <AlertCircle className="text-primary" size={18} /> Block Calendar Range
              </h3>
              <button
                onClick={() => setIsBlockedOpen(false)}
                className="p-1 hover:bg-secondary rounded text-muted-foreground hover:text-foreground"
              >
                <X size={20} />
              </button>
            </div>

            <form onSubmit={handleBlockedSubmit} className="space-y-4">
              <div>
                <label className={labelCls}>Start time *</label>
                <input
                  type="datetime-local"
                  required
                  value={blockedForm.starts_at}
                  onChange={e => setBlockedForm({ ...blockedForm, starts_at: e.target.value })}
                  className={inputCls}
                />
              </div>

              <div>
                <label className={labelCls}>End time *</label>
                <input
                  type="datetime-local"
                  required
                  value={blockedForm.ends_at}
                  onChange={e => setBlockedForm({ ...blockedForm, ends_at: e.target.value })}
                  className={inputCls}
                />
              </div>

              <div>
                <label className={labelCls}>Reason / Label</label>
                <input
                  type="text"
                  value={blockedForm.reason}
                  onChange={e => setBlockedForm({ ...blockedForm, reason: e.target.value })}
                  placeholder="e.g. Doctor's appointment, Vacation"
                  className={inputCls}
                />
              </div>

              <div className="flex gap-3 border-t border-border pt-4 mt-6">
                <button
                  type="button"
                  onClick={() => setIsBlockedOpen(false)}
                  className="flex-1 py-2.5 bg-secondary text-foreground rounded-lg hover:bg-muted font-semibold text-xs"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createBlockedMutation.isPending}
                  className="flex-1 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm"
                >
                  {createBlockedMutation.isPending && <Loader2 size={12} className="animate-spin" />}
                  Block Range
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </main>
  )
}
