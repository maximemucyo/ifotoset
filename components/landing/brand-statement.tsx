import { Section } from './section'

export function BrandStatement() {
  return (
    <Section className="border-t border-border/80 text-center">
      <div className="max-w-4xl mx-auto py-12 md:py-20 flex flex-col items-center justify-center">
        {/* Large Decorative Quote Symbol */}
        <span className="text-primary opacity-20 text-7xl sm:text-8xl font-serif leading-none select-none">
          “
        </span>
        <blockquote className="text-2xl sm:text-3xl md:text-4xl font-bold tracking-tight text-foreground text-balance max-w-3xl leading-[1.25] -mt-4 mb-6">
          Made for photographers. Designed around the way you work.
        </blockquote>
        <div className="w-16 h-1 bg-primary/40 rounded-full"></div>
      </div>
    </Section>
  )
}
export default BrandStatement
