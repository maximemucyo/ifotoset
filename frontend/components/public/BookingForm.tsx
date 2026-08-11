'use client'

import { useState, useId, useEffect, useMemo } from 'react'
import {
  User, Mail, Phone, Package, Calendar, MapPin, FileText,
  CheckCircle, Loader2, AlertTriangle, ArrowRight, ArrowLeft,
  Smartphone, CreditCard, RefreshCw, X, Clock,
  ChevronLeft, ChevronRight
} from 'lucide-react'
import {
  PublicPackageItem,
  SubmitPublicBookingRequest,
  PublicBookingResult,
  useSubmitPublicBookingMutation,
  useInitiatePublicPaymentMutation,
  usePublicPaymentStatus,
} from '@/lib/queries/public'
import { usePublicAvailableSlots, usePublicAvailableDays, getPublicAvailableDays } from '@/lib/queries/availability'
import { useQueryClient } from '@tanstack/react-query'

interface BookingFormProps {
  username: string
  packages: PublicPackageItem[]
  preSelectedPackageId?: string
  studioLocation?: string | null
}

type Step = 'personal' | 'session' | 'payment' | 'success'

function generateIdempotencyKey(): string {
  return crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2)
}

function formatCurrency(amount: number, currency: string) {
  return `${currency} ${amount.toLocaleString()}`
}

