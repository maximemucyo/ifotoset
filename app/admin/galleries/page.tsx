'use client'

import { Star, Trash2, Eye } from 'lucide-react'
import { useState } from 'react'

export default function FeaturedGalleries() {
  const [featuredGalleries] = useState([
    { id: 1, title: 'Wedding - Sarah & John', artist: 'Sarah Photography', views: 2451, images: 245, featured: true, addedDate: '2024-01-15' },
    { id: 2, title: 'Corporate Event - Tech Summit', artist: 'Tech Events Photos', views: 1893, images: 128, featured: true, addedDate: '2024-01-14' },
    { id: 3, title: 'Portrait Series', artist: 'Emma Portraits', views: 1456, images: 89, featured: true, addedDate: '2024-01-13' },
  ])

  const [suggestedGalleries] = useState([
    { id: 4, title: 'Fashion Photoshoot 2024', artist: 'Fashion Brand', views: 892, images: 156 },
    { id: 5, title: 'Event Photography', artist: 'John Studio', views: 745, images: 102 },
    { id: 6, title: 'Nature & Landscape', artist: 'Adventure Shots', views: 634, images: 78 },
  ])

  return (
    <div className="flex min-h-screen bg-background">
      

      <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6">
          <h1 className="text-3xl font-bold text-foreground">Featured Galleries</h1>
          <p className="text-muted-foreground mt-1">Curate and manage galleries featured on the homepage</p>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Currently Featured */}
          <div className="mb-12">
            <h2 className="text-2xl font-bold text-foreground mb-6">Currently Featured</h2>
            <div className="space-y-4">
              {featuredGalleries.map((gallery) => (
                <div key={gallery.id} className="bg-card border-2 border-primary rounded-lg p-6">
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-2">
                        <Star className="w-5 h-5 text-primary fill-primary" />
                        <h3 className="text-lg font-bold text-foreground">{gallery.title}</h3>
                      </div>
                      <p className="text-sm text-muted-foreground">by {gallery.artist}</p>

                      <div className="flex gap-6 mt-4 text-sm">
                        <span className="text-muted-foreground">{gallery.views} views</span>
                        <span className="text-muted-foreground">{gallery.images} images</span>
                        <span className="text-muted-foreground">Featured since {gallery.addedDate}</span>
                      </div>
                    </div>

                    <div className="flex gap-2 ml-4">
                      <button className="p-2 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors">
                        <Eye size={20} />
                      </button>
                      <button className="p-2 hover:bg-red-100 rounded-lg text-muted-foreground hover:text-red-700 transition-colors">
                        <Trash2 size={20} />
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Suggested Galleries */}
          <div>
            <h2 className="text-2xl font-bold text-foreground mb-6">Suggested Galleries</h2>
            <div className="space-y-4">
              {suggestedGalleries.map((gallery) => (
                <div key={gallery.id} className="bg-card border border-border rounded-lg p-6 hover:border-primary transition-colors">
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <h3 className="text-lg font-bold text-foreground">{gallery.title}</h3>
                      <p className="text-sm text-muted-foreground mt-1">by {gallery.artist}</p>

                      <div className="flex gap-6 mt-4 text-sm">
                        <span className="text-muted-foreground">{gallery.views} views</span>
                        <span className="text-muted-foreground">{gallery.images} images</span>
                      </div>
                    </div>

                    <div className="flex gap-2 ml-4">
                      <button className="p-2 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors">
                        <Eye size={20} />
                      </button>
                      <button className="px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm">
                        <Star className="w-5 h-5 inline mr-2" />
                        Feature
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}
