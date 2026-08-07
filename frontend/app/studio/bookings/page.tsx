'use client'

import { useState } from 'react'
import { Calendar, MapPin, User, DollarSign, CheckCircle, Clock, Plus, Loader2, Sparkles, X, AlertTriangle, Eye, Trash2, Copy, ExternalLink } from 'lucide-react'
import { useCurrentUser } from '@/lib/queries/auth'
import { useBookings, useCreateBookingMutation, useUpdateBookingMutation, useDeleteBookingMutation, BookingItem, BookingStatus } from '@/lib/queries/bookings'
import { useClients } from '@/lib/queries/clients'
import { useStudioPackages } from '@/lib/queries/packages'

export default function Bookings() {
  const { data: currentUser } = useCurrentUser()
  const [activeTab, setActiveTab] = useState<'upcoming' | 'past'>('upcoming')
  const [page, setPage] = useState(1)
  const [copiedLink, setCopiedLink] = useState(false)

  // Modals & Details states
  const [isAddOpen, setIsAddOpen] = useState(false)
  const [isDetailsOpen, setIsDetailsOpen] = useState(false)
  const [selectedBooking, setSelectedBooking] = useState<BookingItem | null>(null)
  
  // Overlap warn states
  const [overlapError, setOverlapError] = useState<{ message: string; conflicts: BookingItem[] } | null>(null)

  // Add Booking Form state
  const [formData, setFormData] = useState({
    title: '',
    client_uuid: '',
    package_uuid: '',
    starts_at: '',
    ends_at: '',
    location: '',
    price: '',
    currency: 'RWF',
    status: 'pending' as BookingStatus,
    notes: '',
  })

  // Queries
  const statusFilter = activeTab === 'upcoming' ? 'pending,confirmed' : 'completed,cancelled'
  const { data: bookingsData, isLoading, isError, error } = useBookings({
    status: statusFilter,
    page,
    per_page: 15,
  })

  const { data: clientsData } = useClients({ per_page: 100 })
  const { data: packagesData } = useStudioPackages({ active: true })

  // Mutations
  const createMutation = useCreateBookingMutation()
  const updateMutation = useUpdateBookingMutation()
  const deleteMutation = useDeleteBookingMutation()

  const handleOpenAddModal = () => {
    setFormData({
      title: '',
      client_uuid: '',
      package_uuid: '',
      starts_at: '',
      ends_at: '',
      location: '',
      price: '',
      currency: 'RWF',
      status: 'pending',
      notes: '',
    })
    setOverlapError(null)
    setIsAddOpen(true)
  }

  const handleCreateSubmit = (e: React.FormEvent, force = false) => {
    e.preventDefault()
    if (!formData.title || !formData.starts_at) return

    const payload = {
      title: formData.title,
      client_id: formData.client_uuid || null,
      package_id: formData.package_uuid || null,
      starts_at: new Date(formData.starts_at).toISOString(),
      ends_at: formData.ends_at ? new Date(formData.ends_at).toISOString() : null,
      location: formData.location || null,
      status: formData.status,
      price: formData.price ? Number(formData.price) : null,
      currency: formData.currency,
      notes: formData.notes || null,
      ignore_overlap: force,
    }

    createMutation.mutate(payload, {
      onSuccess: () => {
        setIsAddOpen(false)
        setOverlapError(null)
      },
      onError: (err: any) => {
        if (err.code === 'BOOKING_OVERLAP') {
          setOverlapError({
            message: err.message,
            conflicts: err.conflicts || [],
          })
        }
      }
    })
  }

  const handleStatusChange = (booking: BookingItem, newStatus: BookingStatus) => {
    updateMutation.mutate({
      uuid: booking.uuid,
      status: newStatus,
    }, {
      onSuccess: (data) => {
        if (selectedBooking?.uuid === booking.uuid) {
          setSelectedBooking(data.data)
        }
      }
    })
  }

  const handleDeleteClick = (booking: BookingItem) => {
    if (!confirm(`Are you sure you want to cancel and delete booking "${booking.title}"?`)) return
    deleteMutation.mutate(booking.uuid, {
      onSuccess: () => {
        setIsDetailsOpen(false)
        setSelectedBooking(null)
      }
    })
  }

  const getStatusColor = (status: BookingStatus) => {
    switch (status) {
      case 'confirmed': return 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-500'
      case 'pending': return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-500'
      case 'completed': return 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-500'
      case 'cancelled': return 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-500'
      default: return 'bg-gray-100 text-gray-700'
    }
  }

  const bookings = bookingsData?.data || []
  const meta = bookingsData?.meta
  const clients = clientsData?.data || []
  const packages = packagesData?.data || []

  return (
    <main className="flex-1 flex flex-col h-screen overflow-hidden bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-4 md:p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 flex-shrink-0">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-foreground tracking-tight">Bookings</h1>
          <p className="text-xs md:text-sm text-muted-foreground mt-1">Manage scheduled service sessions and client appointments</p>
        </div>
        <button
          onClick={handleOpenAddModal}
          className="px-5 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-all flex items-center justify-center gap-2 font-semibold text-sm shadow-sm"
        >
          <Plus size={18} />
          Add Booking
        </button>
      </div>

      {/* Public Booking Link Banner */}
      {currentUser?.user?.username ? (
        <div className="bg-primary/5 border-b border-border px-4 py-3 md:px-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs md:text-sm flex-shrink-0">
          <div className="flex items-center gap-2 text-foreground">
            <span className="font-bold text-primary">Your Booking Link:</span>
            <code className="bg-secondary px-2.5 py-1 rounded text-xs select-all text-foreground">
              {`${typeof window !== 'undefined' ? window.location.origin : ''}/p/${currentUser.user.username}`}
            </code>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={() => {
                const link = `${window.location.origin}/p/${currentUser.user.username}`
                navigator.clipboard.writeText(link)
                setCopiedLink(true)
                setTimeout(() => setCopiedLink(false), 2000)
              }}
              className="px-3 py-1.5 bg-secondary hover:bg-muted text-foreground rounded font-semibold text-xs flex items-center gap-1.5 transition-colors"
            >
              <Copy size={12} />
              {copiedLink ? 'Copied!' : 'Copy Link'}
            </button>
            <a
              href={`/p/${currentUser.user.username}`}
              target="_blank"
              rel="noopener noreferrer"
              className="px-3 py-1.5 bg-primary text-primary-foreground hover:bg-accent rounded font-semibold text-xs flex items-center gap-1.5 transition-colors"
            >
              <ExternalLink size={12} />
              Open Page
            </a>
          </div>
        </div>
      ) : (
        <div className="bg-yellow-500/10 border-b border-border px-4 py-3 md:px-6 text-xs md:text-sm flex-shrink-0 text-yellow-700 dark:text-yellow-500 flex items-center justify-between gap-4">
          <span>
            ⚠️ Set a username in your <strong>Studio Settings</strong> to activate your public portfolio and online booking page.
          </span>
          <a
            href="/studio/settings"
            className="px-3 py-1.5 bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-700 dark:text-yellow-500 rounded font-bold text-xs transition-colors shrink-0"
          >
            Go to Settings
          </a>
        </div>
      )}

      {/* Tabs */}
      <div className="border-b border-border bg-card-muted/30 px-4 pt-3 flex flex-shrink-0 gap-4">
        <button
          onClick={() => {
            setActiveTab('upcoming')
            setPage(1)
          }}
          className={`pb-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all ${
            activeTab === 'upcoming' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          Upcoming (Pending / Confirmed)
        </button>
        <button
          onClick={() => {
            setActiveTab('past')
            setPage(1)
          }}
          className={`pb-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all ${
            activeTab === 'past' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          Past (Completed / Cancelled)
        </button>
      </div>

      {/* Scrollable list */}
      <div className="flex-1 overflow-y-auto p-4 md:p-6">
        {isLoading ? (
          <div className="space-y-4">
            {[1, 2, 3].map((idx) => (
              <div key={idx} className="bg-card border border-border rounded-xl p-5 animate-pulse space-y-4">
                <div className="h-6 bg-muted rounded w-1/4" />
                <div className="grid grid-cols-4 gap-4">
                  <div className="h-10 bg-muted rounded" />
                  <div className="h-10 bg-muted rounded" />
                  <div className="h-10 bg-muted rounded" />
                  <div className="h-10 bg-muted rounded" />
                </div>
              </div>
            ))}
          </div>
        ) : isError ? (
          <div className="text-center py-12 bg-card border border-border rounded-xl">
            <p className="text-destructive font-semibold">Failed to load bookings</p>
            <p className="text-muted-foreground text-sm mt-1">{error?.message || 'An unexpected error occurred.'}</p>
          </div>
        ) : bookings.length === 0 ? (
          <div className="text-center py-16 bg-card border border-border rounded-xl max-w-xl mx-auto shadow-sm animate-in fade-in-50">
            <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
              <Calendar className="w-8 h-8 text-primary" />
            </div>
            <h3 className="text-lg font-bold text-foreground">No bookings found</h3>
            <p className="text-sm text-muted-foreground mt-1 max-w-xs mx-auto">
              Schedule a photography session or connect it to packages.
            </p>
            <button
              onClick={handleOpenAddModal}
              className="mt-6 px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors text-sm font-semibold"
            >
              Add Booking
            </button>
          </div>
        ) : (
          <div className="space-y-4">
            {bookings.map((booking) => (
              <div key={booking.uuid} className="bg-card border border-border rounded-xl p-5 hover:border-primary transition-all shadow-sm">
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <h3 className="text-base font-extrabold text-foreground truncate">{booking.title}</h3>
                      <span className="text-[10px] bg-secondary text-foreground px-2 py-0.5 rounded font-mono uppercase tracking-wider shrink-0">
                        {booking.reference}
                      </span>
                    </div>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-xs text-muted-foreground">
                      <div className="flex items-center gap-2">
                        <User size={14} className="text-primary shrink-0" />
                        <div>
                          <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground/60">Client</p>
                          <p className="font-semibold text-foreground truncate">{booking.client?.name || 'No client connected'}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        <Calendar size={14} className="text-primary shrink-0" />
                        <div>
                          <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground/60">Date</p>
                          <p className="font-semibold text-foreground">{new Date(booking.starts_at).toLocaleDateString()} {new Date(booking.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        <MapPin size={14} className="text-primary shrink-0" />
                        <div>
                          <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground/60">Location</p>
                          <p className="font-semibold text-foreground truncate">{booking.location || 'N/A'}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        <DollarSign size={14} className="text-primary shrink-0" />
                        <div>
                          <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground/60">Price</p>
                          <p className="font-semibold text-foreground">
                            {booking.price ? `${booking.currency} ${booking.price.toLocaleString()}` : 'N/A'}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center justify-between lg:justify-end gap-3 border-t lg:border-t-0 border-border pt-3 lg:pt-0">
                    <span className={`text-[10px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-full ${getStatusColor(booking.status)}`}>
                      {booking.status}
                    </span>
                    <button
                      onClick={() => {
                        setSelectedBooking(booking)
                        setIsDetailsOpen(true)
                      }}
                      className="px-4 py-2 bg-secondary hover:bg-muted text-foreground rounded-lg transition-colors font-semibold text-xs flex items-center gap-1.5 shadow-xs"
                    >
                      <Eye size={14} />
                      Details
                    </button>
                  </div>
                </div>
              </div>
            ))}

            {/* Pagination */}
            {meta && meta.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border pt-6 mt-6">
                <button
                  disabled={page <= 1}
                  onClick={() => setPage(page - 1)}
                  className="px-4 py-2 border border-border rounded-lg text-sm font-semibold hover:bg-secondary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  Previous
                </button>
                <span className="text-sm text-muted-foreground">
                  Page {meta.current_page} of {meta.last_page}
                </span>
                <button
                  disabled={page >= meta.last_page}
                  onClick={() => setPage(page + 1)}
                  className="px-4 py-2 border border-border rounded-lg text-sm font-semibold hover:bg-secondary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  Next
                </button>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Add Booking Modal */}
      {isAddOpen && (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-card border border-border rounded-2xl w-full max-w-lg shadow-2xl p-6 relative">
            <div className="flex justify-between items-center mb-6">
              <div className="flex items-center gap-2">
                <Sparkles className="w-5 h-5 text-primary animate-pulse" />
                <h3 className="text-xl font-bold text-foreground">Schedule Booking</h3>
              </div>
              <button
                onClick={() => setIsAddOpen(false)}
                className="p-1.5 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors"
              >
                <X size={20} />
              </button>
            </div>

            {/* Overlap Error Warn */}
            {overlapError && (
              <div className="mb-5 p-4 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-xl">
                <div className="flex items-start gap-2.5 text-red-700 dark:text-red-500">
                  <AlertTriangle size={18} className="shrink-0 mt-0.5" />
                  <div>
                    <p className="text-xs font-bold uppercase tracking-wider">Booking Conflicts Detected!</p>
                    <p className="text-sm mt-1">{overlapError.message}</p>
                    <div className="mt-3 space-y-1.5">
                      {overlapError.conflicts.map((conflict, idx) => (
                        <div key={idx} className="text-xs font-semibold p-2 bg-background border border-border rounded-lg text-foreground flex justify-between">
                          <span>{conflict.title}</span>
                          <span className="text-muted-foreground">
                            {new Date(conflict.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          </span>
                        </div>
                      ))}
                    </div>
                    <button
                      onClick={(e) => handleCreateSubmit(e, true)}
                      className="mt-4 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5"
                    >
                      {createMutation.isPending && <Loader2 className="w-3 animate-spin" />}
                      Force Save Booking
                    </button>
                  </div>
                </div>
              </div>
            )}

            <form onSubmit={(e) => handleCreateSubmit(e, false)} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Event Title *</label>
                <input
                  type="text"
                  required
                  value={formData.title}
                  onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                  placeholder="Wedding Sarah & John"
                  className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Client contact</label>
                  <select
                    value={formData.client_uuid}
                    onChange={(e) => setFormData({ ...formData, client_uuid: e.target.value })}
                    className="w-full px-3 py-2.5 bg-background border border-border rounded-lg text-foreground text-sm focus:outline-none focus:border-primary font-medium"
                  >
                    <option value="">-- Optional --</option>
                    {clients.map((c) => (
                      <option key={c.uuid} value={c.uuid}>{c.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Pricing tier</label>
                  <select
                    value={formData.package_uuid}
                    onChange={(e) => setFormData({ ...formData, package_uuid: e.target.value })}
                    className="w-full px-3 py-2.5 bg-background border border-border rounded-lg text-foreground text-sm focus:outline-none focus:border-primary font-medium"
                  >
                    <option value="">-- Optional --</option>
                    {packages.map((p) => (
                      <option key={p.uuid} value={p.uuid}>{p.name} ({p.currency} {p.price.toLocaleString()})</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Starts At *</label>
                  <input
                    type="datetime-local"
                    required
                    value={formData.starts_at}
                    onChange={(e) => setFormData({ ...formData, starts_at: e.target.value })}
                    className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Ends At</label>
                  <input
                    type="datetime-local"
                    value={formData.ends_at}
                    onChange={(e) => setFormData({ ...formData, ends_at: e.target.value })}
                    className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Price override</label>
                  <input
                    type="number"
                    value={formData.price}
                    onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                    placeholder="250000"
                    className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Location</label>
                  <input
                    type="text"
                    value={formData.location}
                    onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                    placeholder="e.g. Kigali, Rwanda"
                    className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Booking notes</label>
                <textarea
                  rows={3}
                  value={formData.notes}
                  onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                  placeholder="Directions, contact info, special requests..."
                  className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                />
              </div>

              <div className="flex gap-3 pt-4 border-t border-border mt-6">
                <button
                  type="button"
                  onClick={() => setIsAddOpen(false)}
                  className="flex-1 py-3 bg-secondary text-foreground rounded-lg hover:bg-muted font-semibold text-xs"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending}
                  className="flex-1 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm"
                >
                  {createMutation.isPending && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                  Schedule
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Booking Details Dialog */}
      {isDetailsOpen && selectedBooking && (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-2xl w-full max-w-lg shadow-2xl p-6 relative">
            <div className="flex justify-between items-start mb-6 border-b border-border pb-4">
              <div className="min-w-0">
                <h3 className="text-lg font-bold text-foreground truncate">{selectedBooking.title}</h3>
                <span className="text-[10px] bg-secondary text-foreground px-2 py-0.5 rounded font-mono uppercase tracking-wider mt-1 inline-block">
                  {selectedBooking.reference}
                </span>
              </div>
              <button
                onClick={() => {
                  setIsDetailsOpen(false)
                  setSelectedBooking(null)
                }}
                className="p-1.5 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors"
              >
                <X size={20} />
              </button>
            </div>

            <div className="space-y-4 text-sm text-foreground mb-6">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Client Name</p>
                  <p className="font-semibold text-foreground mt-0.5">{selectedBooking.client?.name || 'Walk-in client'}</p>
                </div>
                <div>
                  <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Package Connected</p>
                  <p className="font-semibold text-foreground mt-0.5">{selectedBooking.package?.name || 'Custom event'}</p>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Starts At</p>
                  <p className="font-semibold text-foreground mt-0.5">{new Date(selectedBooking.starts_at).toLocaleString()}</p>
                </div>
                <div>
                  <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Ends At</p>
                  <p className="font-semibold text-foreground mt-0.5">
                    {selectedBooking.ends_at ? new Date(selectedBooking.ends_at).toLocaleString() : 'N/A'}
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Session timezone</p>
                  <p className="font-semibold text-foreground mt-0.5">{selectedBooking.timezone}</p>
                </div>
                <div>
                  <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Location</p>
                  <p className="font-semibold text-foreground mt-0.5">{selectedBooking.location || 'N/A'}</p>
                </div>
              </div>

              <div>
                <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground mb-2">Change status</p>
                <div className="flex gap-2 flex-wrap">
                  {(['pending', 'confirmed', 'completed', 'cancelled'] as BookingStatus[]).map((status) => (
                    <button
                      key={status}
                      onClick={() => handleStatusChange(selectedBooking, status)}
                      className={`px-3 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors ${
                        selectedBooking.status === status
                          ? getStatusColor(status) + ' ring-2 ring-primary/45'
                          : 'bg-secondary hover:bg-muted text-muted-foreground'
                      }`}
                    >
                      {status}
                    </button>
                  ))}
                </div>
              </div>

              {selectedBooking.notes && (
                <div>
                  <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Session Notes</p>
                  <p className="p-3 bg-secondary/30 border border-border rounded-lg text-xs text-muted-foreground mt-1 whitespace-pre-wrap">
                    {selectedBooking.notes}
                  </p>
                </div>
              )}
            </div>

            <div className="flex gap-3 pt-4 border-t border-border mt-6">
              <button
                onClick={() => handleDeleteClick(selectedBooking)}
                className="py-2.5 px-4 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg font-bold text-xs flex items-center gap-1.5 shrink-0 transition-colors"
              >
                <Trash2 size={14} />
                Delete Booking
              </button>
              <button
                onClick={() => {
                  setIsDetailsOpen(false)
                  setSelectedBooking(null)
                }}
                className="flex-1 py-2.5 bg-primary text-primary-foreground hover:bg-accent rounded-lg font-bold text-xs transition-colors"
              >
                Close Details
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  )
}
