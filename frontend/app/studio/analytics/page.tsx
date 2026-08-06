'use client'

import { useState } from 'react'
import { Eye, Download, Users, Share2, TrendingUp, TrendingDown, EyeOff, Loader2 } from 'lucide-react'
import { useAnalytics } from '@/lib/queries/analytics'

export default function Analytics() {
  const [period, setPeriod] = useState<'7d' | '30d' | '90d'>('30d')

  const { data: analyticsData, isLoading, isError, error } = useAnalytics(period)

  const overview = analyticsData?.overview
  const monthlyViews = analyticsData?.monthly_views || []
  const downloadsBySource = analyticsData?.downloads_by_source || []
  const topGalleries = analyticsData?.top_galleries || []
  const recentActivity = analyticsData?.recent_activity || []

  const getSourceLabel = (src: string) => {
    switch (src) {
      case 'direct': return 'Direct Links'
      case 'email': return 'Email Shares'
      case 'social': return 'Social Media'
      default: return src
    }
  }

  const renderTrend = (value: number) => {
    if (value > 0) {
      return (
        <span className="text-xs text-green-600 dark:text-green-500 font-semibold flex items-center gap-1">
          <TrendingUp size={14} />
          +{value}% vs prev period
        </span>
      )
    } else if (value < 0) {
      return (
        <span className="text-xs text-red-600 dark:text-red-500 font-semibold flex items-center gap-1">
          <TrendingDown size={14} />
          {value}% vs prev period
        </span>
      )
    }
    return (
      <span className="text-xs text-muted-foreground font-semibold flex items-center gap-1">
        No change vs prev period
      </span>
    )
  }

  const maxMonthlyCount = Math.max(...monthlyViews.map((d) => d.count), 1)

  return (
    <main className="flex-1 flex flex-col h-screen overflow-hidden bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-4 md:p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 flex-shrink-0">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-foreground tracking-tight">Analytics</h1>
          <p className="text-xs md:text-sm text-muted-foreground mt-1">Track views, downloads, engagement and campaigns</p>
        </div>
      </div>

      {/* Period Filter Panel */}
      <div className="border-b border-border bg-card-muted/30 p-4 flex flex-shrink-0 gap-2.5">
        {(['7d', '30d', '90d'] as const).map((p) => (
          <button
            key={p}
            onClick={() => setPeriod(p)}
            className={`px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-lg border transition-all ${
              period === p
                ? 'bg-primary text-primary-foreground border-primary shadow-xs'
                : 'bg-card border-border text-foreground hover:border-primary'
            }`}
          >
            Last {p.replace('d', '')} Days
          </button>
        ))}
      </div>

      {/* Main Content Area */}
      <div className="flex-1 overflow-y-auto p-4 md:p-6 space-y-6">
        {isLoading ? (
          <div className="space-y-6">
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 animate-pulse">
              {[1, 2, 3, 4].map((idx) => (
                <div key={idx} className="bg-card border border-border rounded-xl p-5 h-28" />
              ))}
            </div>
            <div className="grid md:grid-cols-2 gap-6 animate-pulse">
              <div className="bg-card border border-border rounded-xl h-80" />
              <div className="bg-card border border-border rounded-xl h-80" />
            </div>
          </div>
        ) : isError ? (
          <div className="text-center py-12 bg-card border border-border rounded-xl max-w-xl mx-auto shadow-sm">
            <p className="text-destructive font-semibold">Failed to fetch analytics</p>
            <p className="text-muted-foreground text-sm mt-1">{error?.message || 'An unexpected error occurred.'}</p>
          </div>
        ) : (
          <div className="space-y-6 pb-6">
            {/* Overview Metric Grid */}
            {overview && (
              <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                <div className="bg-card border border-border rounded-xl p-5 shadow-sm hover:border-primary/50 transition-colors">
                  <div className="flex justify-between items-start">
                    <div>
                      <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Total Views</p>
                      <h3 className="text-2xl font-black text-foreground mt-1.5">{overview.total_views.toLocaleString()}</h3>
                    </div>
                    <div className="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
                      <Eye size={18} />
                    </div>
                  </div>
                  <div className="mt-3.5 border-t border-border/50 pt-2.5">
                    {renderTrend(overview.views_change_pct)}
                  </div>
                </div>

                <div className="bg-card border border-border rounded-xl p-5 shadow-sm hover:border-primary/50 transition-colors">
                  <div className="flex justify-between items-start">
                    <div>
                      <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Total Downloads</p>
                      <h3 className="text-2xl font-black text-foreground mt-1.5">{overview.total_downloads.toLocaleString()}</h3>
                    </div>
                    <div className="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
                      <Download size={18} />
                    </div>
                  </div>
                  <div className="mt-3.5 border-t border-border/50 pt-2.5">
                    {renderTrend(overview.downloads_change_pct)}
                  </div>
                </div>

                <div className="bg-card border border-border rounded-xl p-5 shadow-sm hover:border-primary/50 transition-colors">
                  <div className="flex justify-between items-start">
                    <div>
                      <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Unique Visitors</p>
                      <h3 className="text-2xl font-black text-foreground mt-1.5">{overview.unique_visitors.toLocaleString()}</h3>
                    </div>
                    <div className="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
                      <Users size={18} />
                    </div>
                  </div>
                  <div className="mt-3.5 border-t border-border/50 pt-2.5">
                    <p className="text-xs text-muted-foreground font-semibold">Cookie-session unique views</p>
                  </div>
                </div>

                <div className="bg-card border border-border rounded-xl p-5 shadow-sm hover:border-primary/50 transition-colors">
                  <div className="flex justify-between items-start">
                    <div>
                      <p className="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Gallery Shares</p>
                      <h3 className="text-2xl font-black text-foreground mt-1.5">{overview.gallery_shares.toLocaleString()}</h3>
                    </div>
                    <div className="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
                      <Share2 size={18} />
                    </div>
                  </div>
                  <div className="mt-3.5 border-t border-border/50 pt-2.5">
                    <p className="text-xs text-muted-foreground font-semibold">Total links and shares logged</p>
                  </div>
                </div>
              </div>
            )}

            {/* Averages info cards */}
            {overview && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6 bg-card border border-border rounded-xl p-5 shadow-xs">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <TrendingUp size={20} />
                  </div>
                  <div>
                    <h4 className="text-sm font-extrabold text-foreground">Average Views per Gallery</h4>
                    <p className="text-xs text-muted-foreground mt-0.5">Photographer average: <strong className="text-foreground">{overview.avg_views_per_gallery}</strong> views per active gallery.</p>
                  </div>
                </div>
                <div className="flex items-center gap-3 border-t md:border-t-0 md:border-l border-border pt-4 md:pt-0 md:pl-6">
                  <div className="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Download size={20} />
                  </div>
                  <div>
                    <h4 className="text-sm font-extrabold text-foreground">Average Downloads per Gallery</h4>
                    <p className="text-xs text-muted-foreground mt-0.5">Photographer average: <strong className="text-foreground">{overview.avg_downloads_per_gallery}</strong> downloads per active gallery.</p>
                  </div>
                </div>
              </div>
            )}

            {/* Charts Panel */}
            <div className="grid md:grid-cols-2 gap-6">
              {/* Views Over Time */}
              <div className="bg-card border border-border rounded-xl p-5 shadow-sm flex flex-col justify-between h-80">
                <h3 className="text-sm font-extrabold text-foreground uppercase tracking-wider mb-4">Gallery Views Over Time</h3>
                <div className="h-44 flex items-end gap-2.5 justify-between px-2 pt-2">
                  {monthlyViews.map((point, idx) => (
                    <div key={idx} className="flex-1 flex flex-col items-center h-full justify-end group">
                      <div
                        className="w-full bg-gradient-to-t from-primary to-accent rounded-t-md opacity-75 group-hover:opacity-100 transition-all cursor-pointer relative"
                        style={{ height: `${(point.count / maxMonthlyCount) * 100}%` }}
                        title={`${point.month}: ${point.count} views`}
                      >
                        {/* Hover Tooltip */}
                        <div className="hidden group-hover:block absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 bg-black text-white text-[10px] font-bold rounded shadow-md whitespace-nowrap z-10">
                          {point.count} views
                        </div>
                      </div>
                      <span className="text-[10px] text-muted-foreground mt-2 rotate-45 md:rotate-0 origin-center">
                        {point.month.split('-')[1]}/{point.month.split('-')[0].substring(2)}
                      </span>
                    </div>
                  ))}
                </div>
                <p className="text-[10px] text-muted-foreground text-center mt-3">Monthly view summary (past 12 months)</p>
              </div>

              {/* Downloads by Source */}
              <div className="bg-card border border-border rounded-xl p-5 shadow-sm h-80 flex flex-col justify-between">
                <div>
                  <h3 className="text-sm font-extrabold text-foreground uppercase tracking-wider mb-5">Downloads by Channel Source</h3>
                  <div className="space-y-4">
                    {downloadsBySource.map((item, idx) => (
                      <div key={idx}>
                        <div className="flex justify-between text-xs font-semibold mb-1.5">
                          <span className="text-foreground">{getSourceLabel(item.source)}</span>
                          <span className="text-muted-foreground">{item.count.toLocaleString()} ({item.percent}%)</span>
                        </div>
                        <div className="w-full bg-secondary rounded-full h-2">
                          <div
                            className="bg-gradient-to-r from-primary to-accent h-2 rounded-full transition-all duration-500"
                            style={{ width: `${item.percent}%` }}
                          />
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
                <p className="text-[10px] text-muted-foreground text-center">Referrals and UTM query source metrics</p>
              </div>
            </div>

            {/* Top Galleries Grid */}
            <div className="bg-card border border-border rounded-xl p-5 shadow-sm">
              <h3 className="text-sm font-extrabold text-foreground uppercase tracking-wider mb-4">Top Performing Galleries</h3>
              {topGalleries.length === 0 ? (
                <div className="text-center py-6 text-xs text-muted-foreground">
                  No gallery activity recorded in this period.
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead>
                      <tr className="border-b border-border text-[10px] font-bold uppercase tracking-wider text-muted-foreground text-left">
                        <th className="pb-3 px-4">Gallery Name</th>
                        <th className="pb-3 px-4 text-center">Views</th>
                        <th className="pb-3 px-4 text-center">Downloads</th>
                        <th className="pb-3 px-4 text-center">Favorites</th>
                      </tr>
                    </thead>
                    <tbody>
                      {topGalleries.map((gallery, idx) => (
                        <tr key={idx} className="border-b border-border/50 hover:bg-secondary/25 transition-colors text-xs text-foreground">
                          <td className="py-3 px-4 font-semibold">{gallery.title}</td>
                          <td className="py-3 px-4 text-center font-semibold">{gallery.views.toLocaleString()}</td>
                          <td className="py-3 px-4 text-center font-semibold">{gallery.downloads.toLocaleString()}</td>
                          <td className="py-3 px-4 text-center font-semibold">{gallery.favorites.toLocaleString()}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            {/* Recent Activity Log */}
            <div className="bg-card border border-border rounded-xl p-5 shadow-sm">
              <h3 className="text-sm font-extrabold text-foreground uppercase tracking-wider mb-4">Recent Activity Logs</h3>
              {recentActivity.length === 0 ? (
                <div className="text-center py-6 text-xs text-muted-foreground">
                  No log entries recorded.
                </div>
              ) : (
                <div className="space-y-3.5">
                  {recentActivity.map((log, idx) => (
                    <div key={idx} className="flex justify-between items-start gap-4 text-xs border-b border-border/30 pb-3 last:border-0 last:pb-0">
                      <div className="min-w-0">
                        <p className="font-semibold text-foreground truncate">
                          {log.event.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                        </p>
                        <p className="text-[10px] text-muted-foreground mt-0.5 truncate">Gallery: {log.gallery_title}</p>
                      </div>
                      <span className="text-[10px] text-muted-foreground shrink-0 mt-0.5">
                        {new Date(log.created_at).toLocaleString()}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </main>
  )
}
