'use client'

import { useState } from 'react'
import { ChevronDown } from 'lucide-react'
import { Section } from './section'
import { faqItems } from './data'

export function FAQ() {
  const [openIndex, setOpenIndex] = useState<number | null>(null)

  const toggleItem = (index: number) => {
    setOpenIndex(openIndex === index ? null : index)
  }

  return (
    <Section id="faq" className="border-t border-border/80">
      <div className="text-center max-w-3xl mx-auto mb-16">
        <h2 className="text-3xl sm:text-4xl font-bold tracking-tight text-foreground mb-4">
          Frequently Asked Questions
        </h2>
        <p className="text-lg text-muted-foreground">
          Everything you need to know about the platform, delivery features, and studio plans.
        </p>
      </div>

      <div className="max-w-3xl mx-auto space-y-4">
        {faqItems.map((item, idx) => {
          const isOpen = openIndex === idx

          return (
            <div
              key={idx}
              className="bg-card border border-border rounded-xl overflow-hidden transition-all duration-300"
            >
              {/* Accordion Trigger Header */}
              <button
                type="button"
                onClick={() => toggleItem(idx)}
                aria-expanded={isOpen}
                aria-controls={`faq-answer-${idx}`}
                id={`faq-question-${idx}`}
                className="w-full px-6 py-5 flex items-center justify-between text-left font-bold text-foreground text-sm sm:text-base outline-none focus-visible:bg-secondary/40 hover:bg-secondary/20 transition-colors"
              >
                <span>{item.question}</span>
                <ChevronDown
                  size={20}
                  className={`text-muted-foreground transition-transform duration-300 ${
                    isOpen ? 'rotate-180 text-primary' : ''
                  }`}
                />
              </button>

              {/* Accordion Panel Body */}
              <div
                id={`faq-answer-${idx}`}
                role="region"
                aria-labelledby={`faq-question-${idx}`}
                className={`transition-all duration-300 ease-in-out overflow-hidden ${
                  isOpen ? 'max-h-[300px] border-t border-border/60 opacity-100' : 'max-h-0 opacity-0'
                }`}
              >
                <div className="p-6 text-sm sm:text-base text-muted-foreground leading-relaxed">
                  {item.answer}
                </div>
              </div>
            </div>
          )
        })}
      </div>
    </Section>
  )
}
export default FAQ
