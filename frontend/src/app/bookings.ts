import { Component, inject, OnInit, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { map, timer } from 'rxjs';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ApiService, errorMessage, officeDate } from './api.service';
import { Availability, Booking, Desk, Page } from './models';

@Component({
  standalone: true,
  imports: [DatePipe, FormsModule, RouterLink],
  template: `
    <div class="page-heading">
      <div>
        <p class="breadcrumb">Workspace / Reservations</p>
        <h1>My reservations</h1>
        <p class="muted">Check in when you arrive. Free up your seat when you leave.</p>
      </div>
      <a class="button primary" routerLink="/desks">Find a desk</a>
    </div>
    @if (error()) {
      <p class="error-box" role="alert">{{ error() }}</p>
    }
    @if (notice()) {
      <p class="success-box" role="status">{{ notice() }}</p>
    }
    <div class="list-toolbar">
      <label
        >Status<select [(ngModel)]="status" (ngModelChange)="page = 1; load()">
          <option value="">All reservations</option>
          <option value="BOOKED">Reserved</option>
          <option value="CHECKED_IN">Checked in</option>
          <option value="CHECKED_OUT">Checked out</option>
          <option value="CANCELLED">Cancelled</option>
        </select></label
      ><button class="button secondary" (click)="load()">Refresh</button>
    </div>
    @if (loading()) {
      <div class="empty-state" role="status">Loading reservations…</div>
    } @else if (!result()?.items?.length) {
      <div class="empty-state">
        <h2>No reservations here yet</h2>
        <p>Your next working day starts with a desk.</p>
        <a class="button primary" routerLink="/desks">Choose your desk</a>
      </div>
    } @else {
      <div class="booking-list">
        @for (booking of result()!.items; track booking.id) {
          <article class="booking-card">
            <div class="booking-date">
              <strong>{{ booking.date | date: 'dd' }}</strong><span>{{
                booking.date | date: 'MMM'
              }}</span>
            </div>
            <div class="booking-info">
              <h2>Desk {{ booking.deskLabel }}</h2>
              <p>{{ booking.officeName }}</p>
              <span class="badge" [class.green]="booking.status === 'CHECKED_IN'">{{
                label(booking)
              }}</span>
            </div>
            <div class="booking-actions">
              @if (booking.status === 'BOOKED' && !hasEnded(booking)) {
                @if (hasStarted(booking)) {
                  <button
                    class="button primary"
                    [disabled]="busy()"
                    (click)="act(booking, 'check-in')"
                  >
                    Check in
                  </button>
                } @else {
                  <small
                    >Check-in opens {{ booking.startsAt | date: 'd MMM, HH:mm' }} (local
                    time)</small
                  >
                }
                <button class="button secondary" [disabled]="busy()" (click)="edit(booking)">
                  Edit</button
                ><button
                  class="text-button danger"
                  [disabled]="busy()"
                  (click)="act(booking, 'cancel')"
                >
                  Cancel
                </button>
              }
              @if (booking.status === 'CHECKED_IN') {
                <button
                  class="button primary"
                  [disabled]="busy()"
                  (click)="act(booking, 'check-out')"
                >
                  Check out
                </button>
              }
              <a
                class="text-button"
                [routerLink]="['/activity']"
                [queryParams]="{ bookingId: booking.id }"
                >View activity</a
              >
            </div>
            @if (editing() === booking.id) {
              <form class="booking-edit" (ngSubmit)="saveEdit(booking)">
                <label
                  >Day<select
                    name="editDay"
                    [(ngModel)]="editDate"
                    (ngModelChange)="loadEditDesks(booking)"
                  >
                    <option [value]="today(booking)">Today</option>
                    <option [value]="tomorrow(booking)">Tomorrow</option>
                  </select></label
                ><label
                  >Desk<select name="editDesk" [(ngModel)]="editDeskId">
                    @for (desk of editDesks(); track desk.id) {
                      <option [ngValue]="desk.id">{{ desk.label }}</option>
                    }
                  </select></label
                ><button class="button primary" [disabled]="busy()">Save changes</button
                ><button type="button" class="text-button" (click)="editing.set(null)">
                  Close
                </button>
              </form>
            }
          </article>
        }
      </div>
      <div class="pagination">
        <span>{{ result()!.total }} reservations</span
        ><button class="button secondary" [disabled]="page === 1" (click)="page = page - 1; load()">
          Previous</button
        ><span>Page {{ page }}</span
        ><button
          class="button secondary"
          [disabled]="page * 10 >= result()!.total"
          (click)="page = page + 1; load()"
        >
          Next
        </button>
      </div>
    }
  `,
})
export class BookingsPage implements OnInit {
  private readonly api = inject(ApiService);
  readonly now = toSignal(timer(0, 1000).pipe(map(() => Date.now())), { initialValue: Date.now() });
  readonly result = signal<Page<Booking> | null>(null);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal('');
  readonly notice = signal('');
  readonly editing = signal<number | null>(null);
  readonly editDesks = signal<Desk[]>([]);
  editDate = '';
  editDeskId = 0;
  status = '';
  page = 1;
  ngOnInit(): void {
    void this.load();
  }
  today(booking: Booking): string {
    return officeDate(booking.timezone);
  }
  tomorrow(booking: Booking): string {
    return officeDate(booking.timezone, 1);
  }
  hasStarted(booking: Booking): boolean {
    return this.now() >= Date.parse(booking.startsAt);
  }
  hasEnded(booking: Booking): boolean {
    return this.now() >= Date.parse(booking.endsAt);
  }
  label(booking: Booking): string {
    if (this.hasEnded(booking) && booking.status === 'BOOKED') return 'Elapsed';
    return {
      BOOKED: 'Reserved',
      CHECKED_IN: 'Checked in',
      CHECKED_OUT: 'Checked out',
      CANCELLED: 'Cancelled',
    }[booking.status];
  }
  async load(): Promise<void> {
    this.loading.set(true);
    this.error.set('');
    try {
      this.result.set(
        await this.api.get<Page<Booking>>(
          `/bookings?page=${this.page}&pageSize=10&status=${this.status}`,
        ),
      );
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.loading.set(false);
    }
  }
  async act(booking: Booking, action: string): Promise<void> {
    if (this.busy()) return;
    if (action === 'cancel' && !confirm(`Cancel your reservation for desk ${booking.deskLabel}?`))
      return;
    this.busy.set(true);
    this.error.set('');
    try {
      await this.api.request('POST', `/bookings/${booking.id}/${action}`, {});
      this.notice.set(
        action === 'check-in'
          ? 'You’re checked in.'
          : action === 'check-out'
            ? 'You’re checked out. Your desk is available again.'
            : 'Reservation cancelled.',
      );
      await this.load();
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.busy.set(false);
    }
  }
  async edit(booking: Booking): Promise<void> {
    this.editing.set(booking.id);
    this.editDate = booking.date;
    this.editDeskId = booking.deskId;
    await this.loadEditDesks(booking);
  }
  async loadEditDesks(booking: Booking): Promise<void> {
    try {
      const result = await this.api.get<Availability>(
        `/desks?officeId=${booking.officeId}&date=${this.editDate}`,
      );
      this.editDesks.set(result.items);
    } catch (error) {
      this.error.set(errorMessage(error));
    }
  }
  async saveEdit(booking: Booking): Promise<void> {
    this.busy.set(true);
    this.error.set('');
    try {
      await this.api.request('PUT', `/bookings/${booking.id}`, {
        deskId: this.editDeskId,
        date: this.editDate,
      });
      this.editing.set(null);
      this.notice.set('Reservation updated.');
      await this.load();
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.busy.set(false);
    }
  }
}
