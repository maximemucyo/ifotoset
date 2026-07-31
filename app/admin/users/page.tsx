'use client'

import { Search, Filter, Ban, CheckCircle, Eye } from 'lucide-react'
import { useState } from 'react'
import Link from 'next/link'

export default function UserManagement() {
  const [searchTerm, setSearchTerm] = useState('')
  const [users] = useState([
    { id: 1, name: 'Sarah Photography Studio', email: 'sarah@example.com', joined: '2024-01-15', galleries: 12, status: 'Active', subscription: 'Professional', revenue: 85000 },
    { id: 2, name: 'John Studio', email: 'john@example.com', joined: '2024-01-14', galleries: 8, status: 'Active', subscription: 'Professional', revenue: 45000 },
    { id: 3, name: 'Emma Portraits', email: 'emma@example.com', joined: '2024-01-13', galleries: 3, status: 'Pending', subscription: 'Starter', revenue: 0 },
    { id: 4, name: 'Tech Events Photos', email: 'tech@example.com', joined: '2024-01-12', galleries: 15, status: 'Active', subscription: 'Studio', revenue: 125000 },
    { id: 5, name: 'Fashion Brand Studio', email: 'fashion@example.com', joined: '2024-01-11', galleries: 6, status: 'Suspended', subscription: 'Professional', revenue: 35000 },
  ])

  return (
    <div className="flex min-h-screen bg-background">
      

      <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6">
          <h1 className="text-3xl font-bold text-foreground">User Management</h1>
          <p className="text-muted-foreground mt-1">Monitor and manage photographer accounts</p>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Search and Filter */}
          <div className="flex flex-col sm:flex-row gap-4 mb-6">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" size={20} />
              <input
                type="text"
                placeholder="Search users..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-4 py-2 bg-card border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
              />
            </div>
            <button className="px-4 py-2 bg-card border border-border rounded-lg hover:border-primary transition-colors flex items-center gap-2 text-foreground font-semibold">
              <Filter size={20} />
              Filter
            </button>
          </div>

          {/* Users Table */}
          <div className="bg-card border border-border rounded-lg overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-secondary/50">
                  <tr>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Studio Name</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Email</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Joined</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Galleries</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Plan</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Revenue</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Status</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {users.map((user) => (
                    <tr key={user.id} className="border-t border-border hover:bg-secondary/30 transition-colors">
                      <td className="py-4 px-4 text-foreground font-medium">{user.name}</td>
                      <td className="py-4 px-4 text-muted-foreground text-sm">{user.email}</td>
                      <td className="py-4 px-4 text-muted-foreground text-sm">{user.joined}</td>
                      <td className="py-4 px-4 text-foreground font-medium">{user.galleries}</td>
                      <td className="py-4 px-4 text-foreground font-medium text-sm">{user.subscription}</td>
                      <td className="py-4 px-4 text-primary font-semibold">RWF {user.revenue.toLocaleString()}</td>
                      <td className="py-4 px-4">
                        <span className={`text-xs font-semibold px-3 py-1 rounded-full ${
                          user.status === 'Active'
                            ? 'bg-green-100 text-green-700'
                            : user.status === 'Pending'
                            ? 'bg-yellow-100 text-yellow-700'
                            : 'bg-red-100 text-red-700'
                        }`}>
                          {user.status}
                        </span>
                      </td>
                      <td className="py-4 px-4">
                        <div className="flex gap-2">
                          <Link href={`/admin/users/${user.id}`} className="p-2 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors">
                            <Eye size={16} />
                          </Link>
                          {user.status === 'Active' && (
                            <button className="p-2 hover:bg-red-100 rounded-lg text-muted-foreground hover:text-red-700 transition-colors">
                              <Ban size={16} />
                            </button>
                          )}
                        </div>
                      </td>
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
