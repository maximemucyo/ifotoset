'use client'

import { Save, Bell, Lock, Mail, Globe } from 'lucide-react'
import { useState } from 'react'

export default function AdminSettings() {
  const [settings, setSettings] = useState({
    platformName: 'ifotoset',
    supportEmail: 'support@ifotoset.com',
    contactPhone: '+254 800 123 456',
    maxGallerySize: '100',
    maxStoragePerUser: '500',
    commissionPercentage: '15'
  })

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
