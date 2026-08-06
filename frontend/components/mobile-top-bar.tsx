'use client'

import { Menu } from 'lucide-react'
import { Logo } from './logo'
import { ReactNode } from 'react'

interface MobileTopBarProps {
  onMenuClick: () => void
  logoHref?: string
  rightAction?: ReactNode
}

export function MobileTopBar({ onMenuClick, logoHref = '/studio/dashboard', rightAction }: MobileTopBarProps) {
  return (
    <header className="md:hidden flex items-center justify-between px-4 bg-card border-b border-border sticky top-0 z-30 w-full h-[calc(4rem+env(safe-area-inset-top))] pt-[env(safe-area-inset-top)] shadow-sm">
      <div className="flex items-center gap-3">
        <button
          onClick={onMenuClick}
          className="p-2 text-foreground hover:bg-secondary rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
          aria-label="Open navigation menu"
        >
          <Menu size={24} />
        </button>
        <Logo size="sm" href={logoHref} variant="dark" />
      </div>
      {rightAction && (
        <div className="flex items-center">
          {rightAction}
        </div>
      )}
    </header>
  )
}
