'use client'

import Link from 'next/link'
import Image from 'next/image'

interface LogoProps {
  variant?: 'default' | 'light' | 'dark' | 'text-only'
  size?: 'sm' | 'md' | 'lg'
  href?: string
}

const sizes = {
  sm: { mark: 24, text: 'text-lg' },
  md: { mark: 32, text: 'text-2xl' },
  lg: { mark: 48, text: 'text-4xl' }
}

export function Logo({ variant = 'default', size = 'md', href = '/' }: LogoProps) {
  const sizeConfig = sizes[size]
  
  const textColor = variant === 'light' ? 'text-white' : 'text-foreground'
  const accentColor = 'text-primary'

  const content = (
    <div className="flex items-center gap-3">
      {variant !== 'text-only' && (
        <div className="relative flex-shrink-0" style={{ width: sizeConfig.mark, height: sizeConfig.mark }}>
          <Image
            src="/logo.png"
            alt="ifotoset"
            fill
            className="object-contain drop-shadow-sm"
            priority
          />
        </div>
      )}
      <div className={`${sizeConfig.text} font-bold tracking-tight`}>
        <span className={textColor}>
          ifoto
        </span>
        <span className={accentColor}>
          set
        </span>
      </div>
    </div>
  )

  if (href) {
    return (
      <Link href={href} className="hover:opacity-80 transition-opacity">
        {content}
      </Link>
    )
  }

  return content
}

export function LogoMark() {
  return (
    <Link href="/" className="relative w-10 h-10 hover:opacity-80 transition-opacity">
      <Image
        src="/logo.png"
        alt="ifotoset"
        fill
        className="object-contain"
        priority
      />
    </Link>
  )
}

export function LogoText() {
  return (
    <Link href="/" className="font-bold text-xl tracking-tight hover:opacity-80 transition-opacity inline-block">
      <span className="text-foreground">ifoto</span>
      <span className="text-primary">set</span>
    </Link>
  )
}
