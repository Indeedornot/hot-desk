import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { DatePipe } from '@angular/common';
import { ApiService, errorMessage } from './api.service';
import { Assignment, Booking, Desk, Office, Page, User } from './models';

type Tab = 'offices' | 'desks' | 'users' | 'assignments' | 'bookings';

@Component({
  standalone: true,
  imports: [FormsModule, DatePipe],
  templateUrl: './admin.html',
})
export class AdminPage implements OnInit {
  private readonly api = inject(ApiService);
  readonly offices = signal<Office[]>([]);
  readonly desks = signal<Desk[]>([]);
  readonly users = signal<Page<User> | null>(null);
  readonly people = signal<User[]>([]);
  readonly assignments = signal<Assignment[]>([]);
  readonly bookings = signal<Page<Booking> | null>(null);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal('');
  readonly notice = signal('');
  tab: Tab = 'offices';
  editor = false;
  editedId: number | null = null;
  page = 1;
  query = '';
  officeDraft = this.emptyOffice();
  deskDraft = this.emptyDesk();
  userDraft = this.emptyUser();
  assignmentDraft = { userId: 0, deskId: 0 };
  bookingDraft = { deskId: 0, date: '' };

  ngOnInit(): void {
    void this.load();
  }
  emptyOffice() {
    return {
      name: '',
      address: '',
      timezone: 'Europe/Warsaw',
      openingTime: '08:00',
      closingTime: '18:00',
      active: true,
    };
  }
  emptyDesk() {
    return {
      officeId: this.offices?.()[0]?.id ?? 0,
      label: '',
      equipment: '',
      x: 0,
      y: 0,
      active: true,
    };
  }
  emptyUser() {
    return { name: '', email: '', password: '', role: 'ROLE_USER', active: true };
  }
  switchTab(tab: Tab): void {
    this.tab = tab;
    this.page = 1;
    this.query = '';
    this.editor = false;
    this.error.set('');
    void this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);
    try {
      const [offices, desks, users, people, assignments, bookings] = await Promise.all([
        this.api.get<{ items: Office[] }>('/admin/offices'),
        this.api.get<{ items: Desk[] }>('/admin/desks'),
        this.api.get<Page<User>>(
          `/admin/users?page=${this.page}&pageSize=10&q=${encodeURIComponent(this.query)}`,
        ),
        this.api.get<Page<User>>('/admin/users?pageSize=100'),
        this.api.get<{ items: Assignment[] }>('/admin/assignments'),
        this.api.get<Page<Booking>>(`/admin/bookings?page=${this.page}&pageSize=10`),
      ]);
      this.offices.set(offices.items);
      this.desks.set(desks.items);
      this.users.set(users);
      this.people.set(people.items);
      this.assignments.set(assignments.items);
      this.bookings.set(bookings);
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.loading.set(false);
    }
  }

  openNew(): void {
    this.editor = true;
    this.editedId = null;
    this.error.set('');
    this.officeDraft = this.emptyOffice();
    this.deskDraft = this.emptyDesk();
    this.userDraft = this.emptyUser();
    this.assignmentDraft = { userId: 0, deskId: 0 };
  }
  editOffice(office: Office): void {
    this.editor = true;
    this.editedId = office.id;
    const { id, ...draft } = office;
    this.officeDraft = draft;
  }
  editDesk(desk: Desk): void {
    this.editor = true;
    this.editedId = desk.id;
    this.deskDraft = {
      officeId: desk.officeId,
      label: desk.label,
      equipment: desk.equipment,
      x: desk.x,
      y: desk.y,
      active: desk.active,
    };
  }
  editUser(user: User): void {
    this.editor = true;
    this.editedId = user.id;
    this.userDraft = {
      name: user.name,
      email: user.email,
      role: user.role,
      active: user.active,
      password: '',
    };
  }
  editBooking(booking: Booking): void {
    this.editor = true;
    this.editedId = booking.id;
    this.bookingDraft = { deskId: booking.deskId, date: booking.date };
  }
  async save(): Promise<void> {
    let body: unknown;
    if (this.tab === 'offices') body = this.officeDraft;
    else if (this.tab === 'desks') body = this.deskDraft;
    else if (this.tab === 'users')
      body = { ...this.userDraft, password: this.userDraft.password || null };
    else if (this.tab === 'bookings') body = this.bookingDraft;
    else body = this.assignmentDraft;
    const path = this.tab === 'bookings' ? '/bookings' : '/admin/' + this.tab;
    await this.run(async () => {
      await this.api.request(
        this.editedId ? 'PUT' : 'POST',
        path + (this.editedId ? '/' + this.editedId : ''),
        body,
      );
      this.editor = false;
    }, 'Changes saved.');
  }
  async remove(id: number, label: string): Promise<void> {
    if (
      !confirm(
        `Delete ${label}? Existing history will be preserved; resources with dependent records cannot be deleted.`,
      )
    )
      return;
    await this.run(() => this.api.request('DELETE', `/admin/${this.tab}/${id}`), 'Deleted.');
  }
  async unassign(assignment: Assignment): Promise<void> {
    if (
      !confirm(`Remove ${assignment.userName}’s permanent assignment to ${assignment.deskLabel}?`)
    )
      return;
    await this.run(
      () => this.api.request('DELETE', `/admin/assignments/${assignment.id}`),
      'Assignment removed.',
    );
  }
  async bookingAction(booking: Booking, action: string): Promise<void> {
    if (
      !confirm(`${action === 'cancel' ? 'Cancel' : 'Check out'} ${booking.userName}’s reservation?`)
    )
      return;
    await this.run(
      () => this.api.request('POST', `/bookings/${booking.id}/${action}`, {}),
      'Reservation updated.',
    );
  }
  private async run(action: () => Promise<unknown>, success: string): Promise<void> {
    if (this.busy()) return;
    this.busy.set(true);
    this.error.set('');
    this.notice.set('');
    try {
      await action();
      this.notice.set(success);
      await this.load();
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.busy.set(false);
    }
  }
  get total(): number {
    return this.tab === 'users' ? (this.users()?.total ?? 0) : (this.bookings()?.total ?? 0);
  }
}
