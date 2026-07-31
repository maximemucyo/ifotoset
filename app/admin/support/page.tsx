'use client'

import { MessageSquare, AlertCircle, CheckCircle, Clock, Eye } from 'lucide-react'
import { useState } from 'react'

export default function SupportTickets() {
  const [tickets] = useState([
    { id: '#TK001', subject: 'Gallery not loading properly', user: 'Sarah Photography', priority: 'Urgent', status: 'Open', date: '2024-01-15', replies: 3 },
    { id: '#TK002', subject: 'Payment not received', user: 'Tech Events', priority: 'High', status: 'Open', date: '2024-01-14', replies: 1 },
    { id: '#TK003', subject: 'How to add custom domain?', user: 'Emma Portraits', priority: 'Low', status: 'Resolved', date: '2024-01-13', replies: 5 },
    { id: '#TK004', subject: 'API integration question', user: 'John Studio', priority: 'Medium', status: 'In Progress', date: '2024-01-12', replies: 2 },
    { id: '#TK005', subject: 'Export galleries feature request', user: 'Fashion Brand', priority: 'Low', status: 'Open', date: '2024-01-11', replies: 0 },
  ])

  const getPriorityColor = (priority: string) => {
    switch(priority) {
      case 'Urgent': return 'bg-red-100 text-red-700'
      case 'High': return 'bg-orange-100 text-orange-700'
      case 'Medium': return 'bg-yellow-100 text-yellow-700'
      case 'Low': return 'bg-blue-100 text-blue-700'
      default: return 'bg-gray-100 text-gray-700'
    }
  }

  const getStatusIcon = (status: string) => {
    switch(status) {
      case 'Open': return <AlertCircle size={16} />
      case 'In Progress': return <Clock size={16} />
      case 'Resolved': return <CheckCircle size={16} />
      default: return <MessageSquare size={16} />
    }
  }

  const getStatusColor = (status: string) => {
    switch(status) {
      case 'Open': return 'bg-red-100 text-red-700'
      case 'In Progress': return 'bg-yellow-100 text-yellow-700'
      case 'Resolved': return 'bg-green-100 text-green-700'
      default: return 'bg-gray-100 text-gray-700'
    }
  }

  return (
    <div className="flex min-h-screen bg-background">
      

      <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6">
          <h1 className="text-3xl font-bold text-foreground">Support Tickets</h1>
          <p className="text-muted-foreground mt-1">Manage user support requests and inquiries</p>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Stats */}
          <div className="grid md:grid-cols-4 gap-4 mb-8">
            {[
              { label: 'Open Tickets', value: 3, icon: AlertCircle, color: 'text-red-600' },
              { label: 'In Progress', value: 1, icon: Clock, color: 'text-yellow-600' },
              { label: 'Resolved', value: 1, icon: CheckCircle, color: 'text-green-600' },
              { label: 'Avg Response', value: '2h', icon: MessageSquare, color: 'text-blue-600' }
            ].map((stat, idx) => {
              const Icon = stat.icon
              return (
                <div key={idx} className="bg-card border border-border rounded-lg p-4">
                  <div className="flex items-center gap-3 mb-2">
                    <Icon className={`w-5 h-5 ${stat.color}`} />
                    <p className="text-sm text-muted-foreground">{stat.label}</p>
                  </div>
                  <p className="text-2xl font-bold text-foreground">{stat.value}</p>
                </div>
              )
            })}
          </div>

          {/* Tickets Table */}
          <div className="bg-card border border-border rounded-lg overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-secondary/50">
                  <tr>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Ticket ID</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Subject</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">User</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Priority</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Status</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Date</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Replies</th>
                    <th className="text-left py-3 px-4 text-sm font-semibold text-muted-foreground">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {tickets.map((ticket) => (
                    <tr key={ticket.id} className="border-t border-border hover:bg-secondary/30 transition-colors">
                      <td className="py-4 px-4 text-foreground font-mono text-sm">{ticket.id}</td>
                      <td className="py-4 px-4 text-foreground font-medium">{ticket.subject}</td>
                      <td className="py-4 px-4 text-muted-foreground text-sm">{ticket.user}</td>
                      <td className="py-4 px-4">
                        <span className={`text-xs font-semibold px-3 py-1 rounded-full ${getPriorityColor(ticket.priority)}`}>
                          {ticket.priority}
                        </span>
                      </td>
                      <td className="py-4 px-4">
                        <span className={`flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full w-fit ${getStatusColor(ticket.status)}`}>
                          {getStatusIcon(ticket.status)}
                          {ticket.status}
                        </span>
                      </td>
                      <td className="py-4 px-4 text-muted-foreground text-sm">{ticket.date}</td>
                      <td className="py-4 px-4 text-foreground font-medium">{ticket.replies}</td>
                      <td className="py-4 px-4">
                        <button className="flex items-center gap-2 px-3 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm">
                          <Eye size={16} />
                          View
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
