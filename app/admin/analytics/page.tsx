'use client'

import { BarChart3, Users, TrendingUp, Download } from 'lucide-react'

export default function AdminAnalytics() {
  return (
    <div className="flex min-h-screen bg-background">
      

      <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6">
          <h1 className="text-3xl font-bold text-foreground">Platform Analytics</h1>
          <p className="text-muted-foreground mt-1">Overview of platform performance and growth metrics</p>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Date Range */}
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
            <button className="px-4 py-2 bg-card border border-border rounded-lg hover:border-primary transition-colors text-foreground font-semibold text-sm">
              Year to Date
            </button>
          </div>

          {/* Key Metrics */}
          <div className="grid md:grid-cols-4 gap-6 mb-8">
            {[
              { label: 'Total Signups', value: '2,847', change: '+12% vs last month', icon: Users },
              { label: 'Active Studios', value: '1,256', change: '+3.2%', icon: TrendingUp },
              { label: 'Total Galleries', value: '8,942', change: '+18.5%', icon: BarChart3 },
              { label: 'Total Downloads', value: '145K', change: '+24.3%', icon: Download }
            ].map((metric, idx) => {
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
                  <p className="text-xs text-green-600 font-semibold">{metric.change}</p>
                </div>
              )
            })}
          </div>

          {/* Charts */}
          <div className="grid md:grid-cols-2 gap-6 mb-8">
            {/* Growth Chart */}
            <div className="bg-card border border-border rounded-lg p-6">
              <h3 className="text-lg font-bold text-foreground mb-6">User Growth</h3>
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

            {/* Gallery Distribution */}
            <div className="bg-card border border-border rounded-lg p-6">
              <h3 className="text-lg font-bold text-foreground mb-6">Gallery Types</h3>
              <div className="space-y-4">
                {[
                  { label: 'Wedding', value: 2847, percent: 32 },
                  { label: 'Events', value: 2145, percent: 24 },
                  { label: 'Portraits', value: 1892, percent: 21 },
                  { label: 'Other', value: 2058, percent: 23 }
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

          {/* Top Studios */}
          <div className="bg-card border border-border rounded-lg p-6">
            <h3 className="text-lg font-bold text-foreground mb-6">Top Performing Studios</h3>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-border">
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Studio Name</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Galleries</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Views</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Revenue</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Member Since</th>
                  </tr>
                </thead>
                <tbody>
                  {[
                    { name: 'Sarah Photography Studio', galleries: 12, views: 2451, revenue: 85000, date: '2023-06-15' },
                    { name: 'Tech Events Photos', galleries: 15, views: 3124, revenue: 125000, date: '2023-08-20' },
                    { name: 'Emma Portraits Studio', galleries: 8, views: 1892, revenue: 45000, date: '2023-10-12' },
                    { name: 'John Wedding Photography', galleries: 10, views: 2756, revenue: 95000, date: '2023-09-05' },
                  ].map((studio, idx) => (
                    <tr key={idx} className="border-b border-border hover:bg-secondary/30 transition-colors">
                      <td className="py-4 px-4 text-foreground font-medium">{studio.name}</td>
                      <td className="py-4 px-4 text-foreground">{studio.galleries}</td>
                      <td className="py-4 px-4 text-foreground">{studio.views.toLocaleString()}</td>
                      <td className="py-4 px-4 font-semibold text-primary">KES {studio.revenue.toLocaleString()}</td>
                      <td className="py-4 px-4 text-muted-foreground text-sm">{studio.date}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}
