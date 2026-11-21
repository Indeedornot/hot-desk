import { Component, inject, OnInit, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { map, timer } from 'rxjs';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { errorMessage, officeDate } from '../shared/api.service';
import { Booking, Desk, Page } from '../shared/models';
import { WorkspaceApiService } from '../shared/workspace-api.service';
import type { BookingAction, BookingDraft } from '../shared/workspace-api.service';

@Component({
  standalone: true,
  imports: [DatePipe, FormsModule, RouterLink],
  templateUrl: './bookings.component.html',
})
export class BookingsPage implements OnInit {
  private readonly api = inject(WorkspaceApiService);
  public readonly now = toSignal(timer(0, 1000).pipe(map(() => Date.now())), {
    initialValue: Date.now(),
  });
  public readonly result = signal<Page<Booking> | null>(null);
  public readonly loading = signal(true);
  public readonly saving = signal(false);
  public readonly error = signal('');
  public readonly notice = signal('');
  public readonly editing = signal<number | null>(null);
  public readonly editDesks = signal<Desk[]>([]);
  public editDraft: BookingDraft = { deskId: 0, date: '' };
  public readonly filters = { status: '', page: 1 };
  public ngOnInit(): void {
    void this.load();
  }
  public today(booking: Booking): string {
    return officeDate(booking.timezone);
  }
  public tomorrow(booking: Booking): string {
    return officeDate(booking.timezone, 1);
  }
  public hasStarted(booking: Booking): boolean {
    return this.now() >= Date.parse(booking.startsAt);
  }
  public hasEnded(booking: Booking): boolean {
    return this.now() >= Date.parse(booking.endsAt);
  }
  public label(booking: Booking): string {
    if (this.hasEnded(booking) && booking.status === 'BOOKED') {
      return 'Elapsed';
    }
    return {
      BOOKED: 'Reserved',
      CHECKED_IN: 'Checked in',
      CHECKED_OUT: 'Checked out',
      CANCELLED: 'Cancelled',
    }[booking.status];
  }
  public async load(): Promise<void> {
    this.loading.set(true);
    this.error.set('');
    try {
      this.result.set(await this.api.bookings(this.filters.page, this.filters.status));
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.loading.set(false);
    }
  }
  public async act(booking: Booking, action: BookingAction): Promise<void> {
    if (this.saving()) {
      return;
    }
    if (action === 'cancel' && !confirm(`Cancel your reservation for desk ${booking.deskLabel}?`)) {
      return;
    }
    this.saving.set(true);
    this.error.set('');
    try {
      await this.api.changeBookingStatus(booking.id, action);
      this.notice.set(
        action === 'check-in'
          ? "You're checked in."
          : action === 'check-out'
            ? "You're checked out. Your desk is available again."
            : 'Reservation cancelled.',
      );
      await this.load();
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.saving.set(false);
    }
  }
  public async edit(booking: Booking): Promise<void> {
    this.editing.set(booking.id);
    this.editDraft = { deskId: booking.deskId, date: booking.date };
    await this.loadEditDesks(booking);
  }
  public async loadEditDesks(booking: Booking): Promise<void> {
    try {
      const result = await this.api.availability(booking.officeId, this.editDraft.date);
      this.editDesks.set(result.items);
    } catch (error) {
      this.error.set(errorMessage(error));
    }
  }
  public async saveEdit(booking: Booking): Promise<void> {
    this.saving.set(true);
    this.error.set('');
    try {
      await this.api.updateBooking(booking.id, this.editDraft);
      this.editing.set(null);
      this.notice.set('Reservation updated.');
      await this.load();
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.saving.set(false);
    }
  }
}
