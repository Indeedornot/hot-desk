import { Component, inject, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { DatePipe, NgTemplateOutlet } from '@angular/common';
import { AdminWorkspaceService } from './admin-workspace.service';
import type { AdminResource } from './admin-workspace.service';
import { Assignment, Booking, Desk, Office, User } from '../shared/models';
import type { BookingAction } from '../shared/workspace-api.service';

import { AssignmentEditorComponent } from './editors/assignment-editor.component';
import { BookingEditorComponent } from './editors/booking-editor.component';
import { DeskEditorComponent } from './editors/desk-editor.component';
import { OfficeEditorComponent } from './editors/office-editor.component';
import { UserEditorComponent } from './editors/user-editor.component';

type Editor =
  | { resource: 'offices'; record: Office | null }
  | { resource: 'desks'; record: Desk | null }
  | { resource: 'users'; record: User | null }
  | { resource: 'bookings'; record: Booking }
  | { resource: 'assignments'; record: null };

@Component({
  standalone: true,
  imports: [
    FormsModule,
    DatePipe,
    NgTemplateOutlet,
    AssignmentEditorComponent,
    BookingEditorComponent,
    DeskEditorComponent,
    OfficeEditorComponent,
    UserEditorComponent,
  ],
  templateUrl: './admin.component.html',
  providers: [AdminWorkspaceService],
})
export class AdminPage implements OnInit {
  public readonly workspace = inject(AdminWorkspaceService);
  public tab: AdminResource = 'offices';
  public editor: Editor | null = null;
  public page = 1;
  public query = '';

  public ngOnInit(): void {
    void this.load();
  }

  public switchTab(tab: AdminResource): void {
    this.tab = tab;
    this.page = 1;
    this.query = '';
    this.editor = null;
    this.workspace.clearError();
    void this.load();
  }

  public load(): Promise<void> {
    return this.workspace.load(this.page, this.query);
  }

  public openNew(): void {
    if (this.tab !== 'bookings') {
      this.workspace.clearError();
      this.editor = { resource: this.tab, record: null };
    }
  }

  public editOffice(office: Office): void {
    this.editor = { resource: 'offices', record: office };
  }

  public editDesk(desk: Desk): void {
    this.editor = { resource: 'desks', record: desk };
  }

  public editUser(user: User): void {
    this.editor = { resource: 'users', record: user };
  }

  public editBooking(booking: Booking): void {
    this.editor = { resource: 'bookings', record: booking };
  }

  public async save(editor: Editor, body: unknown): Promise<void> {
    if (await this.workspace.save(editor.resource, editor.record?.id ?? null, body)) {
      if (this.editor === editor) {
        this.editor = null;
      }
      await this.load();
    }
  }

  public async remove(id: number, label: string): Promise<void> {
    if (
      !confirm(
        `Delete ${label}? Existing history will be preserved; resources with dependent records cannot be deleted.`,
      )
    ) {
      return;
    }
    if (await this.workspace.remove(this.tab, id)) {
      await this.load();
    }
  }
  public async unassign(assignment: Assignment): Promise<void> {
    if (
      !confirm(`Remove ${assignment.userName}'s permanent assignment to ${assignment.deskLabel}?`)
    ) {
      return;
    }
    if (await this.workspace.remove('assignments', assignment.id)) {
      await this.load();
    }
  }
  public async bookingAction(booking: Booking, action: BookingAction): Promise<void> {
    if (
      !confirm(`${action === 'cancel' ? 'Cancel' : 'Check out'} ${booking.userName}'s reservation?`)
    ) {
      return;
    }
    if (await this.workspace.changeBookingStatus(booking.id, action)) {
      await this.load();
    }
  }
  public get total(): number {
    const { users, bookings } = this.workspace.data();
    return (this.tab === 'users' ? users?.total : bookings?.total) ?? 0;
  }
}
