'use client'

import { useState } from 'react'
import { Plus, Mail, Phone, MapPin, MoreHorizontal, Search, Trash2, Edit2, Loader2, ArrowLeft, X, Sparkles } from 'lucide-react'
import Link from 'next/link'
import { useClients, useCreateClientMutation, useUpdateClientMutation, useDeleteClientMutation, ClientItem } from '@/lib/queries/clients'

export default function Clients() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [sort, setSort] = useState('created_desc')
  
  // Modal states
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [activeClient, setActiveClient] = useState<ClientItem | null>(null) // null for create
  const [isDeleteOpen, setIsDeleteOpen] = useState(false)
  const [clientToDelete, setClientToDelete] = useState<ClientItem | null>(null)
  
  // Dropdown states
  const [activeMenuId, setActiveMenuId] = useState<string | null>(null)

  // Form states
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    company_name: '',
    location: '',
    instagram: '',
    notes: '',
    tagsString: '',
  })

  // Queries & Mutations
  const { data: clientsData, isLoading, isError, error } = useClients({
    page,
    search,
    sort,
    per_page: 10,
  })

  const createMutation = useCreateClientMutation()
  const updateMutation = useUpdateClientMutation()
  const deleteMutation = useDeleteClientMutation()

  const handleOpenForm = (client: ClientItem | null = null) => {
    setActiveClient(client)
    setActiveMenuId(null)
    if (client) {
      setFormData({
        name: client.name,
        email: client.email || '',
        phone: client.phone || '',
        company_name: client.company_name || '',
        location: client.location || '',
        instagram: client.instagram || '',
        notes: client.notes || '',
        tagsString: client.tags ? client.tags.join(', ') : '',
      })
    } else {
      setFormData({
        name: '',
        email: '',
        phone: '',
        company_name: '',
        location: '',
        instagram: '',
        notes: '',
        tagsString: '',
      })
    }
    setIsFormOpen(true)
  }

  const handleFormSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!formData.name) return

    const parsedTags = formData.tagsString
      ? formData.tagsString.split(',').map((t) => t.trim()).filter(Boolean)
      : []

    const payload = {
      name: formData.name,
      email: formData.email || null,
      phone: formData.phone || null,
      company_name: formData.company_name || null,
      location: formData.location || null,
      instagram: formData.instagram || null,
      notes: formData.notes || null,
      tags: parsedTags,
    }

    if (activeClient) {
      updateMutation.mutate({
        uuid: activeClient.uuid,
        ...payload,
      }, {
        onSuccess: () => {
          setIsFormOpen(false)
        }
      })
    } else {
      createMutation.mutate(payload, {
        onSuccess: () => {
          setIsFormOpen(false)
          setPage(1)
        }
      })
    }
  }

  const handleDeleteClick = (client: ClientItem) => {
    setClientToDelete(client)
    setActiveMenuId(null)
    setIsDeleteOpen(true)
  }

  const handleDeleteConfirm = () => {
    if (!clientToDelete) return
    deleteMutation.mutate(clientToDelete.uuid, {
      onSuccess: () => {
        setIsDeleteOpen(false)
        setClientToDelete(null)
      }
    })
  }

  const clients = clientsData?.data || []
  const meta = clientsData?.meta

  return (
    <main className="flex-1 flex flex-col bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-4 md:p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 flex-shrink-0">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-foreground tracking-tight">Clients</h1>
          <p className="text-xs md:text-sm text-muted-foreground mt-1">Manage CRM contacts and view related projects</p>
        </div>
        <button
          onClick={() => handleOpenForm(null)}
          className="px-5 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-all flex items-center justify-center gap-2 font-semibold text-sm shadow-sm"
        >
          <Plus size={18} />
          Add Client
        </button>
      </div>

      {/* Filter and Search Bar */}
      <div className="border-b border-border bg-card-muted/30 p-4 flex flex-col sm:flex-row gap-3 flex-shrink-0">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-2.5 h-4.5 w-4.5 text-muted-foreground" />
          <input
            type="text"
            placeholder="Search name, email, phone, company..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            className="w-full pl-10 pr-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
          />
        </div>
        <div className="flex gap-2">
          <select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            className="px-3 py-2 bg-background border border-border rounded-lg text-foreground text-sm focus:outline-none focus:border-primary font-medium"
          >
            <option value="created_desc">Newest First</option>
            <option value="created_asc">Oldest First</option>
            <option value="name_asc">Name A-Z</option>
            <option value="name_desc">Name Z-A</option>
          </select>
        </div>
      </div>

      {/* Content Container */}
      <div className="p-4 sm:p-6">
        {isLoading ? (
          <div className="grid md:grid-cols-2 gap-6">
            {[1, 2, 3, 4].map((idx) => (
              <div key={idx} className="bg-card border border-border rounded-xl p-6 animate-pulse space-y-4">
                <div className="flex justify-between items-start">
                  <div className="h-6 bg-muted rounded w-1/3" />
                  <div className="h-8 bg-muted rounded-full w-8" />
                </div>
                <div className="space-y-2">
                  <div className="h-4 bg-muted rounded w-2/3" />
                  <div className="h-4 bg-muted rounded w-1/2" />
                  <div className="h-4 bg-muted rounded w-3/4" />
                </div>
                <div className="flex gap-3 pt-4 border-t border-border">
                  <div className="h-8 bg-muted rounded w-1/2" />
                  <div className="h-8 bg-muted rounded w-1/2" />
                </div>
              </div>
            ))}
          </div>
        ) : isError ? (
          <div className="text-center py-12 bg-card border border-border rounded-xl">
            <p className="text-destructive font-semibold">Failed to load clients</p>
            <p className="text-muted-foreground text-sm mt-1">{error?.message || 'An unexpected error occurred.'}</p>
          </div>
        ) : clients.length === 0 ? (
          <div className="text-center py-16 bg-card border border-border rounded-xl max-w-xl mx-auto shadow-sm">
            <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
              <Plus className="w-8 h-8 text-primary" />
            </div>
            <h3 className="text-lg font-bold text-foreground">No clients found</h3>
            <p className="text-sm text-muted-foreground mt-1 max-w-xs mx-auto">
              {search ? "No clients match your search criteria." : "Get started by adding your first client contact."}
            </p>
            {!search && (
              <button
                onClick={() => handleOpenForm(null)}
                className="mt-6 px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors text-sm font-semibold"
              >
                Add Client
              </button>
            )}
          </div>
        ) : (
          <div className="space-y-6">
            <div className="grid md:grid-cols-2 gap-6 relative">
              {clients.map((client) => (
                <div
                  key={client.uuid}
                  className="bg-card border border-border rounded-xl p-5 hover:border-primary transition-all shadow-sm flex flex-col justify-between relative group"
                >
                  <div>
                    <div className="flex items-start justify-between mb-3">
                      <div className="min-w-0">
                        <h3 className="text-base font-extrabold text-foreground truncate" title={client.name}>
                          {client.name}
                        </h3>
                        {client.company_name && (
                          <p className="text-xs text-primary font-semibold mt-0.5">{client.company_name}</p>
                        )}
                      </div>
                      <div className="relative">
                        <button
                          onClick={() => setActiveMenuId(activeMenuId === client.uuid ? null : client.uuid)}
                          className="p-1.5 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors shrink-0"
                        >
                          <MoreHorizontal size={18} />
                        </button>
                        
                        {/* Dropdown Options */}
                        {activeMenuId === client.uuid && (
                          <div className="absolute right-0 mt-1 w-36 bg-card border border-border rounded-lg shadow-lg z-10 py-1 overflow-hidden">
                            <button
                              onClick={() => handleOpenForm(client)}
                              className="w-full px-3 py-2 text-left text-xs font-semibold hover:bg-secondary text-foreground flex items-center gap-2"
                            >
                              <Edit2 size={14} />
                              Edit Profile
                            </button>
                            <button
                              onClick={() => handleDeleteClick(client)}
                              className="w-full px-3 py-2 text-left text-xs font-semibold hover:bg-red-50 text-red-600 flex items-center gap-2 border-t border-border"
                            >
                              <Trash2 size={14} />
                              Delete
                            </button>
                          </div>
                        )}
                      </div>
                    </div>

                    <div className="space-y-2.5 mb-5 text-xs text-muted-foreground">
                      {client.email && (
                        <div className="flex items-center gap-2.5">
                          <Mail size={14} className="text-primary shrink-0" />
                          <span className="truncate">{client.email}</span>
                        </div>
                      )}
                      {client.phone && (
                        <div className="flex items-center gap-2.5">
                          <Phone size={14} className="text-primary shrink-0" />
                          <span>{client.phone}</span>
                        </div>
                      )}
                      {client.location && (
                        <div className="flex items-center gap-2.5">
                          <MapPin size={14} className="text-primary shrink-0" />
                          <span className="truncate">{client.location}</span>
                        </div>
                      )}
                    </div>

                    {/* Render Tags */}
                    {client.tags && client.tags.length > 0 && (
                      <div className="flex flex-wrap gap-1.5 mb-4">
                        {client.tags.map((tag, idx) => (
                          <span key={idx} className="text-[10px] font-bold px-2 py-0.5 bg-primary/10 border border-primary/20 text-primary rounded-full uppercase tracking-wider">
                            {tag}
                          </span>
                        ))}
                      </div>
                    )}
                  </div>

                  <div className="border-t border-border pt-4 mt-auto flex items-center justify-between text-xs text-muted-foreground">
                    <div>
                      {client.last_contacted_at ? (
                        <span>Last contacted: {new Date(client.last_contacted_at).toLocaleDateString()}</span>
                      ) : (
                        <span>No contact history</span>
                      )}
                    </div>
                    <Link
                      href={`/studio/galleries?client_uuid=${client.uuid}`}
                      className="px-3 py-1.5 bg-secondary hover:bg-muted text-foreground rounded-lg transition-colors font-semibold shadow-xs"
                    >
                      View Projects
                    </Link>
                  </div>
                </div>
              ))}
            </div>

            {/* Pagination Controls */}
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

      {/* Slide-over Drawer / Modal Form for Create and Edit */}
      {isFormOpen && (
        <div className="fixed inset-0 bg-black/70 z-50 flex justify-end">
          <div 
            className="w-full max-w-lg bg-card border-l border-border h-[100dvh] flex flex-col justify-between shadow-2xl relative animate-in slide-in-from-right duration-300"
          >
            {/* Header */}
            <div className="p-6 pb-4 border-b border-border shrink-0">
              <div className="flex justify-between items-center">
                <div className="flex items-center gap-2">
                  <Sparkles className="w-5 h-5 text-primary" />
                  <h2 className="text-xl font-bold text-foreground">
                    {activeClient ? 'Edit Client Details' : 'Add New Client'}
                  </h2>
                </div>
                <button
                  onClick={() => setIsFormOpen(false)}
                  className="p-1.5 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors"
                >
                  <X size={20} />
                </button>
              </div>
            </div>

            {/* Scrollable Form Body */}
            <div className="flex-1 overflow-y-auto p-6 pb-8 space-y-4">
              <form id="client-form" onSubmit={handleFormSubmit} className="space-y-4">
                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Full Name *</label>
                  <input
                    type="text"
                    required
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                    placeholder="Sarah & John"
                  />
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Email Address</label>
                    <input
                      type="email"
                      value={formData.email}
                      onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                      className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                      placeholder="sarah@example.com"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Phone Number</label>
                    <input
                      type="tel"
                      value={formData.phone}
                      onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                      className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                      placeholder="+250 788 123 456"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Company Name</label>
                    <input
                      type="text"
                      value={formData.company_name}
                      onChange={(e) => setFormData({ ...formData, company_name: e.target.value })}
                      className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                      placeholder="Tech Inc"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Instagram Handle</label>
                    <input
                      type="text"
                      value={formData.instagram}
                      onChange={(e) => setFormData({ ...formData, instagram: e.target.value })}
                      className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                      placeholder="@sarah_clicks"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Location</label>
                  <input
                    type="text"
                    value={formData.location}
                    onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                    className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                    placeholder="Kigali, Rwanda"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Tags (comma-separated)</label>
                  <input
                    type="text"
                    value={formData.tagsString}
                    onChange={(e) => setFormData({ ...formData, tagsString: e.target.value })}
                    className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                    placeholder="VIP, Wedding, Corporate"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Notes</label>
                  <textarea
                    rows={4}
                    value={formData.notes}
                    onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                    className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                    placeholder="Additional context about this client relationship..."
                  />
                </div>
              </form>
            </div>

            {/* Form Footer */}
            <div className="border-t border-border p-6 flex gap-3 shrink-0">
              <button
                type="button"
                onClick={() => setIsFormOpen(false)}
                className="flex-1 py-3 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-xs text-center"
              >
                Cancel
              </button>
              <button
                type="submit"
                form="client-form"
                disabled={createMutation.isPending || updateMutation.isPending}
                className="flex-1 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm"
              >
                {(createMutation.isPending || updateMutation.isPending) && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                {activeClient ? 'Save Changes' : 'Create Contact'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Confirmation Dialog for Delete */}
      {isDeleteOpen && clientToDelete && (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-xl w-full max-w-md shadow-2xl p-6">
            <h3 className="text-lg font-bold text-foreground mb-2">Delete Client Contact</h3>
            <p className="text-sm text-muted-foreground mb-6">
              Are you sure you want to delete <span className="font-semibold text-foreground">{clientToDelete.name}</span>? This will soft-delete their contact profile but preserve any historical bookings.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => {
                  setIsDeleteOpen(false)
                  setClientToDelete(null)
                }}
                className="flex-1 py-2.5 bg-secondary text-foreground rounded-lg hover:bg-muted font-semibold text-xs"
              >
                Cancel
              </button>
              <button
                onClick={handleDeleteConfirm}
                disabled={deleteMutation.isPending}
                className="flex-1 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 font-bold text-xs flex items-center justify-center gap-1.5"
              >
                {deleteMutation.isPending && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  )
}
