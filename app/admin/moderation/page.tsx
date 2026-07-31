'use client'

import { CheckCircle, XCircle, AlertTriangle, Eye } from 'lucide-react'
import { useState } from 'react'

export default function Moderation() {
  const [flaggedGalleries] = useState([
    { id: 1, title: 'Fashion Photoshoot', artist: 'Emma Portraits', images: 89, flaggedDate: '2024-01-15', reason: 'Inappropriate content', severity: 'High' },
    { id: 2, title: 'Beach Photos', artist: 'Sarah Photography', images: 156, flaggedDate: '2024-01-14', reason: 'Copyright concern', severity: 'Medium' },
    { id: 3, title: 'Product Photography', artist: 'John Studio', images: 234, flaggedDate: '2024-01-13', reason: 'Spam/Duplicate', severity: 'Low' },
  ])

  const [pendingGalleries] = useState([
    { id: 1, title: 'Wedding - Sarah & John', artist: 'Sarah Photography', images: 245, submittedDate: '2024-01-15' },
    { id: 2, title: 'Corporate Event', artist: 'Tech Events', images: 128, submittedDate: '2024-01-14' },
    { id: 3, title: 'Portrait Series', artist: 'Emma Portraits', images: 45, submittedDate: '2024-01-13' },
  ])

  return (
    <div className="flex min-h-screen bg-background">
      

      <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6">
          <h1 className="text-3xl font-bold text-foreground">Content Moderation</h1>
          <p className="text-muted-foreground mt-1">Review flagged content and approve new galleries</p>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Tabs */}
          <div className="flex gap-4 mb-6 border-b border-border">
            <button className="pb-3 px-4 border-b-2 border-primary text-primary font-semibold">
              Flagged Content
            </button>
            <button className="pb-3 px-4 text-muted-foreground hover:text-foreground font-semibold">
              Pending Approval
            </button>
          </div>

          {/* Flagged Galleries */}
          <div className="space-y-4 mb-12">
            <div className="text-sm text-muted-foreground mb-4">
              {flaggedGalleries.length} galleries flagged for review
            </div>

            {flaggedGalleries.map((gallery) => (
              <div key={gallery.id} className="bg-card border border-border rounded-lg p-6">
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <h3 className="text-lg font-bold text-foreground">{gallery.title}</h3>
                    <p className="text-sm text-muted-foreground">by {gallery.artist}</p>
                  </div>
                  <div className={`flex items-center gap-2 px-3 py-1 rounded-full text-sm font-semibold ${
                    gallery.severity === 'High'
                      ? 'bg-red-100 text-red-700'
                      : gallery.severity === 'Medium'
                      ? 'bg-yellow-100 text-yellow-700'
                      : 'bg-blue-100 text-blue-700'
                  }`}>
                    <AlertTriangle size={16} />
                    {gallery.severity} Priority
                  </div>
                </div>

                <div className="mb-4 p-4 bg-secondary/30 rounded-lg border border-border">
                  <p className="text-sm text-foreground"><span className="font-semibold">Reason:</span> {gallery.reason}</p>
                  <p className="text-sm text-muted-foreground mt-2">Flagged on {gallery.flaggedDate} • {gallery.images} images</p>
                </div>

                <div className="flex gap-3">
                  <button className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm">
                    <Eye size={16} />
                    Review Gallery
                  </button>
                  <button className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors font-semibold text-sm">
                    <CheckCircle size={16} />
                    Approve
                  </button>
                  <button className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors font-semibold text-sm">
                    <XCircle size={16} />
                    Reject
                  </button>
                </div>
              </div>
            ))}
          </div>

          {/* Pending Approval */}
          <div>
            <h2 className="text-2xl font-bold text-foreground mb-4">Pending Approval</h2>
            <div className="space-y-4">
              {pendingGalleries.map((gallery) => (
                <div key={gallery.id} className="bg-card border border-border rounded-lg p-6">
                  <div className="mb-4">
                    <h3 className="text-lg font-bold text-foreground">{gallery.title}</h3>
                    <p className="text-sm text-muted-foreground">by {gallery.artist}</p>
                  </div>

                  <div className="text-sm text-muted-foreground mb-4">
                    Submitted on {gallery.submittedDate} • {gallery.images} images
                  </div>

                  <div className="flex gap-3">
                    <button className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm">
                      <Eye size={16} />
                      Preview
                    </button>
                    <button className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors font-semibold text-sm">
                      <CheckCircle size={16} />
                      Approve & Publish
                    </button>
                    <button className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors font-semibold text-sm">
                      <XCircle size={16} />
                      Reject
                    </button>
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
