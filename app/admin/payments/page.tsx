'use client'

import { DollarSign, TrendingUp, Users, CreditCard, Eye } from 'lucide-react'
import { useState } from 'react'

export default function Payments() {
  const [transactions] = useState([
    { id: '#TX001', user: 'Sarah Photography', amount: 4999, date: '2024-01-15', method: 'MTN Mobile Money', status: 'Completed' },
    { id: '#TX002', user: 'Tech Events', amount: 12499, date: '2024-01-14', method: 'MTN Mobile Money', status: 'Completed' },
    { id: '#TX003', user: 'John Studio', amount: 4999, date: '2024-01-13', method: 'MTN Mobile Money', status: 'Completed' },
    { id: '#TX004', user: 'Emma Portraits', amount: 0, date: '2024-01-12', method: 'Free Trial', status: 'Active' },
    { id: '#TX005', user: 'Fashion Brand', amount: 4999, date: '2024-01-11', method: 'MTN Mobile Money', status: 'Failed' },
  ])

  const stats = [
    { label: 'Total Revenue', value: 'RWF 4.2M', change: '+28% this month', icon: DollarSign },
    { label: 'Active Subscriptions', value: '1,245', change: '+45 new', icon: Users },
    { label: 'Monthly Recurring', value: 'RWF 2.8M', change: 'From active subs', icon: TrendingUp },
    { label: 'Payment Success Rate', value: '96.2%', change: '+2.1% vs last month', icon: CreditCard }
  ]

  return (
    <div className="flex min-h-screen bg-background">
      

      <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6">
          <h1 className="text-3xl font-bold text-foreground">Payment Management</h1>
          <p className="text-muted-foreground mt-1">Monitor transactions and subscription revenue including MTN Mobile Money payments</p>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Stats */}
          <div className="grid md:grid-cols-4 gap-6 mb-8">
            {stats.map((stat, idx) => {
              const Icon = stat.icon
              return (
                <div key={idx} className="bg-card border border-border rounded-lg p-6">
                  <div className="flex items-start justify-between mb-4">
                    <div>
                      <p className="text-sm text-muted-foreground">{stat.label}</p>
                      <h3 className="text-2xl font-bold text-foreground mt-1">{stat.value}</h3>
                    </div>
                    <Icon className="w-8 h-8 text-primary opacity-50" />
                  </div>
                  <p className="text-xs text-accent">{stat.change}</p>
                </div>
              )
            })}
          </div>

          {/* Payment Methods */}
          <div className="grid md:grid-cols-3 gap-6 mb-8">
            <div className="bg-card border border-border rounded-lg p-6">
              <h3 className="font-bold text-foreground mb-4">MTN Mobile Money</h3>
              <p className="text-3xl font-bold text-primary mb-2">3.9M RWF</p>
              <p className="text-sm text-muted-foreground">1,024 transactions</p>
              <button className="w-full mt-4 px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm">
                Configure
              </button>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h3 className="font-bold text-foreground mb-4">Subscription Plans</h3>
              <p className="text-3xl font-bold text-primary mb-2">1,245</p>
              <p className="text-sm text-muted-foreground">Active subscriptions</p>
              <button className="w-full mt-4 px-4 py-2 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm">
                View Details
              </button>
            </div>

            <div className="bg-card border border-border rounded-lg p-6">
              <h3 className="font-bold text-foreground mb-4">Payouts</h3>
              <p className="text-3xl font-bold text-primary mb-2">2.1M RWF</p>
              <p className="text-sm text-muted-foreground">Pending to artists</p>
              <button className="w-full mt-4 px-4 py-2 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm">
                Process Payouts
              </button>
            </div>
          </div>

          {/* Recent Transactions */}
          <div className="bg-card border border-border rounded-lg overflow-hidden">
            <div className="p-6 border-b border-border">
              <h2 className="text-xl font-bold text-foreground">Recent Transactions</h2>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-secondary/50">
                  <tr>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Transaction ID</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">User</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Amount</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Method</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Date</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Status</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {transactions.map((tx) => (
                    <tr key={tx.id} className="border-t border-border hover:bg-secondary/30 transition-colors">
                      <td className="py-4 px-4 text-foreground font-mono text-sm">{tx.id}</td>
                      <td className="py-4 px-4 text-foreground font-medium">{tx.user}</td>
                      <td className="py-4 px-4 text-primary font-bold">RWF {tx.amount.toLocaleString()}</td>
                      <td className="py-4 px-4 text-muted-foreground text-sm">{tx.method}</td>
                      <td className="py-4 px-4 text-muted-foreground text-sm">{tx.date}</td>
                      <td className="py-4 px-4">
                        <span className={`text-xs font-semibold px-3 py-1 rounded-full ${
                          tx.status === 'Completed'
                            ? 'bg-green-100 text-green-700'
                            : tx.status === 'Active'
                            ? 'bg-blue-100 text-blue-700'
                            : 'bg-red-100 text-red-700'
                        }`}>
                          {tx.status}
                        </span>
                      </td>
                      <td className="py-4 px-4">
                        <button className="flex items-center gap-2 px-3 py-2 bg-secondary text-foreground rounded-lg hover:bg-muted transition-colors font-semibold text-sm">
                          <Eye size={16} />
                          Details
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}
