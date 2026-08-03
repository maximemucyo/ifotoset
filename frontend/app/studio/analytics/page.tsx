'use client'

import { BarChart3, TrendingUp, Users, Download, Eye, Share2 } from 'lucide-react'

export default function Analytics() {
  const metrics = [
    { label: 'Total Views', value: '12,451', change: '+23%', icon: Eye },
    { label: 'Total Downloads', value: '3,245', change: '+15%', icon: Download },
    { label: 'Unique Visitors', value: '2,891', change: '+18%', icon: Users },
    { label: 'Gallery Shares', value: '567', change: '+12%', icon: Share2 }
  ]

  const topGalleries = [
    { name: 'Wedding - Sarah & John', views: 2451, downloads: 245, revenue: 375000 },
    { name: 'Event Photography', views: 1893, downloads: 128, revenue: 175000 },
    { name: 'Portrait Session', views: 1456, downloads: 89, revenue: 75000 },
    { name: 'Pre-wedding Shoot', views: 1234, downloads: 156, revenue: 125000 }
  ]

  return (
    <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6">
          <h1 className="text-3xl font-bold text-foreground">Analytics</h1>
          <p className="text-muted-foreground mt-1">Track your gallery performance and engagement</p>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Date Range Filter */}
          <div className="flex gap-4 mb-6">
            <button className="px-4 py-2 bg-card border border-border rounded-lg hover:border-primary transition-colors text-foreground font-semibold text-sm">
              Last 7 Days
            </button>
            <button className="px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm">
              Last 30 Days
            </button>
            <button className="px-4 py-2 bg-card border border-border rounded-lg hover:border-primary transition-colors text-foreground font-semibold text-sm">
              Last 90 Days
            </button>
          </div>

          {/* Key Metrics */}
          <div className="grid md:grid-cols-4 gap-6 mb-8">
            {metrics.map((metric, idx) => {
              const Icon = metric.icon
              return (
                <div key={idx} className="bg-card border border-border rounded-lg p-6">
                  <div className="flex items-start justify-between mb-4">
                    <div>
                      <p className="text-sm text-muted-foreground">{metric.label}</p>
                      <h3 className="text-2xl font-bold text-foreground mt-1">{metric.value}</h3>
                    </div>
                    <Icon className="w-8 h-8 text-primary opacity-50" />
                  </div>
                  <p className="text-xs text-green-600 font-semibold">{metric.change} from last month</p>
                </div>
              )
            })}
          </div>

          {/* Charts Section */}
          <div className="grid md:grid-cols-2 gap-6 mb-8">
            {/* Views Over Time */}
            <div className="bg-card border border-border rounded-lg p-6">
              <h3 className="text-lg font-bold text-foreground mb-6">Gallery Views Over Time</h3>
              <div className="h-64 flex items-end gap-2 justify-between px-2">
                {[45, 52, 48, 65, 78, 92, 88, 95, 87, 102, 98, 115].map((height, idx) => (
                  <div
                    key={idx}
                    className="flex-1 bg-gradient-to-t from-primary to-accent rounded-t-lg opacity-70 hover:opacity-100 transition-opacity"
                    style={{ height: `${(height / 115) * 100}%` }}
                  />
                ))}
              </div>
              <p className="text-xs text-muted-foreground text-center mt-4">Last 12 months</p>
            </div>

            {/* Download Sources */}
            <div className="bg-card border border-border rounded-lg p-6">
              <h3 className="text-lg font-bold text-foreground mb-6">Downloads by Source</h3>
              <div className="space-y-4">
                {[
                  { label: 'Direct Links', value: 1245, percent: 65 },
                  { label: 'Email Shares', value: 456, percent: 24 },
                  { label: 'Social Media', value: 234, percent: 11 }
                ].map((item, idx) => (
                  <div key={idx}>
                    <div className="flex justify-between mb-2">
                      <span className="text-sm font-semibold text-foreground">{item.label}</span>
                      <span className="text-sm text-muted-foreground">{item.value} ({item.percent}%)</span>
                    </div>
                    <div className="w-full bg-secondary rounded-full h-2">
                      <div
                        className="bg-gradient-to-r from-primary to-accent h-2 rounded-full"
                        style={{ width: `${item.percent}%` }}
                      />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Top Galleries */}
          <div className="bg-card border border-border rounded-lg p-6">
            <h3 className="text-lg font-bold text-foreground mb-6">Top Performing Galleries</h3>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-border">
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Gallery Name</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Views</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Downloads</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Revenue</th>
                  </tr>
                </thead>
                <tbody>
                  {topGalleries.map((gallery, idx) => (
                    <tr key={idx} className="border-b border-border hover:bg-secondary/30 transition-colors">
                      <td className="py-4 px-4 text-foreground font-medium">{gallery.name}</td>
                      <td className="py-4 px-4 text-foreground">{gallery.views.toLocaleString()}</td>
                      <td className="py-4 px-4 text-foreground">{gallery.downloads.toLocaleString()}</td>
                      <td className="py-4 px-4 font-semibold text-primary">RWF {gallery.revenue.toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
    </main>
  )
}
