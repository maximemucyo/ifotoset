'use client'

import { Users, BarChart3, AlertCircle, DollarSign, TrendingUp } from 'lucide-react'
import Link from 'next/link'

export default function AdminDashboard() {
  const stats = [
    { label: 'Total Users', value: '2,847', icon: Users, trend: '+12% this month' },
    { label: 'Active Galleries', value: '1,256', icon: BarChart3, trend: '+45 this week' },
    { label: 'Pending Reviews', value: '23', icon: AlertCircle, trend: '3 urgent', color: 'text-red-600' },
    { label: 'Total Revenue', value: 'RWF 4.2M', icon: DollarSign, trend: '+28% growth' }
  ]

  const recentUsers = [
    { id: 1, name: 'Sarah Photography Studio', email: 'sarah@example.com', joined: '2024-01-15', status: 'Active', galleries: 12 },
    { id: 2, name: 'John Studio', email: 'john@example.com', joined: '2024-01-14', status: 'Active', galleries: 8 },
    { id: 3, name: 'Emma Portraits', email: 'emma@example.com', joined: '2024-01-13', status: 'Pending', galleries: 0 },
    { id: 4, name: 'Tech Events Photos', email: 'tech@example.com', joined: '2024-01-12', status: 'Active', galleries: 15 }
  ]

  const recentGalleries = [
    { id: 1, title: 'Wedding - Sarah & John', artist: 'Sarah Photography', views: 2451, images: 245, status: 'Published' },
    { id: 2, title: 'Corporate Event', artist: 'John Studio', views: 1893, images: 128, status: 'Pending' },
    { id: 3, title: 'Fashion Shoot', artist: 'Emma Portraits', views: 1456, images: 89, status: 'Flagged' },
  ]

  return (
    <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6">
          <h1 className="text-3xl font-bold text-foreground">Admin Dashboard</h1>
          <p className="text-muted-foreground mt-2">Platform overview and system status</p>
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
                      <h3 className={`text-2xl font-bold mt-1 ${stat.color || 'text-foreground'}`}>{stat.value}</h3>
                    </div>
                    <Icon className="w-8 h-8 text-primary opacity-50" />
                  </div>
                  <p className="text-xs text-accent">{stat.trend}</p>
                </div>
              )
            })}
          </div>

          {/* Recent Users */}
          <div className="bg-card border border-border rounded-lg p-6 mb-8">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-bold text-foreground">Recent Users</h2>
              <Link href="/admin/users" className="text-primary hover:text-accent text-sm font-semibold">
                View All →
              </Link>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-border">
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Studio Name</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Email</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Joined</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Galleries</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {recentUsers.map((user) => (
                    <tr key={user.id} className="border-b border-border hover:bg-secondary/30 transition-colors">
                      <td className="py-4 px-4 text-foreground font-medium">{user.name}</td>
                      <td className="py-4 px-4 text-muted-foreground">{user.email}</td>
                      <td className="py-4 px-4 text-muted-foreground text-sm">{user.joined}</td>
                      <td className="py-4 px-4 text-foreground font-medium">{user.galleries}</td>
                      <td className="py-4 px-4">
                        <span className={`text-xs font-semibold px-3 py-1 rounded-full ${
                          user.status === 'Active'
                            ? 'bg-green-100 text-green-700'
                            : 'bg-yellow-100 text-yellow-700'
                        }`}>
                          {user.status}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Recent Galleries & System Alerts */}
          <div className="grid md:grid-cols-3 gap-6">
            {/* Recent Galleries */}
            <div className="md:col-span-2 bg-card border border-border rounded-lg p-6">
              <div className="flex items-center justify-between mb-6">
                <h2 className="text-xl font-bold text-foreground">Recent Galleries</h2>
                <Link href="/admin/galleries" className="text-primary hover:text-accent text-sm font-semibold">
                  View All →
                </Link>
              </div>

              <div className="space-y-4">
                {recentGalleries.map((gallery) => (
                  <div key={gallery.id} className="p-4 bg-secondary/30 rounded-lg border border-border">
                    <div className="flex items-start justify-between mb-2">
                      <div>
                        <p className="font-semibold text-foreground">{gallery.title}</p>
                        <p className="text-sm text-muted-foreground">{gallery.artist}</p>
                      </div>
                      <span className={`text-xs font-semibold px-3 py-1 rounded-full ${
                        gallery.status === 'Published'
                          ? 'bg-green-100 text-green-700'
                          : gallery.status === 'Pending'
                          ? 'bg-yellow-100 text-yellow-700'
                          : 'bg-red-100 text-red-700'
                      }`}>
                        {gallery.status}
                      </span>
                    </div>
                    <div className="flex gap-6 text-sm text-muted-foreground">
                      <span>{gallery.views} views</span>
                      <span>{gallery.images} images</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* System Alerts */}
            <div className="bg-card border border-border rounded-lg p-6">
              <h2 className="text-lg font-bold text-foreground mb-4">System Alerts</h2>
              <div className="space-y-3">
                <div className="p-3 bg-red-100 border border-red-300 rounded-lg">
                  <p className="text-sm font-semibold text-red-700">3 Flagged Galleries</p>
                  <p className="text-xs text-red-600 mt-1">Require moderation review</p>
                </div>
                <div className="p-3 bg-yellow-100 border border-yellow-300 rounded-lg">
                  <p className="text-sm font-semibold text-yellow-700">5 Pending Approvals</p>
                  <p className="text-xs text-yellow-600 mt-1">New user accounts</p>
                </div>
                <div className="p-3 bg-blue-100 border border-blue-300 rounded-lg">
                  <p className="text-sm font-semibold text-blue-700">2 Support Tickets</p>
                  <p className="text-xs text-blue-600 mt-1">Urgent support requests</p>
                </div>
              </div>
              <button className="w-full mt-4 px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm">
                View All Alerts
              </button>
            </div>
          </div>
        </div>
    </main>
  )
}
