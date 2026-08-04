'use client'

import { useState, KeyboardEvent, ClipboardEvent } from 'react'
import { X, Plus, AlertCircle } from 'lucide-react'

interface TagInputProps {
  emails: string[]
  onChange: (emails: string[]) => void
  placeholder?: string
}

export function TagInput({ emails, onChange, placeholder = 'Add client email...' }: TagInputProps) {
  const [input, setInput] = useState('')
  const [error, setError] = useState<string | null>(null)

  const validateEmail = (email: string): boolean => {
    // Simple but robust email regex
    const re = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
    return re.test(email)
  }

  const addEmails = (rawEmails: string[]) => {
    const validToAdd: string[] = []
    let hasInvalid = false
    let hasDuplicate = false

    rawEmails.forEach((raw) => {
      const email = raw.trim().toLowerCase()
      if (!email) return

      if (!validateEmail(email)) {
        hasInvalid = true
      } else if (emails.includes(email) || validToAdd.includes(email)) {
        hasDuplicate = true
      } else {
        validToAdd.push(email)
      }
    })

    if (validToAdd.length > 0) {
      onChange([...emails, ...validToAdd])
      setError(null)
    }

    if (hasInvalid) {
      setError('Please enter a valid email address.')
    } else if (hasDuplicate) {
      setError('Email address is already added.')
    } else {
      setError(null)
    }
  }

  const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      if (input.trim()) {
        addEmails([input])
        setInput('')
      }
    } else if (e.key === ',' || e.key === ';') {
      e.preventDefault()
      if (input.trim()) {
        addEmails([input])
        setInput('')
      }
    }
  }

  const handleBlur = () => {
    if (input.trim()) {
      addEmails([input])
      setInput('')
    }
  }

  const handlePaste = (e: ClipboardEvent<HTMLInputElement>) => {
    e.preventDefault()
    const pastedData = e.clipboardData.getData('text')
    // Split by comma, semicolon, newline, or multiple spaces
    const splitEmails = pastedData.split(/[\s,;]+/)
    addEmails(splitEmails)
    setInput('')
  }

  const removeEmail = (indexToRemove: number) => {
    onChange(emails.filter((_, index) => index !== indexToRemove))
    setError(null)
  }

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap gap-2 p-2 bg-background border border-border rounded-lg min-h-[46px] focus-within:border-primary transition-colors">
        {emails.map((email, index) => (
          <div
            key={`${email}-${index}`}
            className="flex items-center gap-1.5 px-3 py-1 bg-primary/10 border border-primary/20 text-primary rounded-full text-sm font-medium transition-all"
          >
            <span>{email}</span>
            <button
              type="button"
              onClick={() => removeEmail(index)}
              className="p-0.5 rounded-full hover:bg-primary/20 text-primary transition-colors focus:outline-none"
            >
              <X size={14} />
            </button>
          </div>
        ))}
        <div className="flex-1 flex items-center min-w-[120px]">
          <input
            type="text"
            value={input}
            onChange={(e) => {
              setInput(e.target.value)
              if (error) setError(null)
            }}
            onKeyDown={handleKeyDown}
            onBlur={handleBlur}
            onPaste={handlePaste}
            placeholder={emails.length === 0 ? placeholder : ''}
            className="w-full bg-transparent border-0 p-1 text-foreground placeholder-muted-foreground text-sm focus:outline-none focus:ring-0"
          />
          {input.trim() && (
            <button
              type="button"
              onClick={() => {
                addEmails([input])
                setInput('')
              }}
              className="p-1 rounded-md text-primary hover:bg-primary/5 focus:outline-none mr-1 shrink-0"
            >
              <Plus size={16} />
            </button>
          )}
        </div>
      </div>

      {error && (
        <div className="flex items-center gap-1.5 text-xs text-destructive mt-1 font-medium">
          <AlertCircle size={14} />
          <span>{error}</span>
        </div>
      )}
    </div>
  )
}
