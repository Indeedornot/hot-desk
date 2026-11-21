import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import type { Booking, Desk } from '../../shared/models';
import type { BookingDraft } from '../../shared/workspace-api.service';

@Component({
  selector: 'app-booking-editor',
  imports: [FormsModule],
  templateUrl: './booking-editor.component.html',
})
export class BookingEditorComponent {
  @Input({ required: true }) public desks: Desk[] = [];

  @Input({ required: true }) public set record(booking: Booking) {
    this.draft = { deskId: booking.deskId, date: booking.date };
  }

  @Output() public readonly saved = new EventEmitter<BookingDraft>();

  public draft: BookingDraft = { deskId: 0, date: '' };
}
