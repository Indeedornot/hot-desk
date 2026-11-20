import { Component, inject, OnInit, signal } from '@angular/core';
import { DatePipe, JsonPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService, errorMessage } from './api.service';
import { AuditEvent, Page } from './models';

@Component({
  standalone: true,
  imports: [DatePipe, JsonPipe, FormsModule],
  template: `
    <div class="page-heading">
      <div>
        <p class="breadcrumb">{{ admin ? 'Administration' : 'Workspace' }} / Activity</p>
        <h1>{{ admin ? 'Event history' : 'My activity' }}</h1>
        <p class="muted">Reservations, arrivals and departures, in one timeline.</p>
      </div>
    </div>
    @if (error()) {
      <p class="error-box" role="alert">{{ error() }}</p>
    }
    <div class="list-toolbar">
      <label
        >Activity type<select [(ngModel)]="type" (ngModelChange)="page = 1; load()">
          <option value="">All activity</option>
          <option value="booking">Reservations</option>
          <option value="attendance">Check-ins and check-outs</option>
          <option value="administration">Administration</option>
        </select></label
      >
      @if (bookingId) {
        <span class="badge">Reservation #{{ bookingId }}</span>
      }
      <button class="button secondary" (click)="load()">Refresh</button>
    </div>
    @if (loading()) {
      <div class="empty-state" role="status">Loading activity…</div>
    } @else if (!result()?.items?.length) {
      <div class="empty-state">
        <h2>No activity yet</h2>
        <p>Actions will appear here as they happen.</p>
      </div>
    } @else {
      <div class="event-timeline">
        @for (event of result()!.items; track event.id) {
          <article class="event-row">
            <span class="event-dot" [class.attendance]="event.event_type === 'attendance'"></span>
            <div class="event-content">
              <div class="event-title">
                <strong>{{ title(event.event_name) }}</strong><time>{{
                  event.event_date | date: 'd MMM y, HH:mm:ss'
                }}</time>
              </div>
              <p>
                {{ event.actorName }}
                @if (event.event_data.deskLabel) {
                  <span> · Desk {{ event.event_data.deskLabel }}</span>
                }
                @if (event.event_data.date) {
                  <span> · {{ event.event_data.date }}</span>
                }
              </p>
              @if (admin) {
                <details>
                  <summary>Event details</summary>
                  <pre>{{ event.event_data | json }}</pre>
                  <small>{{ event.event_name }} / {{ event.event_type }}</small>
                </details>
              }
            </div>
          </article>
        }
      </div>
      <div class="pagination">
        <span>{{ result()!.total }} events</span
        ><button class="button secondary" [disabled]="page === 1" (click)="page = page - 1; load()">
          Previous</button
        ><span>Page {{ page }}</span
        ><button
          class="button secondary"
          [disabled]="page * 20 >= result()!.total"
          (click)="page = page + 1; load()"
        >
          Next
        </button>
      </div>
    }
  `,
})
export class EventsPage implements OnInit {
  private readonly api = inject(ApiService);
  private readonly route = inject(ActivatedRoute);
  readonly admin = inject(Router).url.startsWith('/events');
  readonly result = signal<Page<AuditEvent> | null>(null);
  readonly loading = signal(true);
  readonly error = signal('');
  readonly bookingId = this.route.snapshot.queryParamMap.get('bookingId') ?? '';
  type = '';
  page = 1;
  ngOnInit(): void {
    void this.load();
  }
  title(name: string): string {
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
  async load(): Promise<void> {
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
