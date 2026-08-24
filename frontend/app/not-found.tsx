import Link from 'next/link'
import { Camera } from 'lucide-react'

export default function NotFound() {
  return (
    <div className="min-h-screen bg-background flex flex-col items-center justify-center text-center px-4 text-foreground">
      <div className="space-y-6 max-w-md">
        <div className="w-20 h-20 bg-primary/10 rounded-full flex items-center justify-center mx-auto text-primary">
          <Camera size={40} />
        </div>
        <div className="space-y-2">
          <h1 className="text-4xl font-extrabold tracking-tight sm:text-5xl font-serif">404 - Page Not Found</h1>
          <p className="text-muted-foreground text-sm sm:text-base leading-relaxed">
            The page you are looking for doesn&apos;t exist, has been moved, or is temporarily unavailable.
          </p>
        </div>
        <div>
          <Link
            href="/"
            className="inline-flex items-center justify-center px-6 py-3 bg-primary text-primary-foreground font-semibold rounded-full hover:bg-primary/90 active:scale-95 transition-all shadow-lg hover:-translate-y-0.5"
          >
            Return to Home
          </Link>
        </div>
      </div>
    </div>
  )
}
