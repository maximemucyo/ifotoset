import Link from 'next/link'
import Image from 'next/image'
import { Camera, Layers, Users } from 'lucide-react'
import { Section } from './section'
import { suiteItems } from './data'

const iconsMap: Record<string, any> = {
  galleries: Camera,
  portfolio: Layers,
  studio: Users,
}

export function ProductSuite() {
  return (
    <Section className="border-t border-border/80">
      <div className="text-center max-w-3xl mx-auto mb-16">
        <h2 className="text-3xl sm:text-4xl font-bold tracking-tight text-foreground mb-4">
          One Platform. Three Powerful Experiences.
        </h2>
        <p className="text-lg text-muted-foreground">
          ifotoset is designed around the way you work, bringing your delivery, marketing, and scheduling workflows into one seamless suite.
        </p>
      </div>

      <div className="grid md:grid-cols-3 gap-8">
        {suiteItems.map((item) => {
          const Icon = iconsMap[item.id] || Camera

          return (
            <div
              key={item.id}
              className="group bg-card rounded-2xl border border-border p-6 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1.5 flex flex-col justify-between"
            >
              <div>
                {/* Icon & Title */}
                <div className="flex items-center gap-4 mb-4">
                  <div className="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-primary-foreground transition-all duration-300">
                    <Icon size={24} />
                  </div>
                  <h3 className="text-xl font-bold text-foreground">
                    {item.title}
                  </h3>
                </div>

                <p className="text-muted-foreground text-sm leading-relaxed mb-6">
                  {item.description}
                </p>

                {/* Compact Screenshot Preview */}
                <div className="relative aspect-[16/10] w-full rounded-lg overflow-hidden border border-border/60 mb-6 bg-secondary/20">
                  <Image
                    src={item.previewImage}
                    alt={`${item.title} preview`}
                    fill
                    className="object-cover group-hover:scale-105 transition-transform duration-500"
                  />
                </div>
              </div>

              <div>
                <Link
                  href={item.learnMoreHref}
                  className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary group-hover:text-accent transition-colors"
                >
                  Learn more
                  <span className="group-hover:translate-x-1 transition-transform">→</span>
                </Link>
              </div>
            </div>
          )
        })}
      </div>
    </Section>
  )
}
export default ProductSuite
