'use client'

import { BarChart3, Users, ImagePlus, TrendingUp } from 'lucide-react'
import Link from 'next/link'

export default function StudioDashboard() {
  // Mock data
  const stats = [
    { label: 'Active Galleries', value: 12, icon: ImagePlus, trend: '+2 this month' },
    { label: 'Total Clients', value: 48, icon: Users, trend: '+5 new' },
    { label: 'Gallery Views', value: '2,341', icon: BarChart3, trend: '+15% growth' },
    { label: 'Total Earnings', value: 'RWF 627,000', icon: TrendingUp, trend: 'This month' }
  ]

  const recentGalleries = [
    { id: 1, name: 'Wedding - Sarah & John', date: '2024-01-15', views: 245, status: 'Published' },
    { id: 2, name: 'Corporate Event - Tech Summit', date: '2024-01-14', views: 128, status: 'Published' },
    { id: 3, name: 'Portrait Session - January', date: '2024-01-13', views: 89, status: 'Draft' },
    { id: 4, name: 'Engagement - Emma & David', date: '2024-01-12', views: 412, status: 'Published' }
  ]

  return (
    <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6">
          <h1 className="text-3xl font-bold text-foreground">Welcome back, Sarah! 👋</h1>
          <p className="text-muted-foreground mt-2">Here&apos;s what&apos;s happening with your photography business today.</p>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Stats Grid */}
          <div className="grid md:grid-cols-4 gap-6 mb-8">
            {stats.map((stat, idx) => {
              const Icon = stat.icon
              return (
                <div key={idx} className="bg-card border border-border rounded-lg p-6">
                  <div className="flex items-start justify-between mb-4">
                    <div>
                      <p className="text-sm text-muted-foreground">{stat.label}</p>
                      <h3 className="text-2xl font-bold text-foreground mt-1">{stat.value}</h3>
                    </div>
                    <Icon className="w-8 h-8 text-primary opacity-50" />
                  </div>
                  <p className="text-xs text-accent">{stat.trend}</p>
                </div>
              )
            })}
          </div>

          {/* Recent Galleries */}
          <div className="bg-card border border-border rounded-lg p-6 mb-8">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-bold text-foreground">Recent Galleries</h2>
              <Link href="/studio/galleries" className="text-primary hover:text-accent text-sm font-semibold">
                View All →
              </Link>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-border">
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Gallery Name</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Date</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Views</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Status</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {recentGalleries.map((gallery) => (
                    <tr key={gallery.id} className="border-b border-border hover:bg-secondary/30 transition-colors">
                      <td className="py-4 px-4 text-foreground font-medium">{gallery.name}</td>
                      <td className="py-4 px-4 text-muted-foreground text-sm">{gallery.date}</td>
                      <td className="py-4 px-4 text-foreground font-medium">{gallery.views}</td>
                      <td className="py-4 px-4">
                        <span className={`text-xs font-semibold px-3 py-1 rounded-full ${
                          gallery.status === 'Published'
                            ? 'bg-green-100 text-green-700'
                            : 'bg-yellow-100 text-yellow-700'
                        }`}>
                          {gallery.status}
                        </span>
                      </td>
                      <td className="py-4 px-4">
                        <Link href={`/studio/galleries/${gallery.id}`} className="text-primary hover:text-accent text-sm font-semibold">
                          View
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Quick Actions */}
          <div className="grid md:grid-cols-3 gap-6">
            <Link href="/studio/galleries/new" className="bg-primary text-primary-foreground rounded-lg p-6 hover:bg-accent transition-colors text-center">
              <ImagePlus className="w-8 h-8 mx-auto mb-2" />
              <h3 className="font-bold">Create Gallery</h3>
              <p className="text-sm text-primary-foreground/80 mt-1">Upload your latest photos</p>
            </Link>
            <Link href="/studio/packages" className="bg-card border border-border rounded-lg p-6 hover:border-primary transition-colors text-center">
              <ImagePlus className="w-8 h-8 mx-auto mb-2 text-primary" />
              <h3 className="font-bold text-foreground">Manage Packages</h3>
              <p className="text-sm text-muted-foreground mt-1">Update your pricing</p>
            </Link>
            <Link href="/studio/settings" className="bg-card border border-border rounded-lg p-6 hover:border-primary transition-colors text-center">
              <ImagePlus className="w-8 h-8 mx-auto mb-2 text-primary" />
              <h3 className="font-bold text-foreground">Studio Settings</h3>
              <p className="text-sm text-muted-foreground mt-1">Customize your profile</p>
            </Link>
          </div>
        </div>
    </main>
  )
}
