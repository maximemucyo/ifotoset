import { Section } from './section'
import { featureItems } from './data'

export function FeatureGrid() {
  return (
    <Section id="features" className="border-t border-border/80">
      <div className="text-center max-w-3xl mx-auto mb-16">
        <h2 className="text-3xl sm:text-4xl font-bold tracking-tight text-foreground mb-4">
          Built for Modern Photographers.
        </h2>
        <p className="text-lg text-muted-foreground">
          ifotoset equips you with critical capabilities to manage client satisfaction and dashboard efficiency in one unified suite.
        </p>
      </div>

      <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
        {featureItems.map((feature, idx) => (
          <div
            key={idx}
            className="p-6 bg-card rounded-2xl border border-border hover:border-primary/30 shadow-sm hover:scale-[1.02] hover:shadow-md transition-all duration-300 flex items-start gap-4"
          >
            <span className="flex-shrink-0 w-6 h-6 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold">
              ✓
            </span>
            <div>
              <h3 className="font-bold text-foreground text-base mb-2">
                {feature.title}
              </h3>
              <p className="text-muted-foreground text-sm leading-relaxed">
                {feature.description}
              </p>
            </div>
          </div>
        ))}
      </div>
    </Section>
  )
}
export default FeatureGrid
