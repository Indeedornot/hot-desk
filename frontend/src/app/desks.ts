import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ApiService, errorMessage, officeDate } from './api.service';
import { Availability, Desk, Office } from './models';

@Component({
  standalone: true,
  imports: [FormsModule, RouterLink, DatePipe],
  templateUrl: './desks.html',
})
export class DesksPage implements OnInit {
  private readonly api = inject(ApiService);
  readonly offices = signal<Office[]>([]);
  readonly data = signal<Availability | null>(null);
  readonly selected = signal<Desk | null>(null);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal('');
  readonly notice = signal('');
  readonly available = computed(
    () => this.data()?.items.filter((desk) => desk.available).length ?? 0,
  );
  officeId = 0;
  day = 0;
  search = '';
  view: 'map' | 'list' = 'map';
  private requestId = 0;

  async ngOnInit(): Promise<void> {
    try {
      const result = await this.api.get<{ items: Office[] }>('/offices');
      this.offices.set(result.items);
      this.officeId = result.items[0]?.id ?? 0;
      await this.load();
    } catch (error) {
      this.error.set(errorMessage(error));
      this.loading.set(false);
    }
  }

  get office(): Office | undefined {
    return this.offices().find((office) => office.id === +this.officeId);
  }
  get date(): string {
    return officeDate(this.office?.timezone ?? 'Europe/Warsaw', this.day);
  }
  get desks(): Desk[] {
    const query = this.search.toLowerCase().trim();
    return (
      this.data()?.items.filter((desk) =>
        `${desk.label} ${desk.equipment}`.toLowerCase().includes(query),
      ) ?? []
    );
  }
  get columns(): string {
    return `repeat(${Math.max(4, ...this.desks.map((desk) => desk.x + 1))}, minmax(65px, 1fr))`;
  }

  async load(): Promise<void> {
    if (!this.officeId) {
      this.loading.set(false);
      return;
    }
    const requestId = ++this.requestId;
    this.loading.set(true);
    this.error.set('');
    this.selected.set(null);
    try {
      const result = await this.api.get<Availability>(
        `/desks?officeId=${this.officeId}&date=${this.date}`,
      );
      if (requestId === this.requestId) this.data.set(result);
    } catch (error) {
      if (requestId === this.requestId) this.error.set(errorMessage(error));
    } finally {
      if (requestId === this.requestId) this.loading.set(false);
    }
  }

  select(desk: Desk): void {
    this.selected.set(desk);
    this.notice.set('');
  }
  async book(): Promise<void> {
    const desk = this.selected();
    if (!desk?.available || this.busy()) return;
    this.busy.set(true);
    this.error.set('');
    try {
      await this.api.request('POST', '/bookings', { deskId: desk.id, date: this.date });
      this.notice.set(
        `Desk ${desk.label} is reserved for ${this.date}. You can check in from My reservations.`,
      );
      await this.load();
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.busy.set(false);
    }
  }
}