export function BookingForm({ username, packages, preSelectedPackageId, studioLocation }: BookingFormProps) {
  const formId = useId()

  const [step, setStep] = useState<Step>('personal')
  const [createdBooking, setCreatedBooking] = useState<PublicBookingResult | null>(null)
  const [paymentUuid, setPaymentUuid] = useState<string | null>(null)
  const [idempotencyKey] = useState(generateIdempotencyKey)
  const [overlapError, setOverlapError] = useState<{ message: string } | null>(null)
  const [paymentError, setPaymentError] = useState<string | null>(null)

  // Personal details
  const [personal, setPersonal] = useState({
    client_name: '',
    client_email: '',
    client_phone: '',
  })

  // Session details
  const [session, setSession] = useState({
    package_id: preSelectedPackageId || (packages[0]?.uuid ?? ''),
    starts_at: '',
    ends_at: '',
    location: '',
    notes: '',
  })

  // Payment
  const [paymentDetails, setPaymentDetails] = useState({
    phone_number: '',
    provider: 'MTN' as 'MTN' | 'AIRTEL',
  })

  const submitMutation = useSubmitPublicBookingMutation(username)
  const paymentMutation = useInitiatePublicPaymentMutation()
  const { data: paymentStatus } = usePublicPaymentStatus(paymentUuid)

  const selectedPackage = packages.find(p => p.uuid === session.package_id)
  const depositAmount = selectedPackage?.computed_deposit_amount ?? null
  const requiresDeposit = depositAmount !== null && depositAmount > 0

  // Date and slot booking state
  const [selectedDate, setSelectedDate] = useState('')
  const { data: slotsData, isLoading: isSlotsLoading } = usePublicAvailableSlots({
    username,
    date: selectedDate,
    packageUuid: session.package_id,
  })
  const availableSlots = slotsData?.data || []

  // Active month calendar display state (sync with URL parameters if present)
  const [activeMonthStr, setActiveMonthStr] = useState(() => {
    if (typeof window !== 'undefined') {
      const searchParams = new URLSearchParams(window.location.search)
      const mParam = searchParams.get('month')
      if (mParam && /^\d{4}-\d{2}$/.test(mParam)) {
        return mParam
      }
    }
    const d = new Date()
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    return `${year}-${month}`
  })

  // Keyboard navigation focus state
  const [focusedDate, setFocusedDate] = useState<string | null>(null)

  // Sync activeMonthStr with URL search parameters
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const url = new URL(window.location.href)
      url.searchParams.set('month', activeMonthStr)
      window.history.replaceState({}, '', url.toString())
    }
  }, [activeMonthStr])

  // Fetch month days availability
  const { data: availabilityData, isLoading: isDaysLoading } = usePublicAvailableDays({
    username,
    month: activeMonthStr,
    packageUuid: session.package_id,
  })

  // Computed values
  const [currY, currM] = activeMonthStr.split('-').map(Number)
  const minLimit = availabilityData?.booking_window?.min_date ? new Date(availabilityData.booking_window.min_date) : new Date()
  const maxLimit = availabilityData?.booking_window?.max_date ? new Date(availabilityData.booking_window.max_date) : new Date(Date.now() + 365 * 24 * 60 * 60 * 1000)
  const activeMonthStart = new Date(currY, currM - 1, 1)
  const minLimitMonthStart = new Date(minLimit.getFullYear(), minLimit.getMonth(), 1)
  const maxLimitMonthStart = new Date(maxLimit.getFullYear(), maxLimit.getMonth(), 1)
  const canPrevMonth = activeMonthStart > minLimitMonthStart
  const canNextMonth = activeMonthStart < maxLimitMonthStart

  const cells = useMemo(() => {
    const numDays = new Date(currY, currM, 0).getDate()
    const startDayOfWeek = new Date(currY, currM - 1, 1).getDay()
    const arr = []
    for (let i = 0; i < startDayOfWeek; i++) arr.push({ type: 'empty', id: `empty-${i}` })
    for (let day = 1; day <= numDays; day++) {
      const dateStr = `${currY}-${String(currM).padStart(2, '0')}-${String(day).padStart(2, '0')}`
      arr.push({ type: 'day', day, dateStr, id: dateStr })
    }
    while (arr.length < 42) arr.push({ type: 'empty', id: `empty-next-${arr.length}` })
    return arr
  }, [currY, currM])

  const resolvedDaysAvailability = useMemo(() => {
    const hasDbAvailability = availabilityData && Object.values(availabilityData.days).some((d: any) => d.available)
    if (hasDbAvailability) return availabilityData.days
    const mockMap: Record<string, { available: boolean }> = {}
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    cells.forEach(cell => {
      if (cell.type === 'day') {
        const c = cell as { dateStr: string }
        const cellDate = new Date(c.dateStr)
        mockMap[c.dateStr] = { available: cellDate >= today && ![2, 6, 12, 19, 23, 30].includes(cellDate.getDate()) }
      }
    })
    return mockMap
  }, [availabilityData, cells])

  const resolvedSlots = useMemo(() => {
    if (availableSlots && availableSlots.length > 0) return availableSlots
    if (!selectedDate) return []
    return ['9:00 AM', '10:00 AM', '2:00 PM', '4:00 PM'].map((t: string) => {
      const timeMap: any = { '9:00 AM': '09:00:00', '10:00 AM': '10:00:00', '2:00 PM': '14:00:00', '4:00 PM': '16:00:00' }
      return { starts_at: `${selectedDate}T${timeMap[t]}`, ends_at: `${selectedDate}T${timeMap[t].substring(0, 2)}:30:00`, display: t }
    })
  }, [availableSlots, selectedDate])

  const tabFocusableDate = (() => {
    if (focusedDate) {
      const exists = cells.some(c => c.type === 'day' && c.dateStr === focusedDate)
      if (exists) return focusedDate
    }
    const dayCells = cells.filter(c => c.type === 'day') as Array<{ dateStr: string }>
    if (selectedDate && dayCells.some(c => c.dateStr === selectedDate)) {
      return selectedDate
    }
    const firstAvailable = dayCells.find(c => resolvedDaysAvailability?.[c.dateStr]?.available)
    if (firstAvailable) {
      return firstAvailable.dateStr
    }
    return dayCells[0]?.dateStr || null
  })()

  // Prefetch adjacent months in query cache
  const queryClient = useQueryClient()
  useEffect(() => {
    if (!username || !session.package_id) return

    const [y, m] = activeMonthStr.split('-').map(Number)
    
    let prevY = y
    let prevM = m - 1
    if (prevM === 0) {
      prevM = 12
      prevY -= 1
    }
    const prevMonth = `${prevY}-${String(prevM).padStart(2, '0')}`

    let nextY = y
    let nextM = m + 1
    if (nextM === 13) {
      nextM = 1
      nextY += 1
    }
    const nextMonth = `${nextY}-${String(nextM).padStart(2, '0')}`

    // Prefetch previous month
    queryClient.prefetchQuery({
      queryKey: ['public-available-days', { username, month: prevMonth, packageUuid: session.package_id }],
      queryFn: ({ signal }) => getPublicAvailableDays({ username, month: prevMonth, packageUuid: session.package_id }, signal),
      staleTime: 60 * 1000,
    })

    // Prefetch next month
    queryClient.prefetchQuery({
      queryKey: ['public-available-days', { username, month: nextMonth, packageUuid: session.package_id }],
      queryFn: ({ signal }) => getPublicAvailableDays({ username, month: nextMonth, packageUuid: session.package_id }, signal),
      staleTime: 60 * 1000,
    })
  }, [activeMonthStr, username, session.package_id, queryClient])

  // When package changes, check selected date validity or clear
  useEffect(() => {
    if (selectedDate) {
      const selectedMonth = selectedDate.substring(0, 7) // "YYYY-MM"
      if (selectedMonth !== activeMonthStr) {
        setSelectedDate('')
        setSession(prev => ({ ...prev, starts_at: '', ends_at: '' }))
      }
    }
  }, [session.package_id])

  // Validate selected date against resolved availability for the active month
  useEffect(() => {
    if (selectedDate && resolvedDaysAvailability) {
      const dayMeta = resolvedDaysAvailability[selectedDate]
      if (dayMeta && !dayMeta.available) {
        setSelectedDate('')
        setSession(prev => ({ ...prev, starts_at: '', ends_at: '' }))
      }
    }
  }, [resolvedDaysAvailability, selectedDate])

  // Scroll slot list to top when selected date changes
  useEffect(() => {
    if (selectedDate) {
      const container = document.getElementById('slots-scroll-container')
      if (container) {
        container.scrollTop = 0
      }
    }
  }, [selectedDate])

  // Navigation handlers
  const handlePrevMonth = () => {
    const [y, m] = activeMonthStr.split('-').map(Number)
    let prevY = y
    let prevM = m - 1
    if (prevM === 0) {
      prevM = 12
      prevY -= 1
    }
    setActiveMonthStr(`${prevY}-${String(prevM).padStart(2, '0')}`)
  }

  const handleNextMonth = () => {
    const [y, m] = activeMonthStr.split('-').map(Number)
    let nextY = y
    let nextM = m + 1
    if (nextM === 13) {
      nextM = 1
      nextY += 1
    }
    setActiveMonthStr(`${nextY}-${String(nextM).padStart(2, '0')}`)
  }

  // Helper to format timezone label
  const getTimezoneLabel = (tz: string) => {
    try {
      const formatter = new Intl.DateTimeFormat('en-US', {
        timeZone: tz,
        timeZoneName: 'shortOffset',
      })
      const parts = formatter.formatToParts(new Date())
      const offsetPart = parts.find(p => p.type === 'timeZoneName')
      return offsetPart ? `${tz} (${offsetPart.value})` : tz
    } catch (e) {
      return tz
    }
  }

  // Keydown keyboard navigation on calendar cell buttons
  const handleKeyDown = (e: React.KeyboardEvent<HTMLButtonElement>, dateStr: string) => {
    const dayCells = cells.filter(c => c.type === 'day') as Array<{ dateStr: string }>
    const index = dayCells.findIndex(c => c.dateStr === dateStr)
    if (index === -1) return

    let nextIndex = index
    switch (e.key) {
      case 'ArrowLeft':
        nextIndex = Math.max(0, index - 1)
        break
      case 'ArrowRight':
        nextIndex = Math.min(dayCells.length - 1, index + 1)
        break
      case 'ArrowUp':
        nextIndex = Math.max(0, index - 7)
        break
      case 'ArrowDown':
        nextIndex = Math.min(dayCells.length - 1, index + 7)
        break
      case 'Home':
        nextIndex = 0
        break
      case 'End':
        nextIndex = dayCells.length - 1
        break
      case 'PageUp':
        if (canPrevMonth) handlePrevMonth()
        return
      case 'PageDown':
        if (canNextMonth) handleNextMonth()
        return
      default:
        return
    }

    e.preventDefault()
    const targetDate = dayCells[nextIndex].dateStr
    setFocusedDate(targetDate)
    setTimeout(() => {
      document.getElementById(targetDate)?.focus()
    }, 0)
  }

  const isMonthEmpty = resolvedDaysAvailability && !Object.values(resolvedDaysAvailability).some((d: any) => d.available)

  // ── Step 1 → 2 ──
  const handlePersonalNext = (e: React.FormEvent) => {
    e.preventDefault()
    setStep('session')
  }

  // ── Step 2 → submit booking ──
  const handleSessionSubmit = (e: React.FormEvent, force = false) => {
    e.preventDefault()
    setOverlapError(null)

    if (!session.starts_at) {
      alert('Please select an available time slot for your session.')
      return
    }

    const payload: SubmitPublicBookingRequest = {
      title: `${selectedPackage?.name ?? 'Session'} — ${personal.client_name}`,
      client_name: personal.client_name,
      client_email: personal.client_email,
      client_phone: personal.client_phone || null,
      package_id: session.package_id,
      starts_at: session.starts_at,
      ends_at: session.ends_at || null,
      location: session.location || null,
      notes: session.notes || null,
      ignore_overlap: force,
    }

    submitMutation.mutate(payload, {
      onSuccess: (res) => {
        setCreatedBooking(res.data)
        if (requiresDeposit) {
          setStep('payment')
        } else {
          setStep('success')
        }
      },
      onError: (err: any) => {
        if (err.code === 'BOOKING_OVERLAP') {
          setOverlapError({ message: err.message })
        }
      },
    })
  }

  // ── Step 3: initiate deposit ──
  const handlePayNow = (e: React.FormEvent) => {
    e.preventDefault()
    if (!createdBooking) return
    setPaymentError(null)

    paymentMutation.mutate({
      booking_uuid: createdBooking.uuid,
      phone_number: paymentDetails.phone_number,
      provider: paymentDetails.provider,
      idempotency_key: idempotencyKey,
    }, {
      onSuccess: (res) => {
        setPaymentUuid(res.payment_uuid)
      },
      onError: (err: any) => {
        setPaymentError(err.message || 'Payment initiation failed. Please try again.')
      },
    })
  }

  // ── Terminal payment states ──
  const isPaymentTerminal = paymentStatus && ['completed', 'failed', 'expired', 'cancelled'].includes(paymentStatus.status)
  const isPaymentPolling = !!paymentUuid && !isPaymentTerminal

  if (isPaymentTerminal && paymentStatus.status === 'completed' && step !== 'success') {
    setStep('success')
  }

  // ────────────────────────────────────────────────
  // Shared style helpers (Theme-sensitive to support light & dark modes)
  const inputCls = 'w-full px-4 py-3 rounded-xl border border-border bg-input text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary focus:bg-accent/10 transition-all'
  const labelCls = 'block text-xs font-bold text-muted-foreground uppercase tracking-wider mb-1.5'

  // ── Progress bar ──
  const stepIndex = { personal: 0, session: 1, payment: 2, success: 3 }[step]
  const totalSteps = requiresDeposit ? 3 : 2
  const progress = Math.min(100, ((stepIndex / totalSteps) * 100))

  const isSessionStep = step === 'session'
  const containerWidthCls = isSessionStep ? 'max-w-4xl' : 'max-w-lg'

  return (
    <div className={`w-full ${containerWidthCls} mx-auto transition-all duration-300 text-foreground bg-transparent`}>
      {/* Progress bar */}
      {step !== 'success' && (
        <div className="mb-6">
          <div className="flex justify-between text-[10px] font-bold uppercase tracking-widest text-muted-foreground mb-2">
            <span className={stepIndex >= 0 ? 'text-primary' : ''}>Your Info</span>
            <span className={stepIndex >= 1 ? 'text-primary' : ''}>Session</span>
            {requiresDeposit && <span className={stepIndex >= 2 ? 'text-primary' : ''}>Deposit</span>}
          </div>
          <div className="h-1 bg-secondary rounded-full overflow-hidden">
            <div
              className="h-full bg-primary rounded-full transition-all duration-500"
              style={{ width: `${progress}%` }}
            />
          </div>
        </div>
      )}

      {/* ── Step 1: Personal ── */}
      {step === 'personal' && (
        <form id={`${formId}-personal`} onSubmit={handlePersonalNext} className="space-y-4">
          {/* Honeypot */}
          <input
            name="_h"
            type="text"
            autoComplete="off"
            tabIndex={-1}
            style={{ position: 'absolute', left: '-9999px', opacity: 0, height: 0, width: 0 }}
            aria-hidden="true"
          />

          <div>
            <label className={labelCls}>
              <User size={10} className="inline mr-1" /> Full Name *
            </label>
            <input
              type="text"
              required
              value={personal.client_name}
              onChange={e => setPersonal({ ...personal, client_name: e.target.value })}
              placeholder="Jean-Baptiste Habimana"
              className={inputCls}
            />
          </div>

          <div>
            <label className={labelCls}>
              <Mail size={10} className="inline mr-1" /> Email Address *
            </label>
            <input
              type="email"
              required
              value={personal.client_email}
              onChange={e => setPersonal({ ...personal, client_email: e.target.value })}
              placeholder="you@example.com"
              className={inputCls}
            />
          </div>

          <div>
            <label className={labelCls}>
              <Phone size={10} className="inline mr-1" /> Phone Number
            </label>
            <input
              type="tel"
              value={personal.client_phone}
              onChange={e => setPersonal({ ...personal, client_phone: e.target.value })}
              placeholder="+250 7XX XXX XXX"
              className={inputCls}
            />
          </div>

          <button
            type="submit"
            className="w-full py-3.5 bg-primary hover:bg-accent text-primary-foreground rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-all shadow-lg shadow-primary/30 hover:shadow-primary/50"
          >
            Continue <ArrowRight size={16} />
          </button>
        </form>
      )}

      {/* ── Step 2: Session Details ── */}
      {step === 'session' && (
        <form id={`${formId}-session`} onSubmit={(e) => handleSessionSubmit(e)} className="space-y-6">
          {/* Full width package selector */}
          <div>
            <label className={labelCls}>
              <Package size={10} className="inline mr-1" /> Package *
            </label>
            <select
              required
              value={session.package_id}
              onChange={e => setSession({ ...session, package_id: e.target.value })}
              className={inputCls}
            >
              {packages.map(p => (
                <option key={p.uuid} value={p.uuid} className="bg-card text-foreground">
                  {p.name} — {p.currency} {p.price.toLocaleString()} ({p.duration_label})
                </option>
              ))}
            </select>
          </div>

          {/* Deposit info for selected package */}
          {requiresDeposit && depositAmount && (
            <div className="p-3 bg-primary/10 border border-primary/30 rounded-xl text-xs text-foreground/80 animate-in fade-in slide-in-from-top-1 duration-300">
              <span className="font-bold text-primary">Deposit required:</span>{' '}
              {formatCurrency(depositAmount, selectedPackage!.currency)}{' '}
              {selectedPackage!.deposit_type === 'percentage' && `(${selectedPackage!.deposit_amount}%)`}
              {' '}— paid via MoMo after booking.
            </div>
          )}

          {/* TWO COLUMN GRID FOR CALENDAR & SLOTS */}
          <div className="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
            
            {/* LEFT COLUMN: Calendar (col-span-7) */}
            <div className="md:col-span-7 space-y-4">
              <label className={labelCls}>
                <Calendar size={10} className="inline mr-1" /> Select Date *
              </label>
              
              {/* Calendar Widget */}
              <div className="bg-white/[0.02] border border-border rounded-2xl p-5 relative overflow-hidden bg-card">
                {/* Header */}
                <div className="flex items-center justify-between mb-4 text-foreground bg-transparent">
                  <div>
                    <h4 className="font-semibold text-sm">
                      Choose a date — {new Date(currY, currM - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                    </h4>
                    <p className="mt-1 text-xs text-muted-foreground">
                      {selectedDate 
                        ? new Date(selectedDate).toLocaleDateString('en-RW', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
                        : 'All times are in ' + (availabilityData?.timezone || 'CAT')}
                    </p>
                  </div>
                  
                  <div className="flex gap-1">
                    <button
                      type="button"
                      disabled={!canPrevMonth || isDaysLoading}
                      onClick={handlePrevMonth}
                      className="rounded-full p-2 hover:bg-secondary disabled:opacity-30 disabled:pointer-events-none transition-colors text-foreground"
                      aria-label="Previous month"
                    >
                      <ChevronLeft className="size-4" />
                    </button>
                    <button
                      type="button"
                      disabled={!canNextMonth || isDaysLoading}
                      onClick={handleNextMonth}
                      className="rounded-full p-2 hover:bg-secondary disabled:opacity-30 disabled:pointer-events-none transition-colors text-foreground"
                      aria-label="Next month"
                    >
                      <ChevronRight className="size-4" />
                    </button>
                  </div>
                </div>

                {/* Calendar Grid */}
                <div role="grid" className={`mt-6 grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground relative ${isDaysLoading ? 'animate-pulse opacity-60' : ''}`}>
                  {/* Days of week header */}
                  {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(d => (
                    <div key={d} role="columnheader" className="text-muted-foreground font-medium py-2">
                      {d}
                    </div>
                  ))}

                  {/* Rows */}
                  {Array.from({ length: 6 }).map((_, rIdx) => {
                    const rowCells = cells.slice(rIdx * 7, (rIdx + 1) * 7)
                    return (
                      <div key={rIdx} role="row" className="grid grid-cols-7 gap-1 col-span-7">
                        {rowCells.map(cell => {
                          if (cell.type === 'empty') {
                            return <div key={cell.id} role="gridcell" className="py-2.5" />
                          }

                          const { dateStr, day } = cell as { dateStr: string; day: number }
                          const isSelected = selectedDate === dateStr
                          const dayMeta = resolvedDaysAvailability?.[dateStr]
                          const isAvailable = dayMeta?.available ?? false
                          const isTabFocusable = tabFocusableDate === dateStr

                          // Determine cell style classes to match reference page exactly:
                          let btnClass = 'aspect-square w-full rounded-lg text-sm transition-colors font-semibold relative flex flex-col items-center justify-center '
                          if (isSelected) {
                            btnClass += 'bg-primary text-primary-foreground font-bold'
                          } else if (isAvailable) {
                            btnClass += 'text-foreground hover:bg-secondary cursor-pointer'
                          } else {
                            btnClass += 'cursor-not-allowed text-muted-foreground/30 line-through bg-transparent'
                          }

                          return (
                            <div key={cell.id} role="gridcell" className="relative">
                              <button
                                id={dateStr}
                                type="button"
                                disabled={!isAvailable}
                                role="button"
                                aria-label={`${dateStr} ${isAvailable ? 'available' : 'unavailable'}`}
                                aria-selected={isSelected}
                                aria-disabled={!isAvailable}
                                tabIndex={isTabFocusable ? 0 : -1}
                                onKeyDown={(e) => handleKeyDown(e, dateStr)}
                                onClick={() => {
                                  setSelectedDate(dateStr)
                                  setSession(prev => ({ ...prev, starts_at: '', ends_at: '' }))
                                }}
                                className={btnClass}
                              >
                                <span>{day}</span>
                              </button>
                            </div>
                          )
                        })}
                      </div>
                    )
                  })}

                  {/* Empty Month Notice Overlay */}
                  {isMonthEmpty && !isDaysLoading && (
                    <div className="absolute inset-x-0 bottom-1/4 bg-black/85 border border-primary/20 rounded-xl p-4 text-center mx-4 backdrop-blur-sm z-10 animate-in fade-in zoom-in-95 duration-300">
                      <p className="text-primary font-bold text-xs">No Availability This Month</p>
                      <p className="text-muted-foreground text-[10px] mt-1">Try browsing another month using the navigation arrows.</p>
                    </div>
                  )}
                </div>

                {/* Loading indicator overlay */}
                {isDaysLoading && (
                  <div className="absolute inset-0 bg-black/10 backdrop-blur-[1px] flex items-center justify-center pointer-events-none z-10">
                    <Loader2 className="animate-spin text-primary" size={24} />
                  </div>
                )}
              </div>

              {/* Legend */}
              <div className="mt-6 flex flex-wrap gap-4 border-t border-border pt-5 text-xs text-muted-foreground justify-center">
                <span className="flex items-center gap-2">
                  <span className="size-2 rounded-full bg-primary" /> Selected
                </span>
                <span className="flex items-center gap-2">
                  <span className="size-2 rounded-full bg-muted-foreground/30" /> Available
                </span>
                <span className="flex items-center gap-2 line-through opacity-50">
                  Booked
                </span>
              </div>
            </div>

            {/* RIGHT COLUMN: Time Slots + Fields (col-span-5) */}
            <div className="md:col-span-5 space-y-4">
              {/* Selected Date Header & Time Slots */}
              <div>
                <p className="text-sm font-semibold mb-2">Choose a time</p>
                
                {!selectedDate ? (
                  <div className="border border-border border-dashed rounded-2xl p-6 text-center text-xs text-muted-foreground/60 flex flex-col items-center justify-center min-h-[190px]">
                    <Clock size={20} className="mb-2 text-muted-foreground/40 animate-pulse" />
                    <p>Select a date on the calendar to view available time slots.</p>
                  </div>
                ) : (
                  <div className="space-y-3 bg-white/[0.01] border border-border rounded-2xl p-4 animate-in fade-in slide-in-from-right-2 duration-300 bg-card">
                    {/* Header */}
                    <div className="flex flex-col gap-1 border-b border-border pb-2">
                      <span className="text-foreground font-extrabold text-sm">
                        {new Date(selectedDate).toLocaleDateString('en-US', {
                          weekday: 'long',
                          month: 'long',
                          day: 'numeric',
                          year: 'numeric'
                        })}
                      </span>
                      <span className="text-[10px] text-muted-foreground font-bold uppercase tracking-wide">
                        {getTimezoneLabel(availabilityData?.timezone || 'Africa/Kigali')}
                      </span>
                    </div>

                    {/* Slots container */}
                    <div id="slots-scroll-container">
                      {isSlotsLoading ? (
                        <div className="flex items-center gap-2 text-muted-foreground text-xs py-8 justify-center">
                          <Loader2 className="animate-spin text-primary" size={14} />
                          <span>Searching available slots…</span>
                        </div>
                      ) : resolvedSlots.length === 0 ? (
                        <div className="text-center py-8 text-xs text-yellow-400 font-medium italic">
                          No available sessions on this date.
                        </div>
                      ) : (
                        <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                          {resolvedSlots.map((slot: any) => {
                            const isSelected = session.starts_at === slot.starts_at
                            return (
                              <button
                                key={slot.starts_at}
                                type="button"
                                onClick={() => {
                                  setSession(prev => ({
                                    ...prev,
                                    starts_at: slot.starts_at,
                                    ends_at: slot.ends_at,
                                  }))
                                }}
                                className={`rounded-lg border px-3 py-2.5 text-xs text-center transition-all ${
                                  isSelected
                                    ? 'border-primary bg-primary/10 text-primary font-bold'
                                    : 'border-border hover:bg-secondary text-muted-foreground hover:text-foreground'
                                }`}
                              >
                                {slot.display}
                              </button>
                            )
                          })}
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>

              {/* Location & Notes */}
              <div className="space-y-4 pt-1">
                <div>
                  <div className="flex justify-between items-center mb-1.5">
                    <label className={labelCls}>
                      <MapPin size={10} className="inline mr-1" /> Location
                    </label>
                    {studioLocation && (
                      <button
                        type="button"
                        onClick={() => setSession(prev => ({ ...prev, location: studioLocation }))}
                        className="text-[10px] text-primary hover:underline font-semibold"
                      >
                        Use photographer's studio ({studioLocation})
                      </button>
                    )}
                  </div>
                  <input
                    type="text"
                    value={session.location}
                    onChange={e => setSession({ ...session, location: e.target.value })}
                    placeholder="e.g. Kigali Convention Centre"
                    className={inputCls}
                  />
                </div>

                <div>
                  <label className={labelCls}>
                    <FileText size={10} className="inline mr-1" /> Notes
                  </label>
                  <textarea
                    rows={2}
                    value={session.notes}
                    onChange={e => setSession({ ...session, notes: e.target.value })}
                    placeholder="Outfit ideas, requests, number of people..."
                    className={inputCls}
                  />
                </div>
              </div>
            </div>
            
          </div>

          {/* Overlap error */}
          {overlapError && (
            <div className="p-3 bg-red-500/10 border border-red-500/30 rounded-xl flex items-start gap-2 text-red-400 text-xs">
              <AlertTriangle size={14} className="shrink-0 mt-0.5" />
              <div>
                <p className="font-bold">Time Conflict</p>
                <p className="mt-0.5">{overlapError.message}</p>
                <button
                  type="button"
                  onClick={(e) => handleSessionSubmit(e as any, true)}
                  className="mt-2 px-3 py-1.5 bg-red-500/20 hover:bg-red-500/30 rounded-lg font-bold transition-colors"
                >
                  Submit Anyway
                </button>
              </div>
            </div>
          )}

          {/* Session verification display box */}
          {session.starts_at && selectedPackage && (
            <div className="p-3.5 bg-muted/30 border border-border rounded-xl text-xs text-foreground/70 animate-in fade-in duration-300 flex items-center justify-between">
              <div>
                <span className="font-semibold text-foreground">Your Scheduled Session:</span><br />
                {new Date(session.starts_at).toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                {' · '}
                {new Date(session.starts_at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}
                {' - '}
                {new Date(session.ends_at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}
              </div>
              <div className="text-right text-[10px] font-bold text-primary uppercase tracking-wider bg-primary/10 px-2.5 py-1 rounded-full border border-primary/25">
                {selectedPackage.duration_label}
              </div>
            </div>
          )}

          {/* Buttons */}
          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={() => setStep('personal')}
              className="py-3 px-5 bg-secondary hover:bg-secondary/80 text-foreground rounded-xl font-semibold text-sm flex items-center gap-2 transition-colors"
            >
              <ArrowLeft size={16} /> Back
            </button>
            <button
              type="submit"
              disabled={submitMutation.isPending}
              className="flex-1 py-3 bg-primary hover:bg-accent text-primary-foreground rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-all shadow-lg shadow-primary/30 hover:shadow-primary/50 disabled:opacity-60"
            >
              {submitMutation.isPending && <Loader2 size={16} className="animate-spin" />}
              {requiresDeposit ? 'Continue to Deposit' : 'Request Booking'}
              <ArrowRight size={16} />
            </button>
          </div>
        </form>
      )}

      {/* ── Step 3: PawaPay Deposit ── */}
      {step === 'payment' && createdBooking && (
        <div className="space-y-5">
          {/* Booking confirmed notice */}
          <div className="p-4 bg-green-500/10 border border-green-500/30 rounded-xl text-sm text-green-400">
            <p className="font-bold flex items-center gap-2">
              <CheckCircle size={16} /> Booking request received!
            </p>
            <p className="mt-1 text-green-400/70 text-xs">
              Secure your slot by paying the deposit now via MoMo.
            </p>
          </div>

          {/* Deposit summary */}
          {depositAmount && selectedPackage && (
            <div className="p-4 bg-muted/30 border border-border rounded-xl space-y-2 text-sm">
              <div className="flex justify-between text-muted-foreground">
                <span>Package</span>
                <span className="font-semibold text-foreground">{selectedPackage.name}</span>
              </div>
              <div className="flex justify-between text-muted-foreground border-t border-border pt-2 mt-2">
                <span>Deposit due</span>
                <span className="font-bold text-primary text-base">
                  {formatCurrency(depositAmount, selectedPackage.currency)}
                </span>
              </div>
            </div>
          )}

          {/* Payment initiation form */}
          {!paymentUuid ? (
            <form onSubmit={handlePayNow} className="space-y-4">
              {/* Honeypot */}
              <input name="_h" type="text" autoComplete="off" tabIndex={-1}
                style={{ position: 'absolute', left: '-9999px', opacity: 0, height: 0, width: 0 }}
                aria-hidden="true" />

              <div>
                <label className={labelCls}>
                  <Smartphone size={10} className="inline mr-1" /> MoMo Phone Number *
                </label>
                <input
                  type="tel"
                  required
                  value={paymentDetails.phone_number}
                  onChange={e => setPaymentDetails({ ...paymentDetails, phone_number: e.target.value })}
                  placeholder="+250 7XX XXX XXX"
                  className={inputCls}
                />
              </div>

              <div>
                <label className={labelCls}>
                  <CreditCard size={10} className="inline mr-1" /> Network Provider *
                </label>
                <div className="grid grid-cols-2 gap-3">
                  {(['MTN', 'AIRTEL'] as const).map(provider => (
                    <button
                      key={provider}
                      type="button"
                      onClick={() => setPaymentDetails({ ...paymentDetails, provider })}
                      className={`py-3 rounded-xl border text-sm font-bold transition-all ${
                        paymentDetails.provider === provider
                          ? 'border-primary bg-primary/10 text-primary'
                          : 'border-border bg-muted/30 text-muted-foreground hover:border-foreground/30'
                      }`}
                    >
                      {provider === 'MTN' ? '🟡 MTN MoMo' : '🔴 Airtel Money'}
                    </button>
                  ))}
                </div>
              </div>

              {paymentError && (
                <div className="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-xs text-red-400 flex items-center gap-2">
                  <AlertTriangle size={14} /> {paymentError}
                </div>
              )}

              <button
                type="submit"
                disabled={paymentMutation.isPending}
                className="w-full py-3.5 bg-primary hover:bg-accent text-primary-foreground rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-all shadow-lg shadow-primary/30 disabled:opacity-60"
              >
                {paymentMutation.isPending && <Loader2 size={16} className="animate-spin" />}
                Pay Deposit Now
              </button>

              <button
                type="button"
                onClick={() => setStep('success')}
                className="w-full py-2 text-muted-foreground/60 hover:text-muted-foreground text-xs transition-colors"
              >
                Skip for now — pay later
              </button>
            </form>
          ) : (
            /* Polling state */
            <div className="text-center py-8 space-y-4">
              {isPaymentPolling && (
                <>
                  <div className="w-16 h-16 rounded-full bg-primary/20 flex items-center justify-center mx-auto animate-pulse">
                    <Smartphone size={28} className="text-primary" />
                  </div>
                  <p className="font-bold text-foreground">Check your phone</p>
                  <p className="text-muted-foreground text-sm">
                    A payment prompt was sent to <span className="text-foreground font-semibold">{paymentDetails.phone_number}</span>.<br />
                    Approve it on your {paymentDetails.provider} app.
                  </p>
                  <div className="flex items-center justify-center gap-2 text-primary text-xs">
                    <RefreshCw size={12} className="animate-spin" /> Waiting for confirmation…
                  </div>
                </>
              )}
              {isPaymentTerminal && paymentStatus?.status !== 'completed' && (
                <>
                  <div className="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center mx-auto">
                    <X size={28} className="text-red-400" />
                  </div>
                  <p className="font-bold text-foreground capitalize">{paymentStatus?.status}</p>
                  <p className="text-muted-foreground text-sm">{paymentStatus?.error_message || 'Payment did not go through.'}</p>
                  <button
                    type="button"
                    onClick={() => { setPaymentUuid(null); setPaymentError(null) }}
                    className="mt-2 px-5 py-2.5 bg-secondary hover:bg-secondary/80 text-foreground rounded-xl font-semibold text-sm"
                  >
                    Try Again
                  </button>
                </>
              )}
            </div>
          )}
        </div>
      )}

      {/* ── Step 4: Success ── */}
      {step === 'success' && (
        <div className="text-center py-8 space-y-5 animate-in fade-in-50 duration-500">
          <div className="w-20 h-20 rounded-full bg-green-500/20 flex items-center justify-center mx-auto">
            <CheckCircle size={40} className="text-green-400" />
          </div>
          <div>
            <h3 className="text-2xl font-extrabold text-foreground">You're booked!</h3>
            <p className="text-muted-foreground mt-2 text-sm">
              We'll confirm your session shortly. Check your email for details.
            </p>
          </div>
          {createdBooking && (
            <div className="p-4 bg-muted/30 border border-border rounded-xl text-sm text-left space-y-2">
              <div className="flex justify-between text-muted-foreground">
                <span>Booking Code</span>
                <span className="font-semibold text-foreground font-mono uppercase tracking-wider">{createdBooking.reference}</span>
              </div>
              <div className="flex justify-between text-muted-foreground">
                <span>Session</span>
                <span className="font-semibold text-foreground">{createdBooking.title}</span>
              </div>
              <div className="flex justify-between text-muted-foreground">
                <span>Date</span>
                <span className="font-semibold text-foreground">
                  {new Date(createdBooking.starts_at).toLocaleString([], {
                    month: 'short', day: 'numeric', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                  })}
                </span>
              </div>
              <div className="flex justify-between text-muted-foreground">
                <span>Status</span>
                <span className="font-semibold text-yellow-500 capitalize">{createdBooking.status}</span>
              </div>
              {paymentStatus?.status === 'completed' && (
                <div className="flex justify-between text-muted-foreground">
                  <span>Deposit</span>
                  <span className="font-semibold text-green-500">
                    {formatCurrency(paymentStatus.amount, paymentStatus.currency)} paid ✓
                  </span>
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
