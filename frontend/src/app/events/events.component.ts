import { Component, inject, OnInit, signal } from '@angular/core';
import { DatePipe, JsonPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService, errorMessage } from '../shared/api.service';
import { AuditEvent, Page } from '../shared/models';

@Component({
  standalone: true,
  imports: [DatePipe, JsonPipe, FormsModule],
  templateUrl: './events.component.html',
})
export class EventsPage implements OnInit {
  private readonly api = inject(ApiService);
  private readonly route = inject(ActivatedRoute);
  public readonly admin = inject(Router).url.startsWith('/events');
  public readonly result = signal<Page<AuditEvent> | null>(null);
  public readonly loading = signal(true);
  public readonly error = signal('');
  public readonly bookingId = this.route.snapshot.queryParamMap.get('bookingId') ?? '';
  public type = '';
  public page = 1;
  public ngOnInit(): void {
    void this.load();
  }
  public title(name: string): string {
    const names: Record<string, string> = {
      'booking.created': 'Desk reserved',
      'booking.updated': 'Reservation updated',
      'booking.cancelled': 'Reservation cancelled',
      'booking.checked_in': 'Checked in',
      'booking.checked_out': 'Checked out',
      'seat.assigned': 'Permanent desk assigned',
      'seat.unassigned': 'Permanent desk released',
    };
    return names[name] ?? name.replace('.', ' ').replace(/_/g, ' ');
  }
  public async load(): Promise<void> {
    this.loading.set(true);
    this.error.set('');
    try {
      this.result.set(
        await this.api.get<Page<AuditEvent>>(
          `${this.admin ? '/admin' : ''}/events?page=${this.page}&event_type=${encodeURIComponent(this.type)}&bookingId=${encodeURIComponent(this.bookingId)}`,
        ),
      );
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.loading.set(false);
    }
  }
}
