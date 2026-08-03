'use client'

import { Calendar, MapPin, User, DollarSign, CheckCircle, Clock } from 'lucide-react'
import { useState } from 'react'

export default function Bookings() {
  const [bookings] = useState([
    { id: 1, client: 'Sarah & John', service: 'Wedding Package', date: '2024-02-15', location: 'Kigali', price: 375000, status: 'Confirmed', dueDate: 'Feb 15, 2024' },
    { id: 2, client: 'Emma & David', service: 'Pre-wedding', date: '2024-02-10', location: 'Butare', price: 125000, status: 'Confirmed', dueDate: 'Feb 10, 2024' },
    { id: 3, client: 'Tech Company', service: 'Event Photography', date: '2024-02-20', location: 'Kigali', price: 175000, status: 'Pending', dueDate: 'Feb 20, 2024' },
    { id: 4, client: 'Fashion Brand', service: 'Portrait Session', date: '2024-02-08', location: 'Gisenyi', price: 75000, status: 'Completed', dueDate: 'Feb 8, 2024' },
  ])

  const getStatusColor = (status: string) => {
    switch(status) {
      case 'Confirmed': return 'bg-green-100 text-green-700'
      case 'Pending': return 'bg-yellow-100 text-yellow-700'
      case 'Completed': return 'bg-blue-100 text-blue-700'
      default: return 'bg-gray-100 text-gray-700'
    }
  }

  const getStatusIcon = (status: string) => {
    if(status === 'Confirmed' || status === 'Completed') return <CheckCircle size={16} />
    return <Clock size={16} />
  }

  return (
    <main className="flex-1">
        {/* Header */}
        <div className="border-b border-border bg-card p-6">
          <h1 className="text-3xl font-bold text-foreground">Bookings</h1>
          <p className="text-muted-foreground mt-1">View and manage all your photography sessions</p>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Upcoming vs Past Tabs */}
          <div className="flex gap-4 mb-6 border-b border-border">
            <button className="pb-3 px-4 border-b-2 border-primary text-primary font-semibold">
              Upcoming
            </button>
            <button className="pb-3 px-4 text-muted-foreground hover:text-foreground font-semibold">
              Past
            </button>
          </div>

          {/* Bookings List */}
          <div className="space-y-4">
            {bookings.map((booking) => (
              <div key={booking.id} className="bg-card border border-border rounded-lg p-6 hover:border-primary transition-colors">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                  {/* Left Section */}
                  <div className="flex-1">
                    <h3 className="text-lg font-bold text-foreground mb-4">{booking.service}</h3>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                      <div className="flex items-center gap-3">
                        <User className="w-5 h-5 text-primary" />
                        <div>
                          <p className="text-xs text-muted-foreground">Client</p>
                          <p className="font-semibold text-foreground">{booking.client}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-3">
                        <Calendar className="w-5 h-5 text-primary" />
                        <div>
                          <p className="text-xs text-muted-foreground">Date</p>
                          <p className="font-semibold text-foreground">{booking.date}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-3">
                        <MapPin className="w-5 h-5 text-primary" />
                        <div>
                          <p className="text-xs text-muted-foreground">Location</p>
                          <p className="font-semibold text-foreground">{booking.location}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-3">
                        <DollarSign className="w-5 h-5 text-primary" />
                        <div>
                          <p className="text-xs text-muted-foreground">Price</p>
                          <p className="font-semibold text-foreground">RWF {booking.price.toLocaleString()}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Right Section */}
                  <div className="flex flex-col items-end justify-between">
                    <span className={`flex items-center gap-2 text-xs font-bold px-3 py-2 rounded-full ${getStatusColor(booking.status)}`}>
                      {getStatusIcon(booking.status)}
                      {booking.status}
                    </span>
                    <button className="mt-4 px-6 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-accent transition-colors font-semibold text-sm">
                      View Details
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
    </main>
  )
}
