import { inject, Injectable } from '@angular/core';
import { ApiService } from './api.service';
import { Availability, Booking, Office, Page } from './models';

export type BookingAction = 'check-in' | 'check-out' | 'cancel';

export interface BookingDraft {
  deskId: number;
  date: string;
}

@Injectable({ providedIn: 'root' })
export class WorkspaceApiService {
  private readonly api = inject(ApiService);

  public async offices(): Promise<Office[]> {
    const result = await this.api.get<{ items: Office[] }>('/offices');
    return result.items;
  }

  public availability(officeId: number, date: string): Promise<Availability> {
    return this.api.get<Availability>(`/desks?officeId=${officeId}&date=${date}`);
  }

  public bookings(page: number, status: string): Promise<Page<Booking>> {
    return this.api.get<Page<Booking>>(`/bookings?page=${page}&pageSize=10&status=${status}`);
  }

  public reserve(draft: BookingDraft): Promise<Booking> {
    return this.api.request<Booking>('POST', '/bookings', draft);
  }

  public updateBooking(id: number, draft: BookingDraft): Promise<Booking> {
    return this.api.request<Booking>('PUT', `/bookings/${id}`, draft);
  }

  public changeBookingStatus(id: number, action: BookingAction): Promise<Booking> {
    return this.api.request<Booking>('POST', `/bookings/${id}/${action}`, {});
  }
}
