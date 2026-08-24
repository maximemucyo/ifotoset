'use client'

import { useState } from 'react'
import { 
  Users, 
  BarChart3, 
  AlertCircle, 
  DollarSign, 
  Database, 
  Activity, 
  CheckCircle2, 
  XCircle, 
  Search, 
  RefreshCw, 
  Clock,
  ArrowRight,
  User,
  Image as ImageIcon,
  Mail
} from 'lucide-react'
import Link from 'next/link'
import { useAdminDashboard, useAdminQueue, useAdminExports } from '@/lib/queries/admin'
import { formatBytes } from '@/lib/utils'
import { ResponsiveTable } from '@/components/ui/responsive-table'
import { useEffect } from 'react'

export default function AdminDashboard() {
  const [activeTab, setActiveTab] = useState<'overview' | 'queue' | 'exports'>('overview')
  
  // Queue filters
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [searchFilter, setSearchFilter] = useState<string>('')
  const [currentPage, setCurrentPage] = useState<number>(1)
  
  const [exportsPage, setExportsPage] = useState<number>(1)
  const [hasActiveExports, setHasActiveExports] = useState(false)

  // Fetch exports monitor data
  const { data: exportsData, isLoading: isExportsLoading } = useAdminExports(exportsPage, hasActiveExports)

  useEffect(() => {
    if (exportsData?.data) {
      const active = exportsData.data.some(job => ['pending', 'processing'].includes(job.status))
      setHasActiveExports(active)
    }
  }, [exportsData])

  // 1. Initial poll check: does the dashboard see active jobs?
  const { data: dashboardData, isLoading: isDashLoading, error: dashError } = useAdminDashboard()
  
  const hasActiveJobs = 
    (dashboardData?.queue_stats?.queued ?? 0) > 0 || 
    (dashboardData?.queue_stats?.processing ?? 0) > 0

  // 2. Fetch the paginated queue jobs list using smart polling (2s if active, 30s if idle)
  const { data: queueData, isLoading: isQueueLoading } = useAdminQueue(
    statusFilter, 
    searchFilter, 
    currentPage, 
    hasActiveJobs
  )

  // Handlers
  const handleStatusSelect = (status: string) => {
    setStatusFilter(status)
    setCurrentPage(1)
  }

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchFilter(e.target.value)
    setCurrentPage(1)
  }

  // Format currency
  const formatRWF = (amount: number) => {
    return `RWF ${Math.round(amount).toLocaleString()}`
  }

  // Loading Screen
  if (isDashLoading) {
    return (
      <div className="flex-1 flex items-center justify-center min-h-[60vh]">
        <div className="flex flex-col items-center gap-4">
          <Activity className="w-12 h-12 text-[#dd7a53] animate-spin" />
          <p className="text-muted-foreground text-sm font-medium">Loading system metrics...</p>
        </div>
      </div>
    )
  }

  if (dashError) {
    return (
      <div className="flex-1 p-6">
        <div className="p-4 bg-red-100 border border-red-300 rounded-lg text-red-700">
          <h3 className="font-bold">System Connection Failure</h3>
          <p className="text-sm">Unable to connect to the administration APIs. Please check that the backend container is running and healthy.</p>
        </div>
      </div>
    )
  }

  const stats = dashboardData?.stats
  const queueStats = dashboardData?.queue_stats
  const recentUsers = dashboardData?.recent_users ?? []
  const recentGalleries = dashboardData?.recent_galleries ?? []

  // Core dashboard overview cards
  const statCards = [
    { label: 'Total Photographers', value: stats?.total_users ?? 0, icon: Users, description: 'Registered creators' },
    { label: 'Active Galleries', value: stats?.active_galleries ?? 0, icon: ImageIcon, description: 'Published on platform' },
    { label: 'Storage Consumed', value: formatBytes(stats?.total_storage_bytes ?? 0), icon: Database, description: 'Original source files' },
    { label: 'Accumulated Revenue', value: formatRWF(stats?.total_revenue ?? 0), icon: DollarSign, description: 'Successful momo deposits' }
  ]

  return (
    <main className="flex-1 bg-background text-foreground flex flex-col min-h-screen">
      {/* Header */}
      <div className="border-b border-border bg-card p-4 md:p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-foreground tracking-tight">Admin Console</h1>
          <p className="text-muted-foreground mt-1 text-xs md:text-sm font-medium flex items-center gap-1.5">
            <Activity className={`w-4 h-4 ${hasActiveJobs ? 'text-primary animate-pulse' : 'text-muted-foreground'}`} />
            System status: {hasActiveJobs ? 'Processing queue active' : 'Queue idle'}
          </p>
        </div>

        {/* Tab switcher */}
        <div className="flex bg-secondary rounded-lg p-1 border border-border shrink-0">
          <button
            onClick={() => setActiveTab('overview')}
            className={`px-4 py-2 text-xs font-semibold rounded-md transition-all ${
              activeTab === 'overview' 
                ? 'bg-card text-foreground shadow-sm' 
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            Overview
          </button>
          <button
            onClick={() => setActiveTab('queue')}
            className={`px-4 py-2 text-xs font-semibold rounded-md transition-all flex items-center gap-1.5 ${
              activeTab === 'queue' 
                ? 'bg-card text-foreground shadow-sm' 
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            Queue Monitor
            {hasActiveJobs && (
              <span className="w-2 h-2 rounded-full bg-primary animate-pulse" />
            )}
          </button>
          <button
            onClick={() => setActiveTab('exports')}
            className={`px-4 py-2 text-xs font-semibold rounded-md transition-all flex items-center gap-1.5 ${
              activeTab === 'exports' 
                ? 'bg-card text-foreground shadow-sm' 
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            Export Monitor
            {hasActiveExports && (
              <span className="w-2 h-2 rounded-full bg-primary animate-pulse" />
            )}
          </button>
        </div>
      </div>

      <div className="p-4 md:p-6 flex-1 space-y-6 md:space-y-8 max-w-[1400px] w-full mx-auto">
        {/* OVERVIEW TAB */}
        {activeTab === 'overview' && (
          <>
            {/* Stats Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
              {statCards.map((card, idx) => {
                const Icon = card.icon
                return (
                  <div key={idx} className="bg-card border border-border rounded-xl p-6 shadow-sm hover:shadow transition-shadow relative overflow-hidden group">
                    <div className="flex items-start justify-between">
                      <div className="space-y-1">
                        <p className="text-xs text-muted-foreground font-semibold uppercase tracking-wider">{card.label}</p>
                        <h3 className="text-2xl font-black text-foreground">{card.value}</h3>
                      </div>
                      <div className="p-3 bg-secondary/80 rounded-lg group-hover:bg-primary/10 group-hover:text-primary transition-colors text-muted-foreground">
                        <Icon className="w-6 h-6" />
                      </div>
                    </div>
                    <p className="text-xs text-muted-foreground mt-4 font-medium">{card.description}</p>
                  </div>
                )
              })}
            </div>

            {/* Quick Queue Widget */}
            <div className="bg-card border border-border rounded-xl p-4 md:p-6 shadow-sm space-y-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Activity className="w-5 h-5 text-primary" />
                  <h3 className="text-lg font-bold text-foreground">Image Processing Status</h3>
                </div>
                <button 
                  onClick={() => setActiveTab('queue')}
                  className="text-primary hover:text-accent text-xs font-bold flex items-center gap-1"
                >
                  Open Full Monitor <ArrowRight size={14} />
                </button>
              </div>

              <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                <div className="p-4 rounded-xl border border-border bg-secondary/30 text-center">
                  <p className="text-xs text-muted-foreground font-bold">Queued</p>
                  <p className="text-2xl font-extrabold text-foreground mt-1">{queueStats?.queued ?? 0}</p>
                </div>
                <div className={`p-4 rounded-xl border border-border bg-secondary/30 text-center ${queueStats?.processing ? 'border-primary/40 bg-primary/5' : ''}`}>
                  <p className="text-xs text-muted-foreground font-bold">Processing</p>
                  <p className={`text-2xl font-extrabold mt-1 ${queueStats?.processing ? 'text-primary animate-pulse' : 'text-foreground'}`}>
                    {queueStats?.processing ?? 0}
                  </p>
                </div>
                <div className="p-4 rounded-xl border border-border bg-secondary/30 text-center">
                  <p className="text-xs text-muted-foreground font-bold">Completed</p>
                  <p className="text-2xl font-extrabold text-green-600 mt-1">{queueStats?.completed ?? 0}</p>
                </div>
                <div className={`p-4 rounded-xl border border-border bg-secondary/30 text-center ${queueStats?.failed ? 'border-red-200 bg-red-50/50' : ''}`}>
                  <p className="text-xs text-muted-foreground font-bold">Failed</p>
                  <p className={`text-2xl font-extrabold mt-1 ${queueStats?.failed ? 'text-red-600' : 'text-foreground'}`}>{queueStats?.failed ?? 0}</p>
                </div>
              </div>
            </div>

            {/* Recent Users and Galleries Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8">
              {/* Users */}
              <div className="space-y-4 flex flex-col">
                <div className="flex items-center justify-between">
                  <h3 className="text-lg font-bold text-foreground">Recent Registered Studios</h3>
                  <Link href="/admin/users" className="text-primary hover:text-accent text-xs font-bold">
                    Manage Studios
                  </Link>
                </div>

                <ResponsiveTable
                  items={recentUsers}
                  emptyText="No registered studios"
                  desktopHeader={
                    <thead className="bg-secondary/40 border-b border-border">
                      <tr className="text-muted-foreground text-xs font-semibold">
                        <th className="py-3 px-4">Studio Name</th>
                        <th className="py-3 px-4">Email</th>
                        <th className="py-3 px-4">Plan</th>
                        <th className="py-3 px-4 text-right">Galleries</th>
                      </tr>
                    </thead>
                  }
                  renderDesktopRow={(user) => (
                    <tr key={user.uuid} className="border-b border-border/50 hover:bg-secondary/20 transition-colors text-foreground/90">
                      <td className="py-3.5 px-4 font-semibold text-xs sm:text-sm">{user.name}</td>
                      <td className="py-3.5 px-4 text-xs text-muted-foreground">{user.email}</td>
                      <td className="py-3.5 px-4 text-xs">
                        <span className="bg-secondary px-2 py-0.5 rounded text-muted-foreground font-semibold">
                          {user.plan}
                        </span>
                      </td>
                      <td className="py-3.5 px-4 font-bold text-xs text-right">{user.galleries_count}</td>
                    </tr>
                  )}
                  renderMobileCard={(user) => (
                    <div key={user.uuid} className="bg-card border border-border rounded-xl p-4 space-y-3 hover:border-primary/50 transition-colors shadow-sm">
                      <div className="flex justify-between items-start gap-4">
                        <div className="min-w-0">
                          <h4 className="font-bold text-foreground text-sm truncate">{user.name}</h4>
                          <p className="text-xs text-muted-foreground mt-0.5 truncate">{user.email}</p>
                        </div>
                        <span className="bg-secondary px-2 py-0.5 rounded text-[10px] text-muted-foreground font-semibold uppercase tracking-wider shrink-0">
                          {user.plan}
                        </span>
                      </div>
                      <div className="flex justify-between items-center text-xs pt-2 border-t border-border/50">
                        <span className="text-muted-foreground">Galleries count:</span>
                        <strong className="text-foreground font-semibold">{user.galleries_count}</strong>
                      </div>
                    </div>
                  )}
                />
              </div>

              {/* Galleries */}
              <div className="space-y-4 flex flex-col">
                <div className="flex items-center justify-between">
                  <h3 className="text-lg font-bold text-foreground">Recent Published Galleries</h3>
                  <span className="text-xs text-muted-foreground font-semibold">Latest updates</span>
                </div>

                <ResponsiveTable
                  items={recentGalleries}
                  emptyText="No published galleries"
                  desktopHeader={
                    <thead className="bg-secondary/40 border-b border-border">
                      <tr className="text-muted-foreground text-xs font-semibold">
                        <th className="py-3 px-4">Gallery Title</th>
                        <th className="py-3 px-4">Photographer</th>
                        <th className="py-3 px-4 text-center">Photos</th>
                        <th className="py-3 px-4 text-right">Visibility</th>
                      </tr>
                    </thead>
                  }
                  renderDesktopRow={(gallery) => (
                    <tr key={gallery.uuid} className="border-b border-border/50 hover:bg-secondary/20 transition-colors text-foreground/90">
                      <td className="py-3.5 px-4 font-semibold text-xs sm:text-sm">{gallery.title}</td>
                      <td className="py-3.5 px-4 text-xs text-muted-foreground">{gallery.owner}</td>
                      <td className="py-3.5 px-4 font-bold text-xs text-center">{gallery.images}</td>
                      <td className="py-3.5 px-4 text-right">
                        <span className={`text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ${
                          gallery.visibility === 'public' 
                            ? 'bg-green-100 text-green-700' 
                            : 'bg-yellow-100 text-yellow-700'
                        }`}>
                          {gallery.visibility}
                        </span>
                      </td>
                    </tr>
                  )}
                  renderMobileCard={(gallery) => (
                    <div key={gallery.uuid} className="bg-card border border-border rounded-xl p-4 space-y-3 hover:border-primary/50 transition-colors shadow-sm">
                      <div className="flex justify-between items-start gap-4">
                        <div className="min-w-0">
                          <h4 className="font-bold text-foreground text-sm truncate">{gallery.title}</h4>
                          <p className="text-xs text-muted-foreground mt-0.5 truncate">By {gallery.owner}</p>
                        </div>
                        <span className={`text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full shrink-0 ${
                          gallery.visibility === 'public' 
                            ? 'bg-green-100 text-green-700' 
                            : 'bg-yellow-100 text-yellow-700'
                        }`}>
                          {gallery.visibility}
                        </span>
                      </div>
                      <div className="flex justify-between items-center text-xs pt-2 border-t border-border/50">
                        <span className="text-muted-foreground">Photos:</span>
                        <strong className="text-foreground font-semibold">{gallery.images}</strong>
                      </div>
                    </div>
                  )}
                />
              </div>
            </div>
          </>
        )}

        {/* QUEUE MONITOR TAB */}
        {activeTab === 'queue' && (
          <div className="space-y-6">
            {/* Live Queue Metrics Summary */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <button 
                onClick={() => handleStatusSelect('')}
                className={`p-4 rounded-xl border text-center transition-all ${
                  statusFilter === '' 
                    ? 'border-foreground bg-secondary/80 ring-1 ring-foreground font-bold' 
                    : 'border-border bg-card hover:bg-secondary/20'
                }`}
              >
                <div className="flex items-center justify-center gap-1.5 text-muted-foreground">
                  <Activity className="w-4 h-4" />
                  <span className="text-xs font-bold">All Jobs</span>
                </div>
                <p className="text-2xl font-black mt-2">
                  {(queueStats?.queued ?? 0) + (queueStats?.processing ?? 0) + (queueStats?.completed ?? 0) + (queueStats?.failed ?? 0)}
                </p>
              </button>

              <button 
                onClick={() => handleStatusSelect('queued')}
                className={`p-4 rounded-xl border text-center transition-all ${
                  statusFilter === 'queued' 
                    ? 'border-gray-500 bg-gray-50 dark:bg-gray-900 ring-1 ring-gray-500 font-bold' 
                    : 'border-border bg-card hover:bg-secondary/20'
                }`}
              >
                <div className="flex items-center justify-center gap-1.5 text-gray-500">
                  <Clock className="w-4 h-4" />
                  <span className="text-xs font-bold">Queued</span>
                </div>
                <p className="text-2xl font-black text-gray-500 mt-2">{queueStats?.queued ?? 0}</p>
              </button>

              <button 
                onClick={() => handleStatusSelect('processing')}
                className={`p-4 rounded-xl border text-center transition-all ${
                  statusFilter === 'processing' 
                    ? 'border-primary bg-primary/5 ring-1 ring-primary font-bold' 
                    : 'border-border bg-card hover:bg-secondary/20'
                }`}
              >
                <div className="flex items-center justify-center gap-1.5 text-primary">
                  <RefreshCw className={`w-4 h-4 ${hasActiveJobs ? 'animate-spin' : ''}`} />
                  <span className="text-xs font-bold">Processing</span>
                </div>
                <p className="text-2xl font-black text-primary mt-2">{queueStats?.processing ?? 0}</p>
              </button>

              <button 
                onClick={() => handleStatusSelect('failed')}
                className={`p-4 rounded-xl border text-center transition-all ${
                  statusFilter === 'failed' 
                    ? 'border-red-600 bg-red-50/50 dark:bg-red-950/20 ring-1 ring-red-600 font-bold' 
                    : 'border-border bg-card hover:bg-secondary/20'
                }`}
              >
                <div className="flex items-center justify-center gap-1.5 text-red-600">
                  <XCircle className="w-4 h-4" />
                  <span className="text-xs font-bold">Failed</span>
                </div>
                <p className="text-2xl font-black text-red-600 mt-2">{queueStats?.failed ?? 0}</p>
              </button>
            </div>

            {/* Filter and Search controls */}
            <div className="flex flex-col sm:flex-row gap-4">
              <div className="flex-1 relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground w-4 h-4" />
                <input
                  type="text"
                  placeholder="Search jobs by filename, studio name, gallery title..."
                  value={searchFilter}
                  onChange={handleSearchChange}
                  className="w-full bg-card border border-border rounded-lg pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:border-primary transition-colors text-foreground placeholder-muted-foreground"
                />
              </div>
            </div>

            {/* Queue Table */}
            <div className="bg-card border border-border rounded-xl overflow-hidden shadow-sm">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-secondary/50 border-b border-border">
                    <tr className="text-muted-foreground text-xs font-bold uppercase tracking-wider">
                      <th className="py-4 px-6">Media Job</th>
                      <th className="py-4 px-6">Studio & Gallery</th>
                      <th className="py-4 px-6">Status</th>
                      <th className="py-4 px-6">Stage Progress</th>
                      <th className="py-4 px-6 text-center">Attempts</th>
                      <th className="py-4 px-6">Duration</th>
                      <th className="py-4 px-6">Timestamps</th>
                    </tr>
                  </thead>
                  <tbody>
                    {isQueueLoading ? (
                      <tr>
                        <td colSpan={7} className="py-12 text-center text-muted-foreground font-medium">
                          <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-primary" />
                          Loading queue data...
                        </td>
                      </tr>
                    ) : !queueData?.data || queueData.data.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="py-12 text-center text-muted-foreground font-medium">
                          No matching media processing jobs found.
                        </td>
                      </tr>
                    ) : (
                      queueData.data.map((job) => {
                        const statusClass = 
                          job.status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-950/30 dark:text-green-400' :
                          job.status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-950/30 dark:text-red-400' :
                          job.status === 'processing' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/30 dark:text-blue-400' :
                          'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-400';

                        return (
                          <tr key={job.id} className="border-b border-border hover:bg-secondary/10 transition-colors">
                            <td className="py-4 px-6">
                              <div className="font-bold text-foreground truncate max-w-[200px]" title={job.original_filename}>
                                {job.original_filename}
                              </div>
                              <div className="text-[10px] text-muted-foreground font-mono mt-0.5 truncate max-w-[200px]">
                                {job.photo_uuid || 'N/A'}
                              </div>
                            </td>
                            <td className="py-4 px-6">
                              <div className="font-semibold text-foreground truncate max-w-[180px]" title={job.gallery_title}>
                                {job.gallery_title}
                              </div>
                              <div className="text-xs text-muted-foreground truncate max-w-[180px]" title={job.studio_name}>
                                {job.studio_name}
                              </div>
                            </td>
                            <td className="py-4 px-6">
                              <span className={`text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full ${statusClass}`}>
                                {job.status}
                              </span>
                            </td>
                            <td className="py-4 px-6">
                              {job.status === 'failed' ? (
                                <div className="text-xs text-red-600 bg-red-50 dark:bg-red-950/10 p-2 rounded border border-red-200/50 max-w-[250px] overflow-hidden whitespace-normal break-words leading-snug">
                                  <strong>Error:</strong> {job.error_message || 'Processing execution failed.'}
                                </div>
                              ) : (
                                <div className="flex items-center gap-2">
                                  {job.status === 'processing' && (
                                    <span className="flex h-2 w-2 relative">
                                      <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                      <span className="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                                    </span>
                                  )}
                                  <span className="text-xs font-semibold text-foreground">
                                    {job.progress || (job.status === 'completed' ? 'Completed' : 'Queued')}
                                  </span>
                                </div>
                              )}
                            </td>
                            <td className="py-4 px-6 text-center text-xs font-mono font-bold">
                              {job.attempts} / {job.max_attempts}
                            </td>
                            <td className="py-4 px-6 font-mono text-xs text-foreground">
                              {job.duration_ms !== null ? (
                                job.duration_ms < 1000 ? `${job.duration_ms}ms` : `${(job.duration_ms / 1000).toFixed(2)}s`
                              ) : job.status === 'processing' && job.started_at ? (
                                <span className="text-muted-foreground italic flex items-center gap-1">
                                  <RefreshCw className="w-3 h-3 animate-spin text-primary" /> Active
                                </span>
                              ) : (
                                '—'
                              )}
                            </td>
                            <td className="py-4 px-6 text-xs text-muted-foreground space-y-1">
                              {job.started_at && (
                                <div className="flex items-center gap-1">
                                  <span className="font-semibold text-[9px] uppercase px-1 py-0.2 bg-secondary rounded text-muted-foreground">Start:</span>
                                  <span>{new Date(job.started_at).toLocaleTimeString()}</span>
                                </div>
                              )}
                              {job.completed_at && (
                                <div className="flex items-center gap-1">
                                  <span className="font-semibold text-[9px] uppercase px-1 py-0.2 bg-secondary rounded text-muted-foreground">End:</span>
                                  <span>{new Date(job.completed_at).toLocaleTimeString()}</span>
                                </div>
                              )}
                              {job.failed_at && (
                                <div className="flex items-center gap-1">
                                  <span className="font-semibold text-[9px] uppercase px-1 py-0.2 bg-red-100 text-red-700 dark:bg-red-950/20 rounded">Fail:</span>
                                  <span>{new Date(job.failed_at).toLocaleTimeString()}</span>
                                </div>
                              )}
                            </td>
                          </tr>
                        )
                      })
                    )}
                  </tbody>
                </table>
              </div>

              {/* Pagination controls */}
              {queueData?.meta && queueData.meta.last_page > 1 && (
                <div className="bg-secondary/40 border-t border-border px-6 py-4 flex items-center justify-between">
                  <div className="text-xs text-muted-foreground font-medium">
                    Showing page {queueData.meta.current_page} of {queueData.meta.last_page} ({queueData.meta.total} total jobs)
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
                      onClick={() => setCurrentPage(prev => Math.min(prev + 1, queueData.meta?.last_page ?? 1))}
                      disabled={currentPage === queueData.meta.last_page}
                      className="px-3.5 py-1.5 border border-border bg-card text-xs font-semibold rounded hover:bg-secondary transition-colors disabled:opacity-50"
                    >
                      Next
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        {/* EXPORT MONITOR TAB */}
        {activeTab === 'exports' && (
          <div className="space-y-6">
            {/* Table */}
            <div className="bg-card border border-border rounded-xl overflow-hidden shadow-sm">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-secondary/50 border-b border-border">
                    <tr className="text-muted-foreground text-xs font-bold uppercase tracking-wider">
                      <th className="py-4 px-6">Export Type</th>
                      <th className="py-4 px-6">Studio & Gallery</th>
                      <th className="py-4 px-6">Status</th>
                      <th className="py-4 px-6">Progress</th>
                      <th className="py-4 px-6">Recipient Notification</th>
                      <th className="py-4 px-6">ETA / Duration</th>
                      <th className="py-4 px-6">Timestamps</th>
                    </tr>
                  </thead>
                  <tbody>
                    {isExportsLoading ? (
                      <tr>
                        <td colSpan={7} className="py-12 text-center text-muted-foreground font-medium">
                          <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-primary" />
                          Loading exports monitor...
                        </td>
                      </tr>
                    ) : !exportsData?.data || exportsData.data.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="py-12 text-center text-muted-foreground font-medium">
                          No export jobs found.
                        </td>
                      </tr>
                    ) : (
                      exportsData.data.map((job) => {
                        const statusClass = 
                          ['completed', 'ready'].includes(job.status) ? 'bg-green-100 text-green-800 dark:bg-green-950/30 dark:text-green-400' :
                          ['completed_with_errors', 'ready_with_errors'].includes(job.status) ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/30 dark:text-amber-400' :
                          job.status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-950/30 dark:text-red-400' :
                          job.status === 'processing' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/30 dark:text-blue-400' :
                          'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-400';

                        // Calculate duration
                        let durationStr = '—';
                        if (job.started_at && job.completed_at) {
                          const diff = new Date(job.completed_at).getTime() - new Date(job.started_at).getTime();
                          durationStr = diff < 60000 
                            ? `${(diff / 1000).toFixed(1)}s` 
                            : `${Math.floor(diff / 60000)}m ${Math.floor((diff % 60000) / 1000)}s`;
                        }

                        return (
                          <tr key={`${job.type}-${job.id}`} className="border-b border-border hover:bg-secondary/10 transition-colors">
                            <td className="py-4 px-6 font-semibold">
                              <div className="flex items-center gap-2">
                                <span className={`text-[10px] font-extrabold uppercase px-2 py-0.5 rounded ${
                                  job.type === 'zip' ? 'bg-primary/10 text-primary' : 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400'
                                }`}>
                                  {job.type === 'zip' ? 'ZIP Archive' : 'Google Photos'}
                                </span>
                              </div>
                              <div className="text-[10px] text-muted-foreground mt-1">ID: #{job.id}</div>
                            </td>
                            <td className="py-4 px-6">
                              <div className="font-semibold text-foreground truncate max-w-[200px]" title={job.gallery_title}>
                                {job.gallery_title}
                              </div>
                              <div className="text-xs text-muted-foreground truncate max-w-[200px]" title={job.studio_name}>
                                {job.studio_name}
                              </div>
                            </td>
                            <td className="py-4 px-6">
                              <span className={`text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full ${statusClass}`}>
                                {job.status.replace(/_/g, ' ')}
                              </span>
                            </td>
                            <td className="py-4 px-6">
                              {job.status === 'failed' ? (
                                <div className="text-xs text-red-600 max-w-[250px] leading-snug">
                                  <strong>Error:</strong> {job.error || 'Execution failed.'}
                                </div>
                              ) : (
                                <div className="space-y-1.5 max-w-[150px]">
                                  <div className="flex justify-between text-xs font-semibold">
                                    <span>{job.percentage}%</span>
                                    <span className="text-muted-foreground text-[10px]">{job.processed_photos + job.failed_photos}/{job.total_photos}</span>
                                  </div>
                                  <div className="w-full bg-secondary/50 rounded-full h-1.5 overflow-hidden border border-border">
                                    <div
                                      className="bg-primary h-full rounded-full transition-all duration-300"
                                      style={{ width: `${job.percentage}%` }}
                                    />
                                  </div>
                                </div>
                              )}
                            </td>
                            <td className="py-4 px-6 text-xs text-muted-foreground">
                              {job.notify_when_ready && job.email ? (
                                <div className="flex items-center gap-1.5">
                                  <Mail size={14} className="text-primary shrink-0" />
                                  <span className="font-semibold text-foreground">{job.email}</span>
                                </div>
                              ) : (
                                <span className="italic text-muted-foreground">No Alert</span>
                              )}
                            </td>
                            <td className="py-4 px-6 font-mono text-xs text-foreground">
                              {['pending', 'processing'].includes(job.status) ? (
                                job.estimated_finish_time ? (
                                  <span className="text-primary font-bold">
                                    ~{job.remaining_seconds !== null ? (
                                      job.remaining_seconds < 60 ? '1m' : `${Math.ceil(job.remaining_seconds / 60)}m`
                                    ) : 'calc'}
                                  </span>
                                ) : (
                                  <span className="text-muted-foreground italic">Estimating...</span>
                                )
                              ) : (
                                durationStr
                              )}
                            </td>
                            <td className="py-4 px-6 text-xs text-muted-foreground space-y-1">
                              {job.started_at && (
                                <div className="flex items-center gap-1">
                                  <span className="font-semibold text-[9px] uppercase px-1 py-0.2 bg-secondary rounded text-muted-foreground">Start:</span>
                                  <span>{new Date(job.started_at).toLocaleTimeString()}</span>
                                </div>
                              )}
                              {job.completed_at && (
                                <div className="flex items-center gap-1">
                                  <span className="font-semibold text-[9px] uppercase px-1 py-0.2 bg-secondary rounded text-muted-foreground">End:</span>
                                  <span>{new Date(job.completed_at).toLocaleTimeString()}</span>
                                </div>
                              )}
                            </td>
                          </tr>
                        )
                      })
                    )}
                  </tbody>
                </table>
              </div>

              {/* Pagination controls */}
              {exportsData?.meta && exportsData.meta.last_page > 1 && (
                <div className="bg-secondary/40 border-t border-border px-6 py-4 flex items-center justify-between">
                  <div className="text-xs text-muted-foreground font-medium">
                    Showing page {exportsData.meta.current_page} of {exportsData.meta.last_page} ({exportsData.meta.total} total exports)
                  </div>
                  <div className="flex gap-2">
                    <button
                      onClick={() => setExportsPage(prev => Math.max(prev - 1, 1))}
                      disabled={exportsPage === 1}
                      className="px-3.5 py-1.5 border border-border bg-card text-xs font-semibold rounded hover:bg-secondary transition-colors disabled:opacity-50"
                    >
                      Previous
                    </button>
                    <button
                      onClick={() => setExportsPage(prev => Math.min(prev + 1, exportsData.meta?.last_page ?? 1))}
                      disabled={exportsPage === exportsData.meta.last_page}
                      className="px-3.5 py-1.5 border border-border bg-card text-xs font-semibold rounded hover:bg-secondary transition-colors disabled:opacity-50"
                    >
                      Next
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </main>
  )
}
