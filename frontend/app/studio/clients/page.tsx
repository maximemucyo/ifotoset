'use client'

import { Plus, Mail, Phone, MapPin, MoreHorizontal } from 'lucide-react'
import Link from 'next/link'
import { useState } from 'react'

export default function Clients() {
  const [clients] = useState([
    { id: 1, name: 'Sarah & John', email: 'sarah@example.com', phone: '+250 788 123 456', location: 'Kigali, Rwanda', galleries: 2, lastContact: '2024-01-15' },
    { id: 2, name: 'Emma & David', email: 'emma@example.com', phone: '+250 789 234 567', location: 'Butare, Rwanda', galleries: 1, lastContact: '2024-01-12' },
    { id: 3, name: 'Tech Company Inc', email: 'events@techco.com', phone: '+250 790 345 678', location: 'Kigali, Rwanda', galleries: 3, lastContact: '2024-01-14' },
    { id: 4, name: 'Fashion Brand', email: 'contact@fashionbrand.com', phone: '+250 791 456 789', location: 'Gisenyi, Rwanda', galleries: 2, lastContact: '2024-01-11' },
    { id: 5, name: 'Corporate Events Ltd', email: 'bookings@corporateevents.com', phone: '+250 792 567 890', location: 'Musanze, Rwanda', galleries: 1, lastContact: '2024-01-10' }
  ])

  return (
    <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6 flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Clients</h1>
            <p className="text-muted-foreground mt-1">Manage your client relationships and track projects</p>
          </div>
          <button className="px-6 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors flex items-center gap-2 font-semibold">
            <Plus size={20} />
            Add Client
          </button>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Clients Grid */}
          <div className="grid md:grid-cols-2 gap-6">
            {clients.map((client) => (
              <div key={client.id} className="bg-card border border-border rounded-lg p-6 hover:border-primary transition-colors">
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <h3 className="text-lg font-bold text-foreground">{client.name}</h3>
                    <p className="text-sm text-muted-foreground mt-1">{client.galleries} galleries</p>
                  </div>
                  <button className="p-2 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors">
                    <MoreHorizontal size={20} />
                  </button>
                </div>

                <div className="space-y-3 mb-6">
                  <div className="flex items-center gap-3 text-muted-foreground">
                    <Mail size={16} />
                    <span className="text-sm">{client.email}</span>
                  </div>
                  <div className="flex items-center gap-3 text-muted-foreground">
                    <Phone size={16} />
                    <span className="text-sm">{client.phone}</span>
                  </div>
                  <div className="flex items-center gap-3 text-muted-foreground">
                    <MapPin size={16} />
                    <span className="text-sm">{client.location}</span>
                  </div>
                </div>

                <div className="border-t border-border pt-4 text-xs text-muted-foreground mb-4">
                  Last contact: {client.lastContact}
                </div>

                <div className="flex gap-3">
                  <button className="flex-1 py-2 px-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm">
                    View Projects
                  </button>
                  <button className="flex-1 py-2 px-3 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm">
                    Send Message
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
    </main>
  )
}
