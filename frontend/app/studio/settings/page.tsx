'use client'

import { useState, useEffect } from 'react'
import { Save, Upload, Lock, Bell, CreditCard, Loader2, Sparkles, AlertCircle, CheckCircle2 } from 'lucide-react'
import { useCurrentUser, useUpdateProfileMutation, useChangePasswordMutation, useUpdateNotificationsMutation, useUploadAvatarMutation } from '@/lib/queries/auth'

export default function Settings() {
  const { data: currentUser, isLoading: isUserLoading } = useCurrentUser()
  
  const updateProfileMutation = useUpdateProfileMutation()
  const changePasswordMutation = useChangePasswordMutation()
  const updateNotificationsMutation = useUpdateNotificationsMutation()
  const uploadAvatarMutation = useUploadAvatarMutation()

  // Form states
  const [profileForm, setProfileForm] = useState({
    name: '',
    username: '',
    phone: '',
    location: '',
    website: '',
    bio: '',
  })

  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  })

  const [notifForm, setNotifForm] = useState({
    new_bookings: true,
    new_messages: true,
    gallery_activity: true,
    payment_received: true,
  })

  // Feedback states
  const [profileStatus, setProfileStatus] = useState<{ type: 'success' | 'error'; message: string } | null>(null)
  const [passwordStatus, setPasswordStatus] = useState<{ type: 'success' | 'error'; message: string } | null>(null)
  const [avatarStatus, setAvatarStatus] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  // Populate data when loaded
  useEffect(() => {
    if (currentUser?.user) {
      const u = currentUser.user
      setProfileForm({
        name: u.name || '',
        username: u.username || '',
        phone: u.phone || '',
        location: u.location || '',
        website: u.website || '',
        bio: u.bio || '',
      })
      if (u.notification_preferences) {
        setNotifForm({
          new_bookings: !!u.notification_preferences.new_bookings,
          new_messages: !!u.notification_preferences.new_messages,
          gallery_activity: !!u.notification_preferences.gallery_activity,
          payment_received: !!u.notification_preferences.payment_received,
        })
      }
    }
  }, [currentUser])

  // Profile Save
  const handleProfileSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setProfileStatus(null)
    updateProfileMutation.mutate(profileForm, {
      onSuccess: () => {
        setProfileStatus({ type: 'success', message: 'Studio profile updated successfully.' })
      },
      onError: (err: any) => {
        setProfileStatus({ type: 'error', message: err.message || 'Failed to update profile.' })
      }
    })
  }

  // Password Save
  const handlePasswordSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setPasswordStatus(null)
    if (passwordForm.password !== passwordForm.password_confirmation) {
      setPasswordStatus({ type: 'error', message: 'New password confirmation does not match.' })
      return
    }

    changePasswordMutation.mutate(passwordForm, {
      onSuccess: () => {
        setPasswordStatus({ type: 'success', message: 'Password updated successfully. Other device sessions revoked.' })
        setPasswordForm({ current_password: '', password: '', password_confirmation: '' })
      },
      onError: (err: any) => {
        setPasswordStatus({ type: 'error', message: err.message || 'Failed to change password. Make sure current password is correct.' })
      }
    })
  }

  // Notification Toggle
  const handleNotifToggle = (key: keyof typeof notifForm, checked: boolean) => {
    const next = { ...notifForm, [key]: checked }
    setNotifForm(next)
    updateNotificationsMutation.mutate(next)
  }

  // Avatar Upload
  const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    setAvatarStatus(null)

    uploadAvatarMutation.mutate(file, {
      onSuccess: () => {
        setAvatarStatus({ type: 'success', message: 'Avatar updated successfully.' })
      },
      onError: (err: any) => {
        setAvatarStatus({ type: 'error', message: err.message || 'Logo upload confirmation failed.' })
      }
    })
  }

  const user = currentUser?.user

  return (
    <main className="flex-1 flex flex-col bg-background">
      {/* Header */}
      <div className="border-b border-border bg-card p-4 sm:p-6 flex-shrink-0">
        <h1 className="text-2xl sm:text-3xl font-extrabold text-foreground tracking-tight">Settings</h1>
        <p className="text-xs sm:text-sm text-muted-foreground mt-1">Manage your studio profile, notifications, logo, and security</p>
      </div>

      {/* Content */}
      <div className="p-4 sm:p-6">
        {isUserLoading ? (
          <div className="max-w-4xl mx-auto space-y-6 animate-pulse">
            <div className="bg-card border border-border h-48 rounded-xl" />
            <div className="bg-card border border-border h-36 rounded-xl" />
            <div className="bg-card border border-border h-48 rounded-xl" />
          </div>
        ) : (
          <div className="max-w-4xl mx-auto space-y-6 pb-8">
            {/* Profile Section */}
            <div className="bg-card border border-border rounded-xl p-5 md:p-6 shadow-sm">
              <h2 className="text-lg font-bold text-foreground mb-6">Studio Profile</h2>

              {/* Logo / Avatar upload block */}
              <div className="mb-6 flex flex-col sm:flex-row items-center gap-5 p-4 bg-card-muted/20 border border-border rounded-xl">
                <div className="relative w-20 h-20 rounded-xl bg-secondary flex items-center justify-center text-3xl font-bold overflow-hidden border border-border shrink-0">
                  {user?.avatar_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={user.avatar_url} alt="Logo" className="w-full h-full object-cover" />
                  ) : (
                    <span>📸</span>
                  )}
                  {uploadAvatarMutation.isPending && (
                    <div className="absolute inset-0 bg-black/60 flex items-center justify-center text-white">
                      <Loader2 className="w-6 h-6 animate-spin text-primary" />
                    </div>
                  )}
                </div>
                <div>
                  <div className="flex flex-wrap gap-2">
                    <label className="px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-bold text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
                      <Upload size={14} />
                      Upload Logo
                      <input type="file" accept="image/*" className="hidden" onChange={handleAvatarChange} />
                    </label>
                  </div>
                  <p className="text-[10px] text-muted-foreground mt-2">Recommended resolution: 200x200px.</p>
                  {avatarStatus && (
                    <p className={`text-xs mt-1.5 font-semibold ${avatarStatus.type === 'success' ? 'text-green-600 dark:text-green-500' : 'text-red-600 dark:text-red-500'}`}>
                      {avatarStatus.message}
                    </p>
                  )}
                </div>
              </div>

              {profileStatus && (
                <div className={`p-4 mb-4 border rounded-xl flex items-start gap-2.5 ${
                  profileStatus.type === 'success'
                    ? 'bg-green-50 dark:bg-green-500/10 border-green-200 dark:border-green-500/20 text-green-700 dark:text-green-500'
                    : 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-500'
                }`}>
                  {profileStatus.type === 'success' ? <CheckCircle2 size={18} className="shrink-0 mt-0.5" /> : <AlertCircle size={18} className="shrink-0 mt-0.5" />}
                  <span className="text-sm font-semibold">{profileStatus.message}</span>
                </div>
              )}

              <form onSubmit={handleProfileSubmit} className="space-y-4">
                <div className="grid md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Studio Name *</label>
                    <input
                      type="text"
                      required
                      value={profileForm.name}
                      onChange={(e) => setProfileForm({ ...profileForm, name: e.target.value })}
                      className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5 flex justify-between">
                      <span>Booking Username *</span>
                      <span className="text-[10px] text-muted-foreground lowercase">sarah-clicks</span>
                    </label>
                    <input
                      type="text"
                      required
                      value={profileForm.username}
                      onChange={(e) => setProfileForm({ ...profileForm, username: e.target.value })}
                      placeholder="e.g. sarah-photography"
                      className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                    />
                  </div>
                </div>

                <div className="grid md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Email Address (Read-only)</label>
                    <input
                      type="email"
                      disabled
                      value={user?.email || ''}
                      className="w-full px-4 py-2 bg-secondary text-muted-foreground border border-border rounded-lg text-sm cursor-not-allowed"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Phone Number</label>
                    <input
                      type="tel"
                      value={profileForm.phone}
                      onChange={(e) => setProfileForm({ ...profileForm, phone: e.target.value })}
                      className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                      placeholder="+250 788 123 456"
                    />
                  </div>
                </div>

                <div className="grid md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Location</label>
                    <input
                      type="text"
                      value={profileForm.location}
                      onChange={(e) => setProfileForm({ ...profileForm, location: e.target.value })}
                      className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                      placeholder="Kigali, Rwanda"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Website</label>
                    <input
                      type="text"
                      value={profileForm.website}
                      onChange={(e) => setProfileForm({ ...profileForm, website: e.target.value })}
                      className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                      placeholder="www.sarahphoto.com"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Bio</label>
                  <textarea
                    rows={4}
                    value={profileForm.bio}
                    onChange={(e) => setProfileForm({ ...profileForm, bio: e.target.value })}
                    className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:border-primary"
                    placeholder="Professional photographer specializing in portraits, weddings and event shoots..."
                  />
                </div>

                <button
                  type="submit"
                  disabled={updateProfileMutation.isPending}
                  className="w-full flex items-center justify-center gap-2 px-6 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-bold text-sm shadow-sm"
                >
                  {updateProfileMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save size={16} />}
                  Save Studio Profile
                </button>
              </form>
            </div>

            {/* Notification settings */}
            <div className="bg-card border border-border rounded-xl p-5 md:p-6 shadow-sm">
              <div className="flex items-center gap-2.5 mb-6">
                <Bell className="w-5 h-5 text-primary" />
                <h2 className="text-lg font-bold text-foreground">Notifications & Preferences</h2>
              </div>

              <div className="space-y-3">
                {[
                  { key: 'new_bookings' as const, label: 'New Booking Requests', desc: 'Get notified when clients request an online session' },
                  { key: 'new_messages' as const, label: 'Client Messages', desc: 'Receive email alerts for direct portal communications' },
                  { key: 'gallery_activity' as const, label: 'Gallery Interactions', desc: 'Stay updated when clients view, download or favorite' },
                  { key: 'payment_received' as const, label: 'Payments Completed', desc: 'Instant confirmation on Mobile Money transactions' }
                ].map((item, idx) => (
                  <div key={idx} className="flex items-center justify-between p-4 bg-card-muted/20 rounded-xl border border-border">
                    <div className="min-w-0 pr-4">
                      <p className="text-sm font-bold text-foreground truncate">{item.label}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{item.desc}</p>
                    </div>
                    <input
                      type="checkbox"
                      checked={notifForm[item.key]}
                      onChange={(e) => handleNotifToggle(item.key, e.target.checked)}
                      className="w-5 h-5 text-primary bg-background border-border rounded focus:ring-primary shrink-0"
                    />
                  </div>
                ))}
              </div>
            </div>

            {/* Security Section */}
            <div className="bg-card border border-border rounded-xl p-5 md:p-6 shadow-sm">
              <div className="flex items-center gap-2.5 mb-6">
                <Lock className="w-5 h-5 text-primary" />
                <h2 className="text-lg font-bold text-foreground">Security & Password</h2>
              </div>

              {passwordStatus && (
                <div className={`p-4 mb-4 border rounded-xl flex items-start gap-2.5 ${
                  passwordStatus.type === 'success'
                    ? 'bg-green-50 dark:bg-green-500/10 border-green-200 dark:border-green-500/20 text-green-700 dark:text-green-500'
                    : 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-500'
                }`}>
                  {passwordStatus.type === 'success' ? <CheckCircle2 size={18} className="shrink-0 mt-0.5" /> : <AlertCircle size={18} className="shrink-0 mt-0.5" />}
                  <span className="text-sm font-semibold">{passwordStatus.message}</span>
                </div>
              )}

              <form onSubmit={handlePasswordSubmit} className="space-y-4">
                <div>
                  <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Current Password *</label>
                  <input
                    type="password"
                    required
                    value={passwordForm.current_password}
                    onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })}
                    className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground text-sm focus:outline-none focus:border-primary"
                  />
                </div>
                <div className="grid md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">New Password *</label>
                    <input
                      type="password"
                      required
                      value={passwordForm.password}
                      onChange={(e) => setPasswordForm({ ...passwordForm, password: e.target.value })}
                      className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground text-sm focus:outline-none focus:border-primary"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-foreground uppercase tracking-wider mb-1.5">Confirm New Password *</label>
                    <input
                      type="password"
                      required
                      value={passwordForm.password_confirmation}
                      onChange={(e) => setPasswordForm({ ...passwordForm, password_confirmation: e.target.value })}
                      className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground text-sm focus:outline-none focus:border-primary"
                    />
                  </div>
                </div>

                <button
                  type="submit"
                  disabled={changePasswordMutation.isPending}
                  className="w-full flex items-center justify-center gap-2 px-6 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-bold text-sm shadow-sm"
                >
                  {changePasswordMutation.isPending && <Loader2 className="w-4 h-4 animate-spin" />}
                  Change Password
                </button>
              </form>
            </div>

            {/* Payments Settings */}
            <div className="bg-card border border-border rounded-xl p-5 md:p-6 shadow-sm">
              <div className="flex items-center gap-2.5 mb-6">
                <CreditCard className="w-5 h-5 text-primary" />
                <h2 className="text-lg font-bold text-foreground">Payment Settings</h2>
              </div>

              <div className="space-y-4">
                <div className="p-4 bg-card-muted/20 border border-border rounded-xl">
                  <h3 className="text-sm font-bold text-foreground">MTN Mobile Money</h3>
                  <p className="text-xs text-muted-foreground mt-1 mb-4">Connect your MTN Mobile Money account to accept automated online client payments.</p>
                  <button className="px-4 py-2 bg-secondary text-foreground rounded-lg hover:bg-muted font-bold text-xs transition-colors cursor-not-allowed" disabled>
                    Connect Wallet (Coming Soon)
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </main>
  )
}
