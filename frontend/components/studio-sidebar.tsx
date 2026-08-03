'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { LogOut, Menu } from 'lucide-react'
import { useState } from 'react'
import { Logo } from './logo'
import { ThemeToggle } from './theme-toggle'
import {
  LayoutDashboard,
  ImagePlus,
  Users,
  Package,
  Calendar,
  BarChart3,
  Settings
} from 'lucide-react'

interface StudioSidebarProps {
  userId?: string
}

export function StudioSidebar({ userId }: StudioSidebarProps) {
  const pathname = usePathname()
  const [isOpen, setIsOpen] = useState(false)

  const isActive = (href: string) => pathname === href

  const links = [
    { href: '/studio/dashboard', icon: LayoutDashboard, label: 'Dashboard' },
    { href: '/studio/galleries', icon: ImagePlus, label: 'Galleries' },
    { href: '/studio/clients', icon: Users, label: 'Clients' },
    { href: '/studio/packages', icon: Package, label: 'Packages' },
    { href: '/studio/bookings', icon: Calendar, label: 'Bookings' },
    { href: '/studio/analytics', icon: BarChart3, label: 'Analytics' },
    { href: '/studio/settings', icon: Settings, label: 'Settings' }
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
      <aside className={`fixed md:static top-0 left-0 h-screen w-64 bg-sidebar border-r border-sidebar-border transition-transform duration-300 transform ${
        isOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
      } z-40`}>
        <div className="p-6 border-b border-sidebar-border flex items-center justify-center">
          <Logo size="md" href="/studio/dashboard" variant="dark" />
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

        <div className="absolute bottom-0 left-0 right-0 p-4 border-t border-sidebar-border space-y-2">
          <div className="flex justify-center">
            <ThemeToggle />
          </div>
          <button className="w-full flex items-center gap-2 px-4 py-3 rounded-lg text-sidebar-foreground hover:bg-sidebar-accent transition-colors">
            <LogOut size={20} />
            <span>Logout</span>
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
