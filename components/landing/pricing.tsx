import Link from 'next/link'
import { Section } from './section'
import { pricingPlans } from './data'

export function Pricing() {
  return (
    <Section id="pricing" className="border-t border-border/80">
      <div className="text-center max-w-3xl mx-auto mb-16">
        <h2 className="text-3xl sm:text-4xl font-bold tracking-tight text-foreground mb-4">
          Simple Pricing. No Surprises.
        </h2>
        <p className="text-lg text-muted-foreground">
          Choose the billing plan that scales with your photography workspace. Start free, upgrade anytime.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 max-w-[1920px] mx-auto">
        {pricingPlans.map((plan) => (
          <div
            key={plan.name}
            className={`relative rounded-2xl p-8 border transition-all duration-300 flex flex-col justify-between ${
              plan.highlighted
                ? 'border-primary bg-card xl:scale-105 shadow-xl shadow-primary/5 ring-1 ring-primary z-10'
                : 'border-border bg-card hover:border-primary/45 shadow-sm'
            }`}
          >
            {plan.highlighted && (
              <span className="absolute top-0 right-8 -translate-y-1/2 px-3 py-1 bg-primary text-primary-foreground text-xs font-bold uppercase tracking-wider rounded-full shadow">
                Most Popular
              </span>
            )}

            <div>
              <h3 className="text-2xl font-bold text-foreground mb-2">
                {plan.name}
              </h3>
              <p className="text-muted-foreground text-sm mb-6 min-h-[40px]">
                {plan.description}
              </p>
              
              <div className="mb-6 flex items-baseline gap-1">
                <span className="text-4xl font-extrabold tracking-tight text-primary">
                  {plan.price}
                </span>
                <span className="text-muted-foreground text-sm font-semibold">
                  {plan.period}
                </span>
              </div>

              <ul className="space-y-3.5 mb-8 border-t border-border/60 pt-6">
                {plan.features.map((feature, i) => (
                  <li key={i} className="flex items-center gap-3 text-sm text-foreground">
                    <span className="flex-shrink-0 w-5 h-5 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold">
                      ✓
                    </span>
                    <span>{feature}</span>
                  </li>
                ))}
              </ul>
            </div>

            <div>
              <Link
                href={plan.ctaHref}
                className={`block w-full py-3 px-4 rounded-xl font-bold text-center transition-all text-sm ${
                  plan.highlighted
                    ? 'bg-primary text-primary-foreground hover:bg-accent shadow-md hover:shadow-lg'
                    : 'bg-secondary text-foreground hover:bg-border'
                }`}
              >
                {plan.ctaText}
              </Link>
            </div>
          </div>
        ))}
      </div>

      <p className="text-center text-xs text-muted-foreground mt-12">
        * Unlimited storage is subject to our <Link href="#" className="underline hover:text-primary transition-colors">Fair Use Policy</Link>.
      </p>
    </Section>
  )
}
export default Pricing
