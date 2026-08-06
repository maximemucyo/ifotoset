import React from 'react'

interface ResponsiveTableProps<T> {
  items: T[]
  isLoading?: boolean
  loadingRowsCount?: number
  emptyText?: string
  emptySubtext?: string
  desktopHeader: React.ReactNode
  renderDesktopRow: (item: T, index: number) => React.ReactNode
  renderMobileCard: (item: T, index: number) => React.ReactNode
  tableClassName?: string
  desktopColCount?: number
}

export function ResponsiveTable<T>({
  items,
  isLoading = false,
  loadingRowsCount = 3,
  emptyText = 'No items found',
  emptySubtext,
  desktopHeader,
  renderDesktopRow,
  renderMobileCard,
  tableClassName = 'w-full text-left text-sm',
  desktopColCount = 4,
}: ResponsiveTableProps<T>) {
  if (isLoading) {
    return (
      <div className="space-y-4">
        {/* Desktop Loading Skeleton */}
        <div className="hidden md:block overflow-x-auto border border-border rounded-xl">
          <table className={tableClassName}>
            {desktopHeader}
            <tbody>
              {Array.from({ length: loadingRowsCount }).map((_, idx) => (
                <tr key={idx} className="border-b border-border animate-pulse">
                  {Array.from({ length: desktopColCount }).map((_, cellIdx) => (
                    <td key={cellIdx} className="py-4 px-6">
                      <div className="h-4 bg-muted rounded w-3/4" />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Mobile Loading Skeleton */}
        <div className="md:hidden space-y-4">
          {Array.from({ length: loadingRowsCount }).map((_, idx) => (
            <div key={idx} className="bg-card border border-border rounded-xl p-5 space-y-3 animate-pulse">
              <div className="flex justify-between items-center">
                <div className="h-5 bg-muted rounded w-1/2" />
                <div className="h-4 bg-muted rounded-full w-12" />
              </div>
              <div className="h-4 bg-muted rounded w-2/3" />
              <div className="flex justify-between items-center pt-2">
                <div className="h-4 bg-muted rounded w-1/3" />
                <div className="h-4 bg-muted rounded w-1/4" />
              </div>
            </div>
          ))}
        </div>
      </div>
    )
  }

  if (items.length === 0) {
    return (
      <div className="bg-card border border-border rounded-xl p-12 text-center shadow-sm">
        <p className="text-muted-foreground text-lg font-semibold">{emptyText}</p>
        {emptySubtext && <p className="text-xs text-muted-foreground/75 mt-1">{emptySubtext}</p>}
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {/* Desktop Layout */}
      <div className="hidden md:block overflow-x-auto border border-border rounded-xl bg-card">
        <table className={tableClassName}>
          {desktopHeader}
          <tbody>
            {items.map((item, index) => renderDesktopRow(item, index))}
          </tbody>
        </table>
      </div>

      {/* Mobile Layout */}
      <div className="md:hidden space-y-4">
        {items.map((item, index) => renderMobileCard(item, index))}
      </div>
    </div>
  )
}
