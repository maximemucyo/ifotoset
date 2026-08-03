'use client'

import { useState } from 'react'
import Link from 'next/link'
import { Menu, X } from 'lucide-react'
import { Logo } from './logo'
import { ThemeToggle } from './theme-toggle'

export function Navigation() {
  const [isOpen, setIsOpen] = useState(false)

  return (
    <header className="sticky top-0 z-50 bg-background border-b border-border">
      <nav className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <Logo size="md" href="/" />

        {/* Desktop Navigation */}
        <div className="hidden md:flex items-center gap-8">
          <Link href="/" className="text-foreground hover:text-primary transition-colors">
            Home
          </Link>
          <Link href="/#features" className="text-foreground hover:text-primary transition-colors">
            Features
          </Link>
          <Link href="/#pricing" className="text-foreground hover:text-primary transition-colors">
            Pricing
          </Link>
          <Link href="/login" className="text-foreground hover:text-primary transition-colors">
            Sign In
          </Link>
          <Link href="/signup" className="px-6 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors">
            Get Started
          </Link>
          <ThemeToggle />
        </div>

        {/* Mobile Navigation Button */}
        <div className="md:hidden flex items-center gap-2">
          <ThemeToggle />
          <button
            className="p-2 hover:bg-secondary rounded-lg"
            onClick={() => setIsOpen(!isOpen)}
          >
            {isOpen ? <X size={24} /> : <Menu size={24} />}
          </button>
        </div>

        {/* Mobile Navigation */}
        {isOpen && (
          <div className="absolute top-16 left-0 right-0 bg-background border-b border-border p-4 md:hidden">
            <div className="flex flex-col gap-4">
              <Link href="/" className="text-foreground hover:text-primary transition-colors">
                Home
              </Link>
              <Link href="/#features" className="text-foreground hover:text-primary transition-colors">
                Features
              </Link>
              <Link href="/#pricing" className="text-foreground hover:text-primary transition-colors">
                Pricing
              </Link>
              <Link href="/login" className="text-foreground hover:text-primary transition-colors">
                Sign In
              </Link>
              <Link href="/signup" className="px-6 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors text-center">
                Get Started
              </Link>
            </div>
          </div>
        )}
      </nav>
    </header>
  )
}
