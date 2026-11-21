import { inject, Injectable, signal } from '@angular/core';
import { ApiService, errorMessage } from '../shared/api.service';
import { Assignment, Booking, Desk, Office, Page, User } from '../shared/models';
import { WorkspaceApiService } from '../shared/workspace-api.service';
import type { BookingAction } from '../shared/workspace-api.service';

export type AdminResource = 'offices' | 'desks' | 'users' | 'assignments' | 'bookings';

interface AdminData {
  offices: Office[];
  desks: Desk[];
  users: Page<User> | null;
  people: User[];
  assignments: Assignment[];
  bookings: Page<Booking> | null;
}

@Injectable()
export class AdminWorkspaceService {
  private readonly api = inject(ApiService);
  private readonly workspaceApi = inject(WorkspaceApiService);
  private readonly rows = signal<AdminData>({
    offices: [],
    desks: [],
    users: null,
    people: [],
    assignments: [],
    bookings: null,
  });
  private readonly isLoading = signal(true);
  private readonly isSaving = signal(false);
  private readonly errorMessage = signal('');
  private readonly successMessage = signal('');
  private requestId = 0;

  public readonly data = this.rows.asReadonly();
  public readonly loading = this.isLoading.asReadonly();
  public readonly saving = this.isSaving.asReadonly();
  public readonly error = this.errorMessage.asReadonly();
  public readonly notice = this.successMessage.asReadonly();

  public clearError(): void {
    this.errorMessage.set('');
  }

  public async load(page: number, query: string): Promise<void> {
    const requestId = ++this.requestId;
    this.isLoading.set(true);
    try {
      const [offices, desks, users, people, assignments, bookings] = await Promise.all([
        this.api.get<{ items: Office[] }>('/admin/offices'),
        this.api.get<{ items: Desk[] }>('/admin/desks'),
        this.api.get<Page<User>>(
          `/admin/users?page=${page}&pageSize=10&q=${encodeURIComponent(query)}`,
        ),
        this.api.get<Page<User>>('/admin/users?pageSize=100'),
        this.api.get<{ items: Assignment[] }>('/admin/assignments'),
        this.api.get<Page<Booking>>(`/admin/bookings?page=${page}&pageSize=10`),
      ]);

      if (requestId === this.requestId) {
        this.rows.set({
          offices: offices.items,
          desks: desks.items,
          users,
          people: people.items,
          assignments: assignments.items,
          bookings,
        });
      }
    } catch (error) {
      if (requestId === this.requestId) {
        this.errorMessage.set(errorMessage(error));
      }
    } finally {
      if (requestId === this.requestId) {
        this.isLoading.set(false);
      }
    }
  }

  public save(resource: AdminResource, id: number | null, body: unknown): Promise<boolean> {
    const path = resource === 'bookings' ? '/bookings' : '/admin/' + resource;
    const method = id === null ? 'POST' : 'PUT';
    const url = id === null ? path : `${path}/${id}`;
    return this.change(() => this.api.request(method, url, body), 'Changes saved.');
  }

  public remove(resource: AdminResource, id: number): Promise<boolean> {
    const message = resource === 'assignments' ? 'Assignment removed.' : 'Deleted.';
    return this.change(() => this.api.request('DELETE', `/admin/${resource}/${id}`), message);
  }

  public changeBookingStatus(id: number, action: BookingAction): Promise<boolean> {
    return this.change(
      () => this.workspaceApi.changeBookingStatus(id, action),
      'Reservation updated.',
    );
  }

  private async change(request: () => Promise<unknown>, notice: string): Promise<boolean> {
    if (this.isSaving()) {
      return false;
    }

    this.isSaving.set(true);
    this.clearError();
    this.successMessage.set('');
    try {
      await request();
      this.successMessage.set(notice);
      return true;
    } catch (error) {
      this.errorMessage.set(errorMessage(error));
      return false;
    } finally {
      this.isSaving.set(false);
    }
  }
}
