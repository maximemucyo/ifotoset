'use client'

import { useCurrentUser } from '@/lib/queries/auth'
import { useRouter, usePathname } from 'next/navigation'
import { ReactNode, useEffect } from 'react'
import { Routes } from '@/lib/routes'

export function AuthGuard({ children }: { children: ReactNode }) {
  const { data, isLoading } = useCurrentUser()
  const router = useRouter()
  const pathname = usePathname()

  useEffect(() => {
    if (!isLoading) {
      if (!data?.user) {
        router.push(Routes.login)
      } else if (pathname.startsWith('/admin') && !data.permissions?.includes('admin.access')) {
        router.push(Routes.studioDashboard)
      }
    }
  }, [data, isLoading, pathname, router])

  if (isLoading) {
    return (
      <div className="min-h-screen bg-[#0f0c0a] bg-gradient-to-br from-[#1c120c] via-[#0f0c0a] to-[#050302] flex items-center justify-center">
        <div className="relative p-12 bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl shadow-2xl flex flex-col items-center gap-6 max-w-sm w-full mx-4 text-center">
          {/* Decorative glowing background item */}
          <div className="absolute inset-0 -z-10 bg-gradient-to-tr from-primary/20 to-transparent blur-2xl rounded-2xl" />

          {/* Premium Loader Ring */}
          <div className="relative w-16 h-16">
            <div className="absolute inset-0 rounded-full border-4 border-primary/20" />
            <div className="absolute inset-0 rounded-full border-4 border-t-primary animate-spin" />
          </div>

          <div>
            <h3 className="text-xl font-bold text-white tracking-wide">Syncing Session</h3>
            <p className="text-sm text-neutral-400 mt-2">Connecting to ifotoset secure network...</p>
          </div>
        </div>
      </div>
    )
  }

  // Double check client side roles before rendering components
  if (!data?.user) {
    return null
  }

  if (pathname.startsWith('/admin') && !data.permissions?.includes('admin.access')) {
    return null
  }

  return <>{children}</>
}
