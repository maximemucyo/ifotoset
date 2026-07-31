'use client'

import { Save, Upload, Lock, Bell, CreditCard } from 'lucide-react'
import { useState } from 'react'

export default function Settings() {
  const [formData, setFormData] = useState({
    studioName: 'Sarah Photography Studio',
    email: 'sarah@example.com',
    phone: '+250 788 123 456',
    bio: 'Professional photographer specializing in weddings and events.',
    location: 'Kigali, Rwanda',
    website: 'www.sarahphotography.com'
  })

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    })
  }

  return (
    <main className="flex-1 flex flex-col h-screen">
      {/* Header */}
      <div className="border-b border-border bg-card p-6 flex-shrink-0">
        <h1 className="text-3xl font-bold text-foreground">Settings</h1>
        <p className="text-muted-foreground mt-1">Manage your studio profile and preferences</p>
      </div>

      {/* Content - Scrollable */}
      <div className="flex-1 overflow-y-auto p-6">
        <div className="max-w-4xl mx-auto">
          {/* Profile Section */}
          <div className="bg-card border border-border rounded-lg p-6 mb-6">
            <h2 className="text-2xl font-bold text-foreground mb-6">Studio Profile</h2>

            <div className="mb-6">
              <div className="flex items-center gap-4">
                <div className="w-24 h-24 bg-secondary rounded-lg flex items-center justify-center text-4xl">
                  📸
                </div>
                <button className="flex items-center gap-2 px-6 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold">
                  <Upload size={20} />
                  Change Logo
                </button>
              </div>
            </div>

            <form className="space-y-4">
              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Studio Name</label>
                <input
                  type="text"
                  name="studioName"
                  value={formData.studioName}
                  onChange={handleChange}
                  className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                />
              </div>

              <div className="grid md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Email</label>
                  <input
                    type="email"
                    name="email"
                    value={formData.email}
                    onChange={handleChange}
                    className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Phone</label>
                  <input
                    type="tel"
                    name="phone"
                    value={formData.phone}
                    onChange={handleChange}
                    className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Location</label>
                <input
                  type="text"
                  name="location"
                  value={formData.location}
                  onChange={handleChange}
                  className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Website</label>
                <input
                  type="text"
                  name="website"
                  value={formData.website}
                  onChange={handleChange}
                  className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Bio</label>
                <textarea
                  name="bio"
                  value={formData.bio}
                  onChange={handleChange}
                  rows={4}
                  className="w-full px-4 py-2 bg-background border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:border-primary"
                />
              </div>

              <button type="button" className="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold">
                <Save size={20} />
                Save Changes
              </button>
            </form>
          </div>

          {/* Payment Settings */}
          <div className="bg-card border border-border rounded-lg p-6 mb-6">
            <div className="flex items-center gap-3 mb-6">
              <CreditCard className="w-6 h-6 text-primary" />
              <h2 className="text-2xl font-bold text-foreground">Payment Settings</h2>
            </div>

            <div className="space-y-4">
              <div className="p-4 bg-secondary/30 rounded-lg border border-border">
                <h3 className="font-semibold text-foreground mb-2">MTN Mobile Money</h3>
                <p className="text-sm text-muted-foreground mb-4">Connect your MTN Mobile Money account to accept payments from clients across East Africa.</p>
                <button className="px-6 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm">
                  Connect Account
                </button>
              </div>

              <div className="p-4 bg-secondary/30 rounded-lg border border-border">
                <h3 className="font-semibold text-foreground mb-2">Bank Account</h3>
                <p className="text-sm text-muted-foreground mb-4">Add your bank account for direct transfers.</p>
                <button className="px-6 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm">
                  Add Bank Account
                </button>
              </div>
            </div>
          </div>

          {/* Notification Settings */}
          <div className="bg-card border border-border rounded-lg p-6 mb-6">
            <div className="flex items-center gap-3 mb-6">
              <Bell className="w-6 h-6 text-primary" />
              <h2 className="text-2xl font-bold text-foreground">Notifications</h2>
            </div>

            <div className="space-y-4">
              {[
                { label: 'New bookings', desc: 'Get notified when clients book a session' },
                { label: 'New messages', desc: 'Receive alerts for client messages' },
                { label: 'Gallery activity', desc: 'Stay updated on gallery views and downloads' },
                { label: 'Payment received', desc: 'Get notified when payments are processed' }
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
                <p className="font-semibold text-foreground">Change Password</p>
                <p className="text-sm text-muted-foreground mt-1">Update your account password</p>
              </button>

              <button className="w-full p-4 bg-secondary/30 rounded-lg border border-border hover:border-primary transition-colors text-left">
                <p className="font-semibold text-foreground">Two-Factor Authentication</p>
                <p className="text-sm text-muted-foreground mt-1">Enable 2FA for enhanced security</p>
              </button>

              <button className="w-full p-4 bg-red-100 rounded-lg border border-red-300 hover:bg-red-200 transition-colors text-left">
                <p className="font-semibold text-red-700">Delete Account</p>
                <p className="text-sm text-red-600 mt-1">Permanently delete your account and all data</p>
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>
  )
}
