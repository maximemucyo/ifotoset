'use client'

import { useState, useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { BarChart3, Users, ImagePlus, TrendingUp, Download, Heart, X, Sparkles, CheckCircle2, AlertCircle, Loader2 } from 'lucide-react'
import Link from 'next/link'
import { useCurrentUser } from '@/lib/queries/auth'
import { useDashboardStats } from '@/lib/queries/dashboard'
import { formatBytes } from '@/lib/utils'
import { usePlans, useInitiatePaymentMutation, usePaymentStatus } from '@/lib/queries/payments'
import { ResponsiveTable } from '@/components/ui/responsive-table'

import { useRouter, useSearchParams } from 'next/navigation'

export default function StudioDashboard() {
  const queryClient = useQueryClient()
  const { data: currentUser } = useCurrentUser()
  const { data: dashboardData, isLoading } = useDashboardStats()
  const searchParams = useSearchParams()
  const router = useRouter()
  const [showVerifySuccess, setShowVerifySuccess] = useState(false)

  useEffect(() => {
    if (searchParams && searchParams.get('verified') === '1') {
      setShowVerifySuccess(true)
      queryClient.invalidateQueries({ queryKey: ['currentUser'] })
      const newUrl = window.location.pathname
      window.history.replaceState({}, '', newUrl)
    }
  }, [searchParams, queryClient])

  const [isUpgradeOpen, setIsUpgradeOpen] = useState(false)
  const [upgradeStep, setUpgradeStep] = useState<'plans' | 'payment' | 'polling' | 'success' | 'error'>('plans')
  const [billingCycle, setBillingCycle] = useState<'monthly' | 'yearly'>('yearly')
  const [selectedPlan, setSelectedPlan] = useState<any | null>(null)
  
  const [phoneNumber, setPhoneNumber] = useState('')
  const [provider, setProvider] = useState<'MTN' | 'AIRTEL'>('MTN')
  const [paymentUuid, setPaymentUuid] = useState<string | null>(null)
  const [paymentError, setPaymentError] = useState<string | null>(null)

  const { data: plans = [] } = usePlans()
  const initiatePaymentMutation = useInitiatePaymentMutation()
  const { data: paymentStatus } = usePaymentStatus(paymentUuid)

  useEffect(() => {
    if (paymentStatus) {
      if (paymentStatus.status === 'completed') {
        setUpgradeStep('success')
        queryClient.invalidateQueries({ queryKey: ['currentUser'] })
        queryClient.invalidateQueries({ queryKey: ['dashboardStats'] })
      } else if (['failed', 'expired', 'cancelled'].includes(paymentStatus.status)) {
        setPaymentError(paymentStatus.error_message || 'The transaction was declined or timed out.')
        setUpgradeStep('error')
      }
    }
  }, [paymentStatus, queryClient])

  const planDetails: Record<string, { desc: string, features: string[] }> = {
    basic: {
      desc: 'For growing photographers who need more storage and video tools.',
      features: ['50 GB Optimized Storage', 'Up to 30 mins hosted video', 'Email Support', 'Portfolio website']
    },
    pro: {
      desc: 'For established photographers running a full-time business.',
      features: ['1 TB Optimized Storage', 'Up to 5 hours hosted video', 'Booking Manager', 'Secure Payments integration', 'Custom Domain Support']
    },
    business: {
      desc: 'For agencies, studios, and teams scaling their operations.',
      features: ['3 TB Optimized Storage', 'Up to 15 hours hosted video', 'Multi-user team accounts', 'Dedicated setup & onboarding', 'Priority Support']
    }
  }

  const handlePaymentSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedPlan) return

    const amountToCharge = billingCycle === 'monthly' ? selectedPlan.monthly_price : selectedPlan.annual_price
    const idempotencyKey = `upgrade_${currentUser?.user?.uuid}_${selectedPlan.slug}_${Date.now()}`

    initiatePaymentMutation.mutate({
      plan_id: selectedPlan.uuid,
      amount: amountToCharge,
      phone_number: phoneNumber,
      provider: provider,
      idempotency_key: idempotencyKey,
    }, {
      onSuccess: (data) => {
        setPaymentUuid(data.payment_uuid)
        setUpgradeStep('polling')
      },
      onError: (err: any) => {
        setPaymentError(err.message || 'Payment initiation failed. Please try again.')
        setUpgradeStep('error')
      }
    })
  }

  const stats = dashboardData?.stats
  const recentGalleries = dashboardData?.recent_galleries || []

  const statsCards = [
    { label: 'Active Galleries', value: stats?.active_galleries ?? 0, icon: ImagePlus, subtext: 'Galleries created' },
    { label: 'Total Downloads', value: stats?.total_downloads ?? 0, icon: Download, subtext: 'All-time files delivered' },
    { label: 'Total Favorites', value: stats?.total_favorites ?? 0, icon: Heart, subtext: 'Client selections' },
    { label: 'Storage Used', value: stats?.storage, icon: TrendingUp, subtext: '' }
  ]

  return (
    <main className="flex-1 min-h-screen bg-background flex flex-col">
      {/* Header */}
      <div className="border-b border-border bg-card p-4 md:p-6">
        <h1 className="text-2xl md:text-3xl font-extrabold text-foreground tracking-tight">
          Welcome back, {currentUser?.user?.name || 'Photographer'}! 👋
        </h1>
        <p className="text-xs md:text-sm text-muted-foreground mt-1 md:mt-2">
          Here&apos;s what&apos;s happening with your photography business today.
        </p>
      </div>

      {/* Content */}
      <div className="p-4 md:p-6 flex-1 space-y-6 md:space-y-8">
        {showVerifySuccess && (
          <div className="p-4 bg-green-500/10 border border-green-500/20 text-green-600 dark:text-green-400 text-sm rounded-xl flex items-center justify-between shadow-sm">
            <div className="flex items-center gap-2 font-medium">
              <CheckCircle2 size={18} className="shrink-0 text-green-500" />
              <span>Your email address has been verified successfully! All dashboard features are now unlocked.</span>
            </div>
            <button 
              type="button" 
              onClick={() => setShowVerifySuccess(false)}
              className="p-1 hover:bg-green-500/20 rounded text-green-600 dark:text-green-400 transition-colors"
            >
              <X size={16} />
            </button>
          </div>
        )}
        {/* Stats Grid */}
        {isLoading ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
            {[1, 2, 3, 4].map((idx) => (
              <div key={idx} className="bg-card border border-border rounded-xl p-6 animate-pulse">
                <div className="h-4 bg-muted w-2/3 mb-2 rounded" />
                <div className="h-8 bg-muted w-1/3 mb-4 rounded" />
                <div className="h-3 bg-muted w-1/2 rounded" />
              </div>
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
            {statsCards.map((stat, idx) => {
              const Icon = stat.icon

              if (stat.label === 'Storage Used') {
                const storage = stat.value as any
                const activeBytes = storage?.active_bytes ?? 0
                const trashBytes = storage?.trash_bytes ?? 0
                const limitBytes = storage?.limit_bytes
                const usedBytes = storage?.used_bytes ?? 0
                const isUnlimited = storage?.is_unlimited ?? false

                const activePercent = storage?.active_percent ?? 0
                const trashPercent = storage?.trash_percent ?? 0

                const isOverLimit = !isUnlimited && limitBytes && usedBytes > limitBytes

                return (
                  <div key={idx} className="bg-card border border-border rounded-xl p-6 hover:border-primary transition-colors flex flex-col justify-between shadow-sm">
                    <div>
                      <div className="flex items-start justify-between">
                        <div>
                          <p className="text-xs text-muted-foreground font-semibold uppercase tracking-wider">{stat.label}</p>
                          <h3 className="text-2xl font-bold text-foreground mt-1">
                            {formatBytes(usedBytes)}
                            {!isUnlimited && limitBytes && (
                              <span className="text-sm font-normal text-muted-foreground ml-1">
                                / {formatBytes(limitBytes)}
                              </span>
                            )}
                          </h3>
                        </div>
                        <Icon className="w-8 h-8 text-primary opacity-55" />
                      </div>

                      {/* Visual progress bar */}
                      {!isUnlimited && limitBytes ? (
                        <div className="w-full h-2.5 bg-muted rounded-full overflow-hidden flex mt-4 mb-2">
                          <div
                            className="h-full bg-primary transition-all duration-300"
                            style={{ width: `${activePercent}%` }}
                            title={`Active: ${formatBytes(activeBytes)}`}
                          />
                          <div
                            className="h-full bg-accent/60 transition-all duration-300"
                            style={{ width: `${trashPercent}%` }}
                            title={`Trash: ${formatBytes(trashBytes)}`}
                          />
                        </div>
                      ) : (
                        <div className="w-full h-2.5 bg-primary/20 rounded-full overflow-hidden flex mt-4 mb-2">
                          <div
                            className="h-full bg-primary transition-all duration-300"
                            style={{ width: '100%' }}
                            title={`Active: ${formatBytes(activeBytes)}`}
                          />
                        </div>
                      )}

                      {/* Legend details */}
                      <div className="space-y-1.5 mt-3 text-[11px] text-muted-foreground">
                        <div className="flex items-center justify-between">
                          <span className="flex items-center gap-1.5">
                            <span className="w-2 h-2 rounded-full bg-primary inline-block" />
                            <span>Active photos</span>
                          </span>
                          <span className="font-semibold text-foreground">{formatBytes(activeBytes)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="flex items-center gap-1.5">
                            <span className="w-2 h-2 rounded-full bg-accent/60 inline-block" />
                            <span>Trash</span>
                          </span>
                          <span className="font-semibold text-foreground">{formatBytes(trashBytes)}</span>
                        </div>
                      </div>
                    </div>

                    <div className="mt-4 pt-2 border-t border-border/50 flex items-center justify-between">
                      <p className="text-xs text-accent">
                        {isUnlimited
                          ? 'Unlimited plan'
                          : isOverLimit
                            ? 'Out of plan capacity'
                            : `${formatBytes(storage?.remaining_bytes ?? 0)} remaining`}
                      </p>
                      <div className="flex items-center gap-2">
                        <span className="text-[10px] text-muted-foreground uppercase font-semibold tracking-wider">
                          {storage?.plan_name ?? 'Free Tier'}
                        </span>
                        {storage?.plan_name !== 'Business' && (
                          <button 
                            onClick={() => setIsUpgradeOpen(true)}
                            className="text-[10px] bg-primary/10 hover:bg-primary/20 text-primary px-2 py-0.5 rounded font-semibold transition-colors"
                          >
                            Upgrade
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                )
              }

              return (
                <div key={idx} className="bg-card border border-border rounded-xl p-6 hover:border-primary transition-colors flex flex-col justify-between shadow-sm">
                  <div className="flex items-start justify-between mb-4">
                    <div>
                      <p className="text-xs text-muted-foreground font-semibold uppercase tracking-wider">{stat.label}</p>
                      <h3 className="text-2xl font-bold text-foreground mt-1">{stat.value as string | number}</h3>
                    </div>
                    <Icon className="w-8 h-8 text-primary opacity-55" />
                  </div>
                  <p className="text-xs text-accent mt-auto">{stat.subtext}</p>
                </div>
              )
            })}
          </div>
        )}

        {/* Recent Galleries */}
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-lg md:text-xl font-bold text-foreground">Recent Galleries</h2>
            <Link href="/studio/galleries" className="text-primary hover:text-accent text-xs md:text-sm font-semibold flex items-center gap-1">
              View All →
            </Link>
          </div>

          <ResponsiveTable
            items={recentGalleries}
            isLoading={isLoading}
            emptyText="No galleries created yet"
            emptySubtext="Create your first gallery now to deliver photos to clients."
            desktopColCount={5}
            desktopHeader={
              <thead className="bg-secondary/40 border-b border-border">
                <tr className="text-muted-foreground text-xs font-bold uppercase tracking-wider">
                  <th className="py-4 px-6">Gallery Name</th>
                  <th className="py-4 px-6">Date</th>
                  <th className="py-4 px-6 text-center">Photos</th>
                  <th className="py-4 px-6 text-center">Status</th>
                  <th className="py-4 px-6 text-right">Action</th>
                </tr>
              </thead>
            }
            renderDesktopRow={(gallery) => (
              <tr key={gallery.uuid} className="border-b border-border hover:bg-secondary/30 transition-colors">
                <td className="py-4 px-6 text-foreground font-semibold">{gallery.title}</td>
                <td className="py-4 px-6 text-muted-foreground text-sm">
                  {gallery.event_date ? new Date(gallery.event_date).toLocaleDateString() : 'N/A'}
                </td>
                <td className="py-4 px-6 text-center text-foreground font-semibold">{gallery.stats.photo_count}</td>
                <td className="py-4 px-6 text-center">
                  <span className={`text-xs font-semibold px-3 py-1 rounded-full ${
                    gallery.visibility === 'public'
                      ? 'bg-green-100 dark:bg-green-500/10 text-green-700 dark:text-green-500'
                      : 'bg-yellow-100 dark:bg-yellow-500/10 text-yellow-700 dark:text-yellow-500'
                  }`}>
                    {gallery.visibility}
                  </span>
                </td>
                <td className="py-4 px-6 text-right">
                  <Link href={`/studio/galleries/${gallery.uuid}`} className="text-primary hover:text-accent text-sm font-semibold">
                    View
                  </Link>
                </td>
              </tr>
            )}
            renderMobileCard={(gallery) => (
              <div key={gallery.uuid} className="bg-card border border-border rounded-xl p-5 space-y-3 hover:border-primary/50 transition-colors shadow-sm">
                <div className="flex justify-between items-start gap-4">
                  <div className="min-w-0">
                    <h4 className="font-bold text-foreground text-base truncate" title={gallery.title}>{gallery.title}</h4>
                    <p className="text-xs text-muted-foreground mt-0.5">
                      {gallery.event_date ? new Date(gallery.event_date).toLocaleDateString() : 'N/A'}
                    </p>
                  </div>
                  <span className={`text-[10px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-full shrink-0 ${
                    gallery.visibility === 'public'
                      ? 'bg-green-100 dark:bg-green-500/10 text-green-700 dark:text-green-500'
                      : 'bg-yellow-100 dark:bg-yellow-500/10 text-yellow-700 dark:text-yellow-500'
                  }`}>
                    {gallery.visibility}
                  </span>
                </div>
                <div className="flex justify-between items-center text-xs pt-2 border-t border-border/50">
                  <span className="text-muted-foreground">Photos: <strong className="text-foreground">{gallery.stats.photo_count}</strong></span>
                  <Link href={`/studio/galleries/${gallery.uuid}`} className="text-primary hover:text-accent font-bold text-xs">
                    View →
                  </Link>
                </div>
              </div>
            )}
          />
        </div>

        {/* Quick Actions */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-6">
          <Link href="/studio/galleries/new" className="bg-primary text-primary-foreground rounded-xl p-6 hover:bg-accent transition-colors text-center shadow-sm">
            <ImagePlus className="w-8 h-8 mx-auto mb-2" />
            <h3 className="font-bold">Create Gallery</h3>
            <p className="text-sm text-primary-foreground/80 mt-1">Upload your latest photos</p>
          </Link>
          <Link href="/studio/packages" className="bg-card border border-border rounded-xl p-6 hover:border-primary transition-colors text-center shadow-sm">
            <ImagePlus className="w-8 h-8 mx-auto mb-2 text-primary" />
            <h3 className="font-bold text-foreground">Manage Packages</h3>
            <p className="text-sm text-muted-foreground mt-1">Update your pricing</p>
          </Link>
          <Link href="/studio/settings" className="bg-card border border-border rounded-xl p-6 hover:border-primary transition-colors text-center shadow-sm">
            <ImagePlus className="w-8 h-8 mx-auto mb-2 text-primary" />
            <h3 className="font-bold text-foreground">Studio Settings</h3>
            <p className="text-sm text-muted-foreground mt-1">Customize your profile</p>
          </Link>
        </div>
      </div>

      {/* Upgrade Plan Modal */}
      {isUpgradeOpen && (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-card border border-border rounded-2xl w-full max-w-5xl shadow-2xl overflow-hidden relative flex flex-col max-h-[90vh]">
            {/* Modal Header */}
            <div className="p-6 border-b border-border/60 flex items-center justify-between bg-card-muted/50">
              <div className="flex items-center gap-2">
                <Sparkles className="w-5 h-5 text-primary animate-pulse" />
                <h2 className="text-xl font-bold text-foreground">Upgrade Your Studio</h2>
              </div>
              <button 
                onClick={() => {
                  setIsUpgradeOpen(false)
                  setUpgradeStep('plans')
                  setPaymentUuid(null)
                  setPaymentError(null)
                }}
                className="p-1.5 hover:bg-secondary rounded-lg text-muted-foreground hover:text-foreground transition-colors"
              >
                <X size={20} />
              </button>
            </div>

            {/* Modal Content */}
            <div className="p-6 flex-1 overflow-y-auto">
              {upgradeStep === 'plans' && (
                <div>
                  <div className="text-center mb-8">
                    <p className="text-muted-foreground text-sm">Choose the plan that best fits your growing business. Upgrade instantly.</p>
                    
                    {/* Switcher */}
                    <div className="inline-flex items-center p-1 bg-muted rounded-full border border-border/60 mt-4">
                      <button 
                        onClick={() => setBillingCycle('monthly')}
                        className={`px-4 py-1.5 text-xs font-semibold rounded-full transition-all ${
                          billingCycle === 'monthly' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
                        }`}
                      >
                        Monthly
                      </button>
                      <button 
                        onClick={() => setBillingCycle('yearly')}
                        className={`px-4 py-1.5 text-xs font-semibold rounded-full transition-all ${
                          billingCycle === 'yearly' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
                        }`}
                      >
                        Yearly (-18%)
                      </button>
                    </div>
                  </div>

                  {/* Grid */}
                  <div className="grid md:grid-cols-3 gap-6">
                    {plans.filter(p => p.slug !== 'free').map((p) => {
                      const details = planDetails[p.slug] || { desc: '', features: [] }
                      const isCurrent = currentUser?.user?.plan === p.slug
                      const monthlyCost = p.monthly_price
                      const yearlyCost = p.annual_price
                      const priceToShow = billingCycle === 'monthly' ? monthlyCost : Math.round(yearlyCost / 12)

                      return (
                        <div 
                          key={p.uuid}
                          className={`border rounded-xl p-5 flex flex-col justify-between transition-all bg-card ${
                            isCurrent 
                              ? 'border-primary bg-primary/5 ring-1 ring-primary' 
                              : 'border-border hover:border-primary/50'
                          }`}
                        >
                          <div>
                            <div className="flex justify-between items-start mb-2">
                              <h4 className="font-bold text-foreground text-lg uppercase tracking-wide">{p.name}</h4>
                              {isCurrent && (
                                <span className="bg-primary/20 text-primary border border-primary/30 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">
                                  Current
                                </span>
                              )}
                            </div>
                            <p className="text-xs text-muted-foreground min-h-[40px] mb-4">{details.desc}</p>
                            
                            <div className="mb-6">
                              <span className="text-2xl font-black text-primary">RWF {priceToShow.toLocaleString()}</span>
                              <span className="text-xs text-muted-foreground"> / month</span>
                              {billingCycle === 'yearly' && (
                                <p className="text-[10px] text-muted-foreground mt-1">Billed annually (RWF {yearlyCost.toLocaleString()}/year)</p>
                              )}
                            </div>

                            <ul className="space-y-2 border-t border-border pt-4 mb-6">
                              {details.features.map((feat, index) => (
                                <li key={index} className="flex items-start gap-2 text-xs text-foreground">
                                  <span className="text-primary font-bold">✓</span>
                                  <span>{feat}</span>
                                </li>
                              ))}
                            </ul>
                          </div>

                          <button
                            disabled={isCurrent}
                            onClick={() => {
                              setSelectedPlan(p)
                              setUpgradeStep('payment')
                            }}
                            className={`w-full py-2.5 rounded-lg text-xs font-bold transition-all ${
                              isCurrent 
                                ? 'bg-secondary text-muted-foreground cursor-not-allowed' 
                                : 'bg-primary text-primary-foreground hover:bg-accent'
                            }`}
                          >
                            {isCurrent ? 'Current Plan' : `Upgrade to ${p.name}`}
                          </button>
                        </div>
                      )
                    })}
                  </div>
                </div>
              )}

              {upgradeStep === 'payment' && selectedPlan && (
                <div className="max-w-md mx-auto">
                  <div className="mb-6 text-center">
                    <h3 className="text-lg font-bold text-foreground">Subscription Details</h3>
                    <p className="text-sm text-muted-foreground mt-1">
                      Upgrading to <span className="font-semibold text-foreground">{selectedPlan.name} Plan</span>
                    </p>
                    <div className="mt-4 p-4 bg-secondary/40 border border-border rounded-xl">
                      <div className="flex justify-between items-center text-sm mb-2">
                        <span className="text-muted-foreground">Cycle</span>
                        <span className="font-semibold text-foreground uppercase">{billingCycle}</span>
                      </div>
                      <div className="flex justify-between items-center text-sm border-t border-border pt-2">
                        <span className="text-muted-foreground">Amount to pay</span>
                        <span className="font-bold text-primary text-base">
                          RWF {(billingCycle === 'monthly' ? selectedPlan.monthly_price : selectedPlan.annual_price).toLocaleString()}
                        </span>
                      </div>
                    </div>
                  </div>

                  <form onSubmit={handlePaymentSubmit} className="space-y-4">
                    <div>
                      <label className="block text-xs font-semibold text-foreground mb-2">Mobile Money Provider</label>
                      <div className="grid grid-cols-2 gap-3">
                        <label className={`flex items-center justify-center p-3 rounded-lg border cursor-pointer font-bold text-sm ${
                          provider === 'MTN' ? 'border-primary bg-primary/5 text-primary' : 'border-border text-muted-foreground hover:border-primary/40'
                        }`}>
                          <input 
                            type="radio" 
                            name="provider" 
                            value="MTN" 
                            checked={provider === 'MTN'}
                            onChange={() => setProvider('MTN')}
                            className="sr-only"
                          />
                          MTN Mobile Money
                        </label>
                        <label className={`flex items-center justify-center p-3 rounded-lg border cursor-pointer font-bold text-sm ${
                          provider === 'AIRTEL' ? 'border-primary bg-primary/5 text-primary' : 'border-border text-muted-foreground hover:border-primary/40'
                        }`}>
                          <input 
                            type="radio" 
                            name="provider" 
                            value="AIRTEL" 
                            checked={provider === 'AIRTEL'}
                            onChange={() => setProvider('AIRTEL')}
                            className="sr-only"
                          />
                          Airtel Money
                        </label>
                      </div>
                    </div>

                    <div>
                      <label className="block text-xs font-semibold text-foreground mb-2">MoMo Phone Number</label>
                      <input 
                        type="tel"
                        required
                        placeholder="e.g. 0788123456"
                        value={phoneNumber}
                        onChange={(e) => setPhoneNumber(e.target.value)}
                        className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary text-sm"
                      />
                      <p className="text-[10px] text-muted-foreground mt-1">Enter the phone number that will receive the payment prompt.</p>
                    </div>

                    <div className="flex gap-3 pt-4">
                      <button
                        type="button"
                        onClick={() => setUpgradeStep('plans')}
                        className="flex-1 py-2.5 bg-secondary text-foreground rounded-lg hover:bg-muted font-semibold text-xs transition-all"
                      >
                        Back
                      </button>
                      <button
                        type="submit"
                        disabled={initiatePaymentMutation.isPending}
                        className="flex-1 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent font-bold text-xs transition-all flex items-center justify-center gap-1.5"
                      >
                        {initiatePaymentMutation.isPending && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                        Pay Now
                      </button>
                    </div>
                  </form>
                </div>
              )}

              {upgradeStep === 'polling' && (
                <div className="max-w-md mx-auto text-center py-8">
                  <div className="w-16 h-16 bg-primary/10 border border-primary/20 rounded-full flex items-center justify-center mx-auto mb-6 animate-pulse">
                    <Loader2 className="w-8 h-8 text-primary animate-spin" />
                  </div>
                  <h3 className="text-xl font-bold text-foreground">Waiting for Payment Prompt</h3>
                  <p className="text-sm text-muted-foreground mt-2 max-w-xs mx-auto">
                    We have sent a Mobile Money transaction request to <span className="font-semibold text-foreground">{phoneNumber}</span>.
                  </p>
                  
                  <div className="my-6 p-4 bg-secondary/30 rounded-xl border border-border text-left space-y-2">
                    <p className="text-xs text-foreground font-semibold">Instructions:</p>
                    <ol className="list-decimal list-inside text-[11px] text-muted-foreground space-y-1.5">
                      <li>Check your phone for a push message from <strong>{provider}</strong>.</li>
                      <li>Enter your MoMo PIN to authorize the transaction.</li>
                      <li>If the prompt does not appear, dial <strong>{provider === 'MTN' ? '*182# -> Pending Approvals' : '*182#'}</strong>.</li>
                    </ol>
                  </div>

                  <p className="text-xs text-primary animate-pulse font-medium">Listening for payment confirmation...</p>
                </div>
              )}

              {upgradeStep === 'success' && selectedPlan && (
                <div className="max-w-md mx-auto text-center py-8">
                  <div className="w-16 h-16 bg-green-100 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <CheckCircle2 className="w-10 h-10 text-green-600 dark:text-green-500" />
                  </div>
                  <h3 className="text-xl font-bold text-foreground">Upgrade Successful!</h3>
                  <p className="text-sm text-muted-foreground mt-2 max-w-xs mx-auto">
                    Your studio is now upgraded to the <span className="font-semibold text-foreground">{selectedPlan.name} Plan</span>.
                  </p>
                  <p className="text-xs text-muted-foreground mt-1">Your new limits and capabilities are active immediately.</p>

                  <button
                    onClick={() => {
                      setIsUpgradeOpen(false)
                      setUpgradeStep('plans')
                      setPaymentUuid(null)
                    }}
                    className="mt-8 px-6 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent font-bold text-xs transition-all"
                  >
                    Got It!
                  </button>
                </div>
              )}

              {upgradeStep === 'error' && (
                <div className="max-w-md mx-auto text-center py-8">
                  <div className="w-16 h-16 bg-destructive/10 border border-destructive/20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <AlertCircle className="w-10 h-10 text-destructive" />
                  </div>
                  <h3 className="text-xl font-bold text-foreground">Payment Failed</h3>
                  <p className="text-sm text-muted-foreground mt-2 max-w-xs mx-auto">
                    {paymentError || 'Something went wrong during payment authorization.'}
                  </p>

                  <div className="flex gap-3 justify-center mt-8">
                    <button
                      onClick={() => setUpgradeStep('payment')}
                      className="px-6 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent font-bold text-xs transition-all"
                    >
                      Try Again
                    </button>
                    <button
                      onClick={() => {
                        setIsUpgradeOpen(false)
                        setUpgradeStep('plans')
                        setPaymentUuid(null)
                        setPaymentError(null)
                      }}
                      className="px-6 py-2.5 bg-secondary text-foreground rounded-lg hover:bg-muted font-semibold text-xs transition-all"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </main>
  )
}
