'use client'

import { Plus, Edit2, Trash2 } from 'lucide-react'
import { useState } from 'react'

export default function Packages() {
  const [packages, setPackages] = useState([
    { id: 1, name: 'Portrait Session', description: 'Professional portrait session', price: 75000, duration: '2 hours', deliverables: '50+ edited photos' },
    { id: 2, name: 'Wedding Package', description: 'Full day wedding coverage', price: 375000, duration: '8-10 hours', deliverables: '500+ photos, album' },
    { id: 3, name: 'Event Photography', description: 'Corporate or private event', price: 175000, duration: '4 hours', deliverables: '200+ photos' },
    { id: 4, name: 'Pre-wedding', description: 'Engagement and pre-wedding shoot', price: 125000, duration: '4-6 hours', deliverables: '300+ photos' }
  ])

  return (
    <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6 flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Pricing Packages</h1>
            <p className="text-muted-foreground mt-1">Create and manage your photography service packages</p>
          </div>
          <button className="px-6 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors flex items-center gap-2 font-semibold">
            <Plus size={20} />
            New Package
          </button>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Packages Grid */}
          <div className="grid md:grid-cols-2 gap-6">
            {packages.map((pkg) => (
              <div key={pkg.id} className="bg-card border border-border rounded-lg p-6 hover:border-primary transition-colors">
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <h3 className="text-lg font-bold text-foreground">{pkg.name}</h3>
                    <p className="text-sm text-muted-foreground mt-1">{pkg.description}</p>
                  </div>
                </div>

                <div className="mb-6 pb-6 border-b border-border">
                  <div className="text-3xl font-bold text-primary mb-2">
                    RWF {pkg.price.toLocaleString()}
                  </div>
                  <p className="text-sm text-muted-foreground">Session duration: {pkg.duration}</p>
                </div>

                <div className="mb-6">
                  <p className="text-sm font-semibold text-foreground mb-2">Includes:</p>
                  <p className="text-sm text-muted-foreground">{pkg.deliverables}</p>
                </div>

                <div className="flex gap-3">
                  <button className="flex-1 flex items-center justify-center gap-2 py-2 px-4 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm">
                    <Edit2 size={16} />
                    Edit
                  </button>
                  <button className="flex-1 flex items-center justify-center gap-2 py-2 px-4 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors font-semibold text-sm">
                    <Trash2 size={16} />
                    Delete
                  </button>
                </div>
              </div>
            ))}
          </div>

          {/* Add New Package Card */}
          <button className="w-full mt-6 py-12 border-2 border-dashed border-border rounded-lg hover:border-primary transition-colors text-center">
            <Plus className="w-12 h-12 text-primary mx-auto mb-3" />
            <p className="font-semibold text-foreground mb-1">Add New Package</p>
            <p className="text-sm text-muted-foreground">Create a new pricing package for your services</p>
          </button>
        </div>
    </main>
  )
}
