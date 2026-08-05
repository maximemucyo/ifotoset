'use client'

import { Search, Filter, Ban, CheckCircle, Eye, RefreshCw } from 'lucide-react'
import { useState } from 'react'
import Link from 'next/link'
import { useAdminUsers } from '@/lib/queries/admin'
import { formatBytes } from '@/lib/utils'

export default function UserManagement() {
  const [searchTerm, setSearchTerm] = useState('')
  const [planFilter, setPlanFilter] = useState('')
  const [currentPage, setCurrentPage] = useState(1)

  const { data: usersData, isLoading } = useAdminUsers(searchTerm, planFilter, currentPage)

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchTerm(e.target.value)
    setCurrentPage(1)
  }

  const handlePlanChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    setPlanFilter(e.target.value)
    setCurrentPage(1)
  }

  return (
    <div className="flex-1 flex flex-col min-h-screen bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-6">
        <h1 className="text-3xl font-extrabold text-foreground tracking-tight">User Management</h1>
        <p className="text-muted-foreground mt-1 text-sm font-medium">Monitor and manage photographer accounts</p>
      </div>

      {/* Content */}
      <div className="p-6 space-y-6 max-w-[1400px] w-full mx-auto flex-1">
        {/* Search and Filter */}
        <div className="flex flex-col sm:flex-row gap-4">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground w-4 h-4" />
            <input
              type="text"
              placeholder="Search users by name or email..."
              value={searchTerm}
              onChange={handleSearchChange}
              className="w-full pl-10 pr-4 py-2.5 bg-card border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary text-sm transition-colors"
            />
          </div>
          
          <select
            value={planFilter}
            onChange={handlePlanChange}
            className="px-4 py-2.5 bg-card border border-border rounded-lg text-foreground focus:outline-none focus:border-primary text-sm font-semibold"
          >
            <option value="">All Subscription Plans</option>
            <option value="free">Starter (Free)</option>
            <option value="pro">Professional</option>
            <option value="business">Studio/Business</option>
          </select>
        </div>

        {/* Users Table */}
        <div className="bg-card border border-border rounded-xl overflow-hidden shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-secondary/50 border-b border-border">
                <tr className="text-muted-foreground text-xs font-bold uppercase tracking-wider">
                  <th className="py-4 px-6">Studio Name</th>
                  <th className="py-4 px-6">Email</th>
                  <th className="py-4 px-6">Joined Date</th>
                  <th className="py-4 px-6 text-center">Galleries</th>
                  <th className="py-4 px-6">Subscription Plan</th>
                  <th className="py-4 px-6">Storage Used</th>
                  <th className="py-4 px-6">Status</th>
                </tr>
              </thead>
              <tbody>
                {isLoading ? (
                  <tr>
                    <td colSpan={7} className="py-12 text-center text-muted-foreground font-medium">
                      <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-primary" />
                      Loading users list...
                    </td>
                  </tr>
                ) : !usersData?.data || usersData.data.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="py-12 text-center text-muted-foreground font-medium">
                      No registered photographers found.
                    </td>
                  </tr>
                ) : (
                  usersData.data.map((user) => (
                    <tr key={user.uuid} className="border-b border-border hover:bg-secondary/10 transition-colors">
                      <td className="py-4 px-6 text-foreground font-bold">{user.name}</td>
                      <td className="py-4 px-6 text-muted-foreground text-xs">{user.email}</td>
                      <td className="py-4 px-6 text-muted-foreground text-xs">
                        {new Date(user.created_at).toLocaleDateString()}
                      </td>
                      <td className="py-4 px-6 text-center font-extrabold">{user.galleries_count}</td>
                      <td className="py-4 px-6">
                        <span className="bg-secondary px-2.5 py-1 rounded text-muted-foreground text-xs font-bold capitalize">
                          {user.plan}
                        </span>
                      </td>
                      <td className="py-4 px-6 font-mono text-xs font-semibold text-foreground">
                        {formatBytes(user.storage_used_bytes)}
                      </td>
                      <td className="py-4 px-6">
                        <span className={`text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full ${
                          user.is_active
                            ? 'bg-green-100 text-green-800 dark:bg-green-950/30 dark:text-green-455'
                            : 'bg-red-100 text-red-800 dark:bg-red-950/30 dark:text-red-400'
                        }`}>
                          {user.is_active ? 'Active' : 'Suspended'}
                        </span>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination controls */}
          {usersData?.meta && usersData.meta.last_page > 1 && (
            <div className="bg-secondary/40 border-t border-border px-6 py-4 flex items-center justify-between">
              <div className="text-xs text-muted-foreground font-medium">
                Showing page {usersData.meta.current_page} of {usersData.meta.last_page} ({usersData.meta.total} total creators)
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                  disabled={currentPage === 1}
                  className="px-3.5 py-1.5 border border-border bg-card text-xs font-semibold rounded hover:bg-secondary transition-colors disabled:opacity-50"
                >
                  Previous
                </button>
                <button
                  onClick={() => setCurrentPage(prev => Math.min(prev + 1, usersData.meta?.last_page ?? 1))}
                  disabled={currentPage === usersData.meta.last_page}
                  className="px-3.5 py-1.5 border border-border bg-card text-xs font-semibold rounded hover:bg-secondary transition-colors disabled:opacity-50"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
