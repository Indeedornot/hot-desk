export interface User {
  id: number;
  name: string;
  email: string;
  role: 'ROLE_USER' | 'ROLE_ADMIN';
  active: boolean;
}

export interface Office {
  id: number;
  name: string;
  address: string;
  timezone: string;
  openingTime: string;
  closingTime: string;
  active: boolean;
}

export interface Desk {
  id: number;
  officeId: number;
  officeName: string;
  label: string;
  equipment: string;
  x: number;
  y: number;
  active: boolean;
  available?: boolean;
  reason?: string | null;
  state?: 'available' | 'assigned' | 'reserved' | 'occupied';
  isAssignedToMe?: boolean;
  myBookingId?: number | null;
}

export interface Booking {
  id: number;
  userId: number;
  userName: string;
  deskId: number;
  deskLabel: string;
  officeId: number;
  officeName: string;
  timezone: string;
  date: string;
  status: 'BOOKED' | 'CHECKED_IN' | 'CHECKED_OUT' | 'CANCELLED';
  elapsed: boolean;
  createdAt: string;
  checkedInAt: string | null;
  checkedOutAt: string | null;
  cancelledAt: string | null;
}

export interface Assignment {
  id: number;
  userId: number;
  userName: string;
  deskId: number;
  deskLabel: string;
  officeName: string;
  createdAt: string;
}

export interface AuditEvent {
  id: number;
  actorId: number;
  actorName: string;
  event_name: string;
  event_date: string;
  event_type: string;
  event_data: {
    bookingId?: number;
    userId?: number;
    deskLabel?: string;
    officeName?: string;
    date?: string;
    [key: string]: unknown;
  };
}

export interface Page<T> {
  items: T[];
  total: number;
  page: number;
  pageSize: number;
}

export interface Availability {
  items: Desk[];
  assignedSeat: Assignment | null;
  office: Office;
  date: string;
}
