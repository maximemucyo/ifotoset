'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { LogOut, Menu } from 'lucide-react'
import { useState } from 'react'
import { Logo } from './logo'
import { ThemeToggle } from './theme-toggle'
import { useLogoutMutation, useCurrentUser } from '@/lib/queries/auth'
import { Routes } from '@/lib/routes'
import {
  LayoutDashboard,
  Users,
  BarChart3,
  ImagePlus,
  AlertCircle,
  Settings,
  DollarSign
} from 'lucide-react'

export function AdminSidebar() {
  const pathname = usePathname()
  const [isOpen, setIsOpen] = useState(false)
  const logoutMutation = useLogoutMutation()
  const { data: currentUser } = useCurrentUser()

  const isActive = (href: string) => pathname === href

  const links = [
    { href: '/admin/dashboard', icon: LayoutDashboard, label: 'Dashboard' },
    { href: '/admin/users', icon: Users, label: 'User Management' },
    { href: '/admin/analytics', icon: BarChart3, label: 'Analytics' },
    { href: '/admin/moderation', icon: AlertCircle, label: 'Moderation' },
    { href: '/admin/galleries', icon: ImagePlus, label: 'Featured Galleries' },
    { href: '/admin/support', icon: AlertCircle, label: 'Support Tickets' },
    { href: '/admin/payments', icon: DollarSign, label: 'Payments' },
    { href: '/admin/settings', icon: Settings, label: 'Settings' }
  ]

  return (
    <>
      {/* Mobile Menu Button */}
      <button
        className="fixed top-4 left-4 z-50 md:hidden p-2 bg-primary text-primary-foreground rounded-lg"
        onClick={() => setIsOpen(!isOpen)}
      >
        <Menu size={24} />
      </button>

      {/* Sidebar */}
      <aside className={`fixed md:sticky md:top-0 md:self-start top-0 left-0 h-screen w-64 bg-sidebar border-r border-sidebar-border transition-transform duration-300 transform ${
        isOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
      } z-40`}>
        <div className="p-6 border-b border-sidebar-border flex items-center justify-center">
          <Logo size="md" href="/admin/dashboard" variant="dark" />
        </div>

        <nav className="p-4 space-y-2">
          {links.map((link) => {
            const Icon = link.icon
            return (
              <Link
                key={link.href}
                href={link.href}
                onClick={() => setIsOpen(false)}
                className={`flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                  isActive(link.href)
                    ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                    : 'text-sidebar-foreground hover:bg-sidebar-accent'
                }`}
              >
                <Icon size={20} />
                <span>{link.label}</span>
              </Link>
            )
          })}
        </nav>

        <div className="absolute bottom-0 left-0 right-0 p-4 border-t border-sidebar-border space-y-2 bg-sidebar">
          {currentUser?.user && (
            <div className="px-4 py-2 mb-2 rounded-lg bg-sidebar-accent/50 border border-sidebar-border">
              <p className="text-[10px] text-sidebar-foreground/60 uppercase tracking-wider font-semibold">Signed in as</p>
              <p className="text-sm font-bold text-sidebar-foreground truncate">{currentUser.user.name}</p>
            </div>
          )}
          <div className="flex justify-center">
            <ThemeToggle />
          </div>
          <button 
            onClick={() => {
              logoutMutation.mutate(undefined, {
                onSuccess: () => {
                  window.location.href = Routes.login
                }
              })
            }}
            disabled={logoutMutation.isPending}
            className="w-full flex items-center gap-2 px-4 py-3 rounded-lg text-sidebar-foreground hover:bg-sidebar-accent transition-colors disabled:opacity-50"
          >
            <LogOut size={20} />
            <span>{logoutMutation.isPending ? 'Logging out...' : 'Logout'}</span>
          </button>
        </div>
      </aside>

      {/* Mobile Overlay */}
      {isOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-30 md:hidden"
          onClick={() => setIsOpen(false)}
        />
      )}
    </>
  )
}
