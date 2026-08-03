'use client'

import { Plus, Search, Filter, MoreHorizontal } from 'lucide-react'
import Link from 'next/link'
import { useState } from 'react'

export default function Galleries() {
  const [searchTerm, setSearchTerm] = useState('')

  const galleries = [
    { id: 1, name: 'Wedding - Sarah & John', date: '2024-01-15', images: 245, client: 'Sarah & John', size: '8.4 GB' },
    { id: 2, name: 'Corporate Event - Tech Summit', date: '2024-01-14', images: 128, client: 'Tech Company', size: '4.2 GB' },
    { id: 3, name: 'Portrait Session - January', date: '2024-01-13', images: 89, client: 'Various', size: '2.1 GB' },
    { id: 4, name: 'Engagement - Emma & David', date: '2024-01-12', images: 412, client: 'Emma & David', size: '12.8 GB' },
    { id: 5, name: 'Fashion Photoshoot', date: '2024-01-11', images: 256, client: 'Fashion Brand', size: '6.7 GB' }
  ]

  return (
    <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6 flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Galleries</h1>
            <p className="text-muted-foreground mt-1">Manage all your photo galleries and client deliveries</p>
          </div>
          <Link href="/studio/galleries/new" className="px-6 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors flex items-center gap-2 font-semibold">
            <Plus size={20} />
            New Gallery
          </Link>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Search and Filter */}
          <div className="flex flex-col sm:flex-row gap-4 mb-6">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" size={20} />
              <input
                type="text"
                placeholder="Search galleries..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-4 py-2 bg-card border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
              />
            </div>
            <button className="px-4 py-2 bg-card border border-border rounded-lg hover:border-primary transition-colors flex items-center gap-2 text-foreground">
              <Filter size={20} />
              Filter
            </button>
          </div>

          {/* Galleries Grid */}
          <div className="grid gap-6">
            {galleries.map((gallery) => (
              <div key={gallery.id} className="bg-card border border-border rounded-lg p-6 hover:border-primary transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <h3 className="text-lg font-bold text-foreground">{gallery.name}</h3>
                    <p className="text-sm text-muted-foreground mt-1">Created {gallery.date}</p>

                    <div className="flex flex-wrap gap-6 mt-4">
                      <div>
                        <p className="text-xs text-muted-foreground">Images</p>
                        <p className="text-lg font-semibold text-foreground">{gallery.images}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Client</p>
                        <p className="text-lg font-semibold text-foreground">{gallery.client}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Size</p>
                        <p className="text-lg font-semibold text-foreground">{gallery.size}</p>
                      </div>
                    </div>
                  </div>

                  <button className="p-2 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors">
                    <MoreHorizontal size={20} />
                  </button>
                </div>

                <div className="flex gap-3 mt-6 pt-6 border-t border-border">
                  <Link href={`/studio/galleries/${gallery.id}`} className="flex-1 py-2 px-4 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors text-center font-semibold text-sm">
                    View Gallery
                  </Link>
                  <button className="flex-1 py-2 px-4 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm">
                    Share Link
                  </button>
                  <button className="flex-1 py-2 px-4 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm">
                    Edit
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
    </main>
  )
}
