'use client'

import { Save, Bell, Lock, Mail, Globe, Eye, EyeOff, Loader2, CheckCircle2, AlertCircle, Send } from 'lucide-react'
import { useState, useEffect } from 'react'
import { useAdminSmtpSettings, useUpdateAdminSmtpSettings } from '@/lib/queries/admin'

export default function AdminSettings() {
  const [settings, setSettings] = useState({
    platformName: 'ifotoset',
    supportEmail: 'support@ifotoset.com',
    contactPhone: '+254 800 123 456',
    maxGallerySize: '100',
    maxStoragePerUser: '500',
    commissionPercentage: '15'
  })

  const { data: smtpData, isLoading: isLoadingSmtp, isError: isErrorSmtp } = useAdminSmtpSettings()
  const updateSmtpMutation = useUpdateAdminSmtpSettings()

  const [smtpForm, setSmtpForm] = useState({
    host: '',
    port: 587,
    username: '',
    password: '',
    encryption: 'tls',
    from_address: '',
    from_name: '',
    test_email: '',
  })
  
  const [showPassword, setShowPassword] = useState(false)
  const [testStatus, setTestStatus] = useState<{ status: 'idle' | 'testing' | 'success' | 'error'; message?: string }>({ status: 'idle' })
  const [saveStatus, setSaveStatus] = useState<{ status: 'idle' | 'saving' | 'success' | 'error'; message?: string }>({ status: 'idle' })

  useEffect(() => {
    if (smtpData) {
      setSmtpForm(prev => ({
        ...prev,
        host: smtpData.host || '',
        port: smtpData.port || 587,
        username: smtpData.username || '',
        password: smtpData.has_password ? '********' : '',
        encryption: smtpData.encryption || 'tls',
        from_address: smtpData.from_address || '',
        from_name: smtpData.from_name || '',
      }))
    }
  }, [smtpData])

  const handleSmtpSave = async (e: React.FormEvent) => {
    e.preventDefault()
    setSaveStatus({ status: 'saving' })
    setTestStatus({ status: 'idle' })

    try {
      await updateSmtpMutation.mutateAsync({
        host: smtpForm.host,
        port: smtpForm.port,
        username: smtpForm.username,
        password: smtpForm.password,
        encryption: smtpForm.encryption,
        from_address: smtpForm.from_address,
        from_name: smtpForm.from_name,
      })

      setSaveStatus({
        status: 'success',
        message: 'SMTP configuration saved successfully. Queue workers have been triggered to reload configuration.',
      })
    } catch (err: any) {
      setSaveStatus({
        status: 'error',
        message: err?.message || 'An error occurred while saving configuration.',
      })
    }
  }

  const handleSmtpTest = async () => {
    if (!smtpForm.test_email) return
    setTestStatus({ status: 'testing' })

    try {
      const res = await updateSmtpMutation.mutateAsync({
        host: smtpForm.host,
        port: smtpForm.port,
        username: smtpForm.username,
        password: smtpForm.password,
        encryption: smtpForm.encryption,
        from_address: smtpForm.from_address,
        from_name: smtpForm.from_name,
        test_email: smtpForm.test_email,
      })

      if (res.test_error) {
        setTestStatus({
          status: 'error',
          message: res.test_error,
        })
      } else {
        setTestStatus({
          status: 'success',
          message: `Test email successfully sent to ${smtpForm.test_email}! Please check your inbox.`,
        })
      }
    } catch (err: any) {
      setTestStatus({
        status: 'error',
        message: err?.message || 'Failed to trigger verification. Please try again.',
      })
    }
  }

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setSettings({
      ...settings,
      [e.target.name]: e.target.value
    })
  }

  return (
    <main className="flex-1 flex flex-col h-screen">
      {/* Header */}
      <div className="border-b border-border bg-card p-6 flex-shrink-0">
        <h1 className="text-3xl font-bold text-foreground">Platform Settings</h1>
        <p className="text-muted-foreground mt-1">Manage global platform configuration</p>
      </div>

      {/* Content - Scrollable */}
      <div className="flex-1 overflow-y-auto p-6">
        <div className="max-w-4xl mx-auto">
          {/* General Settings */}
          <div className="bg-card border border-border rounded-lg p-6 mb-6">
            <h2 className="text-2xl font-bold text-foreground mb-6">General Settings</h2>

            <form className="space-y-4">
              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Platform Name</label>
                <input
                  type="text"
                  name="platformName"
                  value={settings.platformName}
                  onChange={handleChange}
                  className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                />
              </div>

              <div className="grid md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Support Email</label>
                  <input
                    type="email"
                    name="supportEmail"
                    value={settings.supportEmail}
                    onChange={handleChange}
                    className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Support Phone</label>
                  <input
                    type="tel"
                    name="contactPhone"
                    value={settings.contactPhone}
                    onChange={handleChange}
                    className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                  />
                </div>
              </div>

              <button type="button" className="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold">
                <Save size={20} />
                Save General Settings
              </button>
            </form>
          </div>

          {/* SMTP & Email Settings */}
          <div className="bg-card border border-border rounded-lg p-6 mb-6 relative overflow-hidden">
            {/* Subtle Gradient Accent */}
            <div className="absolute top-0 left-0 w-full h-[3px] bg-gradient-to-r from-primary via-purple-500 to-indigo-500" />
            
            <div className="flex items-center gap-3 mb-6">
              <Mail className="w-6 h-6 text-primary" />
              <div>
                <h2 className="text-2xl font-bold text-foreground">SMTP & Email Configuration</h2>
                <p className="text-sm text-muted-foreground">Configure SMTP server for transactional mail dispatch</p>
              </div>
            </div>

            {isLoadingSmtp ? (
              <div className="flex flex-col items-center justify-center py-12 gap-3">
                <Loader2 className="w-8 h-8 text-primary animate-spin" />
                <p className="text-sm text-muted-foreground font-medium animate-pulse">Loading SMTP configuration...</p>
              </div>
            ) : isErrorSmtp ? (
              <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-lg text-red-500 text-sm font-medium flex items-center gap-3">
                <AlertCircle className="w-5 h-5 flex-shrink-0" />
                <span>Failed to load SMTP settings from backend. Please refresh or try again later.</span>
              </div>
            ) : (
              <div className="space-y-6">
                <form onSubmit={handleSmtpSave} className="space-y-4">
                  <div className="grid md:grid-cols-3 gap-4">
                    <div className="md:col-span-2">
                      <label className="block text-sm font-semibold text-foreground mb-2">SMTP Host</label>
                      <input
                        type="text"
                        value={smtpForm.host}
                        onChange={e => setSmtpForm({ ...smtpForm, host: e.target.value })}
                        placeholder="e.g. smtp.gmail.com"
                        className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors text-sm"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">SMTP Port</label>
                      <input
                        type="number"
                        value={smtpForm.port}
                        onChange={e => setSmtpForm({ ...smtpForm, port: parseInt(e.target.value) || 0 })}
                        placeholder="587"
                        className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors text-sm"
                        required
                      />
                    </div>
                  </div>

                  <div className="grid md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">SMTP Username</label>
                      <input
                        type="text"
                        value={smtpForm.username}
                        onChange={e => setSmtpForm({ ...smtpForm, username: e.target.value })}
                        placeholder="username@domain.com"
                        className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors text-sm"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">SMTP Password</label>
                      <div className="relative">
                        <input
                          type={showPassword ? 'text' : 'password'}
                          value={smtpForm.password}
                          onChange={e => setSmtpForm({ ...smtpForm, password: e.target.value })}
                          placeholder={smtpData?.has_password ? '********' : 'Enter SMTP password'}
                          className="w-full pl-4 pr-10 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors text-sm"
                        />
                        <button
                          type="button"
                          onClick={() => setShowPassword(!showPassword)}
                          className="absolute right-3 top-1/2 transform -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors animate-fade-in"
                        >
                          {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                        </button>
                      </div>
                    </div>
                  </div>

                  <div className="grid md:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">Encryption</label>
                      <select
                        value={smtpForm.encryption}
                        onChange={e => setSmtpForm({ ...smtpForm, encryption: e.target.value })}
                        className="w-full px-4 py-2.5 bg-background border border-border rounded-lg text-foreground focus:outline-none focus:border-primary transition-colors text-sm font-medium"
                      >
                        <option value="tls">TLS (Recommended)</option>
                        <option value="ssl">SSL</option>
                        <option value="none">None</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">From Email Address</label>
                      <input
                        type="email"
                        value={smtpForm.from_address}
                        onChange={e => setSmtpForm({ ...smtpForm, from_address: e.target.value })}
                        placeholder="noreply@domain.com"
                        className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors text-sm"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">From Name</label>
                      <input
                        type="text"
                        value={smtpForm.from_name}
                        onChange={e => setSmtpForm({ ...smtpForm, from_name: e.target.value })}
                        placeholder="ifotoset"
                        className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors text-sm"
                        required
                      />
                    </div>
                  </div>

                  {saveStatus.status === 'success' && (
                    <div className="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-500 text-sm font-medium flex items-center gap-3">
                      <CheckCircle2 className="w-5 h-5 flex-shrink-0 animate-bounce" />
                      <span>{saveStatus.message}</span>
                    </div>
                  )}

                  {saveStatus.status === 'error' && (
                    <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-lg text-red-500 text-sm font-medium flex items-center gap-3">
                      <AlertCircle className="w-5 h-5 flex-shrink-0 animate-pulse" />
                      <span>{saveStatus.message}</span>
                    </div>
                  )}

                  <div className="flex gap-4 pt-2">
                    <button
                      type="submit"
                      disabled={updateSmtpMutation.isPending}
                      className="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 disabled:opacity-50 transition-all font-semibold text-sm cursor-pointer hover:shadow-md active:scale-95"
                    >
                      {updateSmtpMutation.isPending ? (
                        <Loader2 className="w-4 h-4 animate-spin" />
                      ) : (
                        <Save size={16} />
                      )}
                      Save SMTP Settings
                    </button>
                  </div>
                </form>

                {/* Verification Test Card */}
                <div className="border-t border-border pt-6 mt-6">
                  <h3 className="text-sm font-bold text-foreground mb-2 flex items-center gap-2">
                    <Send className="w-4 h-4 text-primary animate-pulse" />
                    Verify Connection (Send Test Email)
                  </h3>
                  <p className="text-xs text-muted-foreground mb-4">
                    Send a test message to ensure the mailer is working. You must save settings first.
                  </p>

                  <div className="flex flex-col sm:flex-row gap-3">
                    <div className="flex-1">
                      <input
                        type="email"
                        value={smtpForm.test_email}
                        onChange={e => setSmtpForm({ ...smtpForm, test_email: e.target.value })}
                        placeholder="Enter recipient email address..."
                        className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary transition-colors text-sm"
                      />
                    </div>
                    <button
                      type="button"
                      onClick={handleSmtpTest}
                      disabled={testStatus.status === 'testing' || !smtpForm.test_email}
                      className="px-6 py-2 bg-secondary text-secondary-foreground rounded-lg hover:bg-secondary/80 disabled:opacity-50 transition-all font-semibold text-sm flex items-center justify-center gap-2 cursor-pointer hover:shadow-sm active:scale-95"
                    >
                      {testStatus.status === 'testing' ? (
                        <Loader2 className="w-4 h-4 animate-spin" />
                      ) : null}
                      Send Test Email
                    </button>
                  </div>

                  {testStatus.status === 'success' && (
                    <div className="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-500 text-sm font-medium flex items-center gap-3 mt-4">
                      <CheckCircle2 className="w-5 h-5 flex-shrink-0 animate-bounce" />
                      <span>{testStatus.message}</span>
                    </div>
                  )}

                  {testStatus.status === 'error' && (
                    <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-lg text-red-500 text-sm font-medium flex items-center gap-3 mt-4">
                      <AlertCircle className="w-5 h-5 flex-shrink-0 animate-pulse" />
                      <span>{testStatus.message}</span>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Platform Limits */}
          <div className="bg-card border border-border rounded-lg p-6 mb-6">
            <div className="flex items-center gap-3 mb-6">
              <Globe className="w-6 h-6 text-primary" />
              <h2 className="text-2xl font-bold text-foreground">Platform Limits</h2>
            </div>

            <form className="space-y-4">
              <div className="grid md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Max Gallery Size (GB)</label>
                  <input
                    type="number"
                    name="maxGallerySize"
                    value={settings.maxGallerySize}
                    onChange={handleChange}
                    className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Storage Per User (GB)</label>
                  <input
                    type="number"
                    name="maxStoragePerUser"
                    value={settings.maxStoragePerUser}
                    onChange={handleChange}
                    className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                  />
                </div>
              </div>

              <button type="button" className="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold">
                <Save size={20} />
                Save Platform Limits
              </button>
            </form>
          </div>

          {/* Commission Settings */}
          <div className="bg-card border border-border rounded-lg p-6 mb-6">
            <div className="flex items-center gap-3 mb-6">
              <Mail className="w-6 h-6 text-primary" />
              <h2 className="text-2xl font-bold text-foreground">Commission Settings</h2>
            </div>

            <form className="space-y-4">
              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Commission Percentage (%)</label>
                <input
                  type="number"
                  name="commissionPercentage"
                  value={settings.commissionPercentage}
                  onChange={handleChange}
                  className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                />
                <p className="text-xs text-muted-foreground mt-2">Platform keeps this percentage from each transaction</p>
              </div>

              <button type="button" className="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold">
                <Save size={20} />
                Save Commission Settings
              </button>
            </form>
          </div>

          {/* Notification Settings */}
          <div className="bg-card border border-border rounded-lg p-6 mb-6">
            <div className="flex items-center gap-3 mb-6">
              <Bell className="w-6 h-6 text-primary" />
              <h2 className="text-2xl font-bold text-foreground">Notifications</h2>
            </div>

            <div className="space-y-4">
              {[
                { label: 'Flagged Content Alerts', desc: 'Get notified when content is flagged' },
                { label: 'Payment Issues', desc: 'Alerts for failed or suspicious transactions' },
                { label: 'User Support Requests', desc: 'Notifications for new support tickets' },
                { label: 'System Health Alerts', desc: 'Warnings about system performance' }
              ].map((notification, idx) => (
                <div key={idx} className="flex items-center justify-between p-4 bg-secondary/30 rounded-lg border border-border">
                  <div>
                    <p className="font-semibold text-foreground">{notification.label}</p>
                    <p className="text-sm text-muted-foreground">{notification.desc}</p>
                  </div>
                  <input
                    type="checkbox"
                    defaultChecked
                    className="w-5 h-5 rounded"
                  />
                </div>
              ))}
            </div>
          </div>

          {/* Security Settings */}
          <div className="bg-card border border-border rounded-lg p-6">
            <div className="flex items-center gap-3 mb-6">
              <Lock className="w-6 h-6 text-primary" />
              <h2 className="text-2xl font-bold text-foreground">Security</h2>
            </div>

            <div className="space-y-4">
              <button className="w-full p-4 bg-secondary/30 rounded-lg border border-border hover:border-primary transition-colors text-left">
                <p className="font-semibold text-foreground">Change Admin Password</p>
                <p className="text-sm text-muted-foreground mt-1">Update your admin account password</p>
              </button>

              <button className="w-full p-4 bg-secondary/30 rounded-lg border border-border hover:border-primary transition-colors text-left">
                <p className="font-semibold text-foreground">Audit Logs</p>
                <p className="text-sm text-muted-foreground mt-1">View all admin and system activities</p>
              </button>

              <button className="w-full p-4 bg-secondary/30 rounded-lg border border-border hover:border-primary transition-colors text-left">
                <p className="font-semibold text-foreground">Backup & Restore</p>
                <p className="text-sm text-muted-foreground mt-1">Manage database backups</p>
              </button>

              <button className="w-full p-4 bg-red-100 rounded-lg border border-red-300 hover:bg-red-200 transition-colors text-left">
                <p className="font-semibold text-red-700">Emergency Maintenance Mode</p>
                <p className="text-sm text-red-600 mt-1">Temporarily disable the platform for maintenance</p>
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>
  )
}
