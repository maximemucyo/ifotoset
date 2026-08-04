'use client'

import { BarChart3, Users, ImagePlus, TrendingUp, Download, Heart } from 'lucide-react'
import Link from 'next/link'
import { useCurrentUser } from '@/lib/queries/auth'
import { useDashboardStats } from '@/lib/queries/dashboard'
import { formatBytes } from '@/lib/utils'

export default function StudioDashboard() {
  const { data: currentUser } = useCurrentUser()
  const { data: dashboardData, isLoading } = useDashboardStats()

  const stats = dashboardData?.stats
  const recentGalleries = dashboardData?.recent_galleries || []

  const statsCards = [
    { label: 'Active Galleries', value: stats?.active_galleries ?? 0, icon: ImagePlus, subtext: 'Galleries created' },
    { label: 'Total Downloads', value: stats?.total_downloads ?? 0, icon: Download, subtext: 'All-time files delivered' },
    { label: 'Total Favorites', value: stats?.total_favorites ?? 0, icon: Heart, subtext: 'Client selections' },
    { label: 'Storage Used', value: formatBytes(stats?.storage_used_bytes ?? 0), icon: TrendingUp, subtext: 'Out of plan capacity' }
  ]

  return (
    <main className="flex-1 min-h-screen bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-6">
        <h1 className="text-3xl font-bold text-foreground">Welcome back, {currentUser?.user?.name || 'Photographer'}! 👋</h1>
        <p className="text-muted-foreground mt-2">Here&apos;s what&apos;s happening with your photography business today.</p>
      </div>

      {/* Content */}
      <div className="p-6">
        {/* Stats Grid */}
        {isLoading ? (
          <div className="grid md:grid-cols-4 gap-6 mb-8">
            {[1, 2, 3, 4].map((idx) => (
              <div key={idx} className="bg-card border border-border rounded-lg p-6 animate-pulse">
                <div className="h-4 bg-muted w-2/3 mb-2 rounded" />
                <div className="h-8 bg-muted w-1/3 mb-4 rounded" />
                <div className="h-3 bg-muted w-1/2 rounded" />
              </div>
            ))}
          </div>
        ) : (
          <div className="grid md:grid-cols-4 gap-6 mb-8">
            {statsCards.map((stat, idx) => {
              const Icon = stat.icon
              return (
                <div key={idx} className="bg-card border border-border rounded-lg p-6 hover:border-primary transition-colors">
                  <div className="flex items-start justify-between mb-4">
                    <div>
                      <p className="text-sm text-muted-foreground">{stat.label}</p>
                      <h3 className="text-2xl font-bold text-foreground mt-1">{stat.value}</h3>
                    </div>
                    <Icon className="w-8 h-8 text-primary opacity-50" />
                  </div>
                  <p className="text-xs text-accent">{stat.subtext}</p>
                </div>
              )
            })}
          </div>
        )}

        {/* Recent Galleries */}
        <div className="bg-card border border-border rounded-lg p-6 mb-8">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-xl font-bold text-foreground">Recent Galleries</h2>
            <Link href="/studio/galleries" className="text-primary hover:text-accent text-sm font-semibold">
              View All →
            </Link>
          </div>

          {isLoading ? (
            <div className="space-y-3">
              {[1, 2, 3].map((i) => (
                <div key={i} className="h-12 bg-muted rounded animate-pulse" />
              ))}
            </div>
          ) : recentGalleries.length === 0 ? (
            <div className="text-center py-6">
              <p className="text-muted-foreground">No galleries created yet.</p>
              <Link href="/studio/galleries/new" className="text-primary hover:underline text-sm font-medium mt-2 inline-block">
                Create your first gallery now
              </Link>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-border">
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Gallery Name</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Date</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Photos</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Status</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {recentGalleries.map((gallery) => (
                    <tr key={gallery.uuid} className="border-b border-border hover:bg-secondary/30 transition-colors">
                      <td className="py-4 px-4 text-foreground font-medium">{gallery.title}</td>
                      <td className="py-4 px-4 text-muted-foreground text-sm">
                        {gallery.event_date ? new Date(gallery.event_date).toLocaleDateString() : 'N/A'}
                      </td>
                      <td className="py-4 px-4 text-foreground font-medium">{gallery.stats.photo_count}</td>
                      <td className="py-4 px-4">
                        <span className={`text-xs font-semibold px-3 py-1 rounded-full ${
                          gallery.visibility === 'public'
                            ? 'bg-green-100 dark:bg-green-500/10 text-green-700 dark:text-green-500'
                            : 'bg-yellow-100 dark:bg-yellow-500/10 text-yellow-700 dark:text-yellow-500'
                        }`}>
                          {gallery.visibility}
                        </span>
                      </td>
                      <td className="py-4 px-4">
                        <Link href={`/studio/galleries/${gallery.uuid}`} className="text-primary hover:text-accent text-sm font-semibold">
                          View
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
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
