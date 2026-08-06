'use client'

import { useState } from 'react'
import { Plus, Edit2, Trash2, Loader2, Sparkles, X, Check } from 'lucide-react'
import { useStudioPackages, useCreatePackageMutation, useUpdatePackageMutation, useDeletePackageMutation, PackageItem } from '@/lib/queries/packages'

export default function Packages() {
  const [activeTab, setActiveTab] = useState<'all' | 'active'>('all')

  // Modal & form states
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [activePackage, setActivePackage] = useState<PackageItem | null>(null)
  const [isDeleteOpen, setIsDeleteOpen] = useState(false)
  const [packageToDelete, setPackageToDelete] = useState<PackageItem | null>(null)

  const [formData, setFormData] = useState({
    name: '',
    description: '',
    price: 0,
    currency: 'RWF',
    durationValue: 2,
    durationUnit: 'hours', // 'mins' | 'hours' | 'days'
    deliverables: [''],
    is_active: true,
  })

  // Queries & Mutations
  const { data: packagesData, isLoading, isError, error } = useStudioPackages({
    active: activeTab === 'active' ? true : undefined,
    sort: 'sort_order',
  })

  const createMutation = useCreatePackageMutation()
  const updateMutation = useUpdatePackageMutation()
  const deleteMutation = useDeletePackageMutation()

  const handleOpenForm = (pkg: PackageItem | null = null) => {
    setActivePackage(pkg)
    if (pkg) {
      // Deconstruct duration minutes
      let value = pkg.duration_minutes
      let unit = 'mins'
      if (value % 1440 === 0) {
        value = value / 1440
        unit = 'days'
      } else if (value % 60 === 0) {
        value = value / 60
        unit = 'hours'
      }

      setFormData({
        name: pkg.name,
        description: pkg.description || '',
        price: pkg.price,
        currency: pkg.currency,
        durationValue: value,
        durationUnit: unit,
        deliverables: pkg.deliverables.length > 0 ? [...pkg.deliverables] : [''],
        is_active: pkg.is_active,
      })
    } else {
      setFormData({
        name: '',
        description: '',
        price: 0,
        currency: 'RWF',
        durationValue: 2,
        durationUnit: 'hours',
        deliverables: [''],
        is_active: true,
      })
    }
    setIsFormOpen(true)
  }

  const handleAddDeliverable = () => {
    setFormData({
      ...formData,
      deliverables: [...formData.deliverables, ''],
    })
  }

  const handleRemoveDeliverable = (index: number) => {
    const next = formData.deliverables.filter((_, idx) => idx !== index)
    setFormData({
      ...formData,
      deliverables: next.length === 0 ? [''] : next,
    })
  }

  const handleDeliverableChange = (index: number, val: string) => {
    const next = [...formData.deliverables]
    next[index] = val
    setFormData({
      ...formData,
      deliverables: next,
    })
  }

  const handleFormSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!formData.name) return

    // Convert duration to minutes
    let durationMinutes = formData.durationValue
    if (formData.durationUnit === 'hours') {
      durationMinutes = formData.durationValue * 60
    } else if (formData.durationUnit === 'days') {
      durationMinutes = formData.durationValue * 1440
    }

    const filteredDeliverables = formData.deliverables.map((d) => d.trim()).filter(Boolean)

    const payload = {
      name: formData.name,
      description: formData.description || null,
      price: Number(formData.price),
      currency: formData.currency,
      duration_minutes: durationMinutes,
      deliverables: filteredDeliverables,
      is_active: formData.is_active,
    }

    if (activePackage) {
      updateMutation.mutate({
        uuid: activePackage.uuid,
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
        }
      })
    }
  }

  const handleDeleteClick = (pkg: PackageItem) => {
    setPackageToDelete(pkg)
    setIsDeleteOpen(true)
  }

  const handleDeleteConfirm = () => {
    if (!packageToDelete) return
    deleteMutation.mutate(packageToDelete.uuid, {
      onSuccess: () => {
        setIsDeleteOpen(false)
        setPackageToDelete(null)
      }
    })
  }

  const packages = packagesData?.data || []

  return (
    <main className="flex-1 flex flex-col h-screen overflow-hidden bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-4 md:p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 flex-shrink-0">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-foreground tracking-tight">Pricing Packages</h1>
          <p className="text-xs md:text-sm text-muted-foreground mt-1">Create and manage your photography service tiers</p>
        </div>
        <button
          onClick={() => handleOpenForm(null)}
          className="px-5 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-all flex items-center justify-center gap-2 font-semibold text-sm shadow-sm"
        >
          <Plus size={18} />
          New Package
        </button>
      </div>

      {/* Filter Tabs */}
      <div className="border-b border-border bg-card-muted/30 px-4 pt-3 flex flex-shrink-0 gap-4">
        <button
          onClick={() => setActiveTab('all')}
          className={`pb-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all ${
            activeTab === 'all' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          All Tiers
        </button>
        <button
          onClick={() => setActiveTab('active')}
          className={`pb-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all ${
            activeTab === 'active' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          Active Only
        </button>
      </div>

      {/* Scrollable grid */}
      <div className="flex-1 overflow-y-auto p-4 md:p-6">
        {isLoading ? (
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {[1, 2, 3].map((idx) => (
              <div key={idx} className="bg-card border border-border rounded-xl p-6 animate-pulse space-y-4">
                <div className="h-6 bg-muted rounded w-1/2" />
                <div className="h-8 bg-muted rounded w-1/3" />
                <div className="space-y-2">
                  <div className="h-4 bg-muted rounded w-2/3" />
                  <div className="h-4 bg-muted rounded w-1/2" />
                </div>
              </div>
            ))}
          </div>
        ) : isError ? (
          <div className="text-center py-12 bg-card border border-border rounded-xl">
            <p className="text-destructive font-semibold">Failed to load packages</p>
            <p className="text-muted-foreground text-sm mt-1">{error?.message || 'An unexpected error occurred.'}</p>
          </div>
        ) : packages.length === 0 ? (
          <div className="text-center py-16 bg-card border border-border rounded-xl max-w-xl mx-auto shadow-sm">
            <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
              <Plus className="w-8 h-8 text-primary" />
            </div>
            <h3 className="text-lg font-bold text-foreground">No packages found</h3>
            <p className="text-sm text-muted-foreground mt-1 max-w-xs mx-auto">
              Create a service tier for online bookings or CRM proposals.
            </p>
            <button
              onClick={() => handleOpenForm(null)}
              className="mt-6 px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors text-sm font-semibold"
            >
              Add New Package
            </button>
          </div>
        ) : (
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {packages.map((pkg) => (
              <div
                key={pkg.uuid}
                className={`bg-card border rounded-xl p-6 hover:border-primary transition-all flex flex-col justify-between shadow-sm relative ${
                  pkg.is_active ? 'border-border' : 'border-dashed border-border opacity-70'
                }`}
              >
                <div>
                  <div className="flex justify-between items-start mb-2">
                    <h3 className="text-base font-extrabold text-foreground">{pkg.name}</h3>
                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${
                      pkg.is_active ? 'bg-green-100 text-green-700' : 'bg-secondary text-muted-foreground'
                    }`}>
                      {pkg.is_active ? 'Active' : 'Draft'}
                    </span>
                  </div>
                  {pkg.description && (
                    <p className="text-xs text-muted-foreground mb-4 line-clamp-2">{pkg.description}</p>
                  )}

                  <div className="py-4 border-t border-b border-border my-4">
                    <div className="text-2xl font-black text-primary">
                      {pkg.currency} {pkg.price.toLocaleString()}
                    </div>
                    <p className="text-xs text-muted-foreground mt-1">Duration: {pkg.duration_label}</p>
                  </div>

                  {pkg.deliverables && pkg.deliverables.length > 0 && (
                    <div className="space-y-1.5 mb-6">
                      <p className="text-xs font-bold text-foreground uppercase tracking-wider mb-2">Includes:</p>
                      {pkg.deliverables.map((item, idx) => (
                        <div key={idx} className="flex items-start gap-2 text-xs text-muted-foreground">
                          <Check size={14} className="text-primary shrink-0 mt-0.5" />
                          <span>{item}</span>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                <div className="flex gap-2 pt-4 border-t border-border mt-auto">
                  <button
                    onClick={() => handleOpenForm(pkg)}
                    className="flex-1 flex items-center justify-center gap-1.5 py-2 px-3 bg-secondary hover:bg-muted text-foreground rounded-lg transition-colors font-semibold text-xs"
                  >
                    <Edit2 size={12} />
                    Edit
                  </button>
                  <button
                    onClick={() => handleDeleteClick(pkg)}
                    className="flex-1 flex items-center justify-center gap-1.5 py-2 px-3 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors font-semibold text-xs"
                  >
                    <Trash2 size={12} />
                    Delete
                  </button>
                </div>
              </div>
            ))}

            {/* Add New dashed Card */}
            <button
              onClick={() => handleOpenForm(null)}
              className="border-2 border-dashed border-border rounded-xl p-8 hover:border-primary transition-all flex flex-col items-center justify-center text-center group min-h-[300px]"
            >
              <Plus className="w-10 h-10 text-muted-foreground group-hover:text-primary transition-colors mb-3" />
              <p className="font-extrabold text-foreground text-sm group-hover:text-primary transition-colors">Add New Package</p>
              <p className="text-xs text-muted-foreground mt-1 max-w-[200px]">Create another service offering for client sessions</p>
            </button>
          </div>
        )}
      </div>

      {/* Slide-over Drawer / Form Modal */}
      {isFormOpen && (
        <div className="fixed inset-0 bg-black/70 z-50 flex justify-end">
          <div className="w-full max-w-lg bg-card border-l border-border h-full p-6 flex flex-col justify-between shadow-2xl relative overflow-y-auto">
            <div>
              <div className="flex justify-between items-center mb-6">
                <div className="flex items-center gap-2">
                  <Sparkles className="w-5 h-5 text-primary" />
                  <h2 className="text-xl font-bold text-foreground">
                    {activePackage ? 'Edit Pricing Tier' : 'New Service Package'}
                  </h2>
                </div>
                <button
                  onClick={() => setIsFormOpen(false)}
                  className="p-1.5 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors"
                >
                  <X size={20} />
                </button>
              </div>

              <form id="package-form" onSubmit={handleFormSubmit} className="space-y-4">
                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Package Name *</label>
                  <input
                    type="text"
                    required
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                    placeholder="e.g. Wedding Full Day"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Description</label>
                  <textarea
                    rows={3}
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                    placeholder="Describe what services are included in this package..."
                  />
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Price *</label>
                    <input
                      type="number"
                      required
                      min={0}
                      value={formData.price || ''}
                      onChange={(e) => setFormData({ ...formData, price: Number(e.target.value) })}
                      className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                      placeholder="e.g. 250000"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Currency</label>
                    <input
                      type="text"
                      required
                      value={formData.currency}
                      onChange={(e) => setFormData({ ...formData, currency: e.target.value })}
                      className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                      placeholder="RWF"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Session Duration *</label>
                  <div className="grid grid-cols-2 gap-3">
                    <input
                      type="number"
                      required
                      min={1}
                      value={formData.durationValue || ''}
                      onChange={(e) => setFormData({ ...formData, durationValue: Number(e.target.value) })}
                      className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                    />
                    <select
                      value={formData.durationUnit}
                      onChange={(e) => setFormData({ ...formData, durationUnit: e.target.value })}
                      className="w-full px-3 py-2.5 bg-background border border-border rounded-lg text-foreground text-sm focus:outline-none focus:border-primary font-medium"
                    >
                      <option value="mins">Minutes</option>
                      <option value="hours">Hours</option>
                      <option value="days">Days</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5 flex justify-between items-center">
                    <span>Deliverables / Includes</span>
                    <button
                      type="button"
                      onClick={handleAddDeliverable}
                      className="text-xs text-primary hover:underline font-bold"
                    >
                      + Add Item
                    </button>
                  </label>
                  <div className="space-y-2 max-h-48 overflow-y-auto p-1 border border-border rounded-lg bg-background">
                    {formData.deliverables.map((item, index) => (
                      <div key={index} className="flex gap-2">
                        <input
                          type="text"
                          required
                          value={item}
                          onChange={(e) => handleDeliverableChange(index, e.target.value)}
                          placeholder="e.g. 100+ edited high-res photos"
                          className="flex-1 px-3 py-2 bg-background border border-border rounded-lg text-foreground text-xs focus:outline-none focus:border-primary"
                        />
                        <button
                          type="button"
                          onClick={() => handleRemoveDeliverable(index)}
                          className="p-2 text-red-500 hover:bg-red-50 rounded-lg shrink-0 transition-colors"
                        >
                          <X size={16} />
                        </button>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="flex items-center gap-2 pt-2">
                  <input
                    type="checkbox"
                    id="is_active"
                    checked={formData.is_active}
                    onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                    className="w-4 h-4 text-primary bg-background border-border rounded focus:ring-primary"
                  />
                  <label htmlFor="is_active" className="text-xs font-bold text-foreground uppercase tracking-wider cursor-pointer">
                    Active (visible on Booking page)
                  </label>
                </div>
              </form>
            </div>

            {/* Footer */}
            <div className="border-t border-border pt-4 flex gap-3 mt-6">
              <button
                type="button"
                onClick={() => setIsFormOpen(false)}
                className="flex-1 py-3 bg-secondary text-foreground rounded-lg hover:bg-muted font-semibold text-xs text-center"
              >
                Cancel
              </button>
              <button
                type="submit"
                form="package-form"
                disabled={createMutation.isPending || updateMutation.isPending}
                className="flex-1 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm"
              >
                {(createMutation.isPending || updateMutation.isPending) && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                {activePackage ? 'Save Changes' : 'Create Package'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Delete Confirmation */}
      {isDeleteOpen && packageToDelete && (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-xl w-full max-w-md shadow-2xl p-6">
            <h3 className="text-lg font-bold text-foreground mb-2">Delete Pricing Tier</h3>
            <p className="text-sm text-muted-foreground mb-6">
              Are you sure you want to delete <span className="font-semibold text-foreground">{packageToDelete.name}</span>? This action soft-deletes the tier but preserves historical packages referenced in client bookings.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => {
                  setIsDeleteOpen(false)
                  setPackageToDelete(null)
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
