import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { errorMessage, officeDate } from '../shared/api.service';
import { Availability, Desk, Office } from '../shared/models';
import { WorkspaceApiService } from '../shared/workspace-api.service';

@Component({
  standalone: true,
  imports: [FormsModule, RouterLink, DatePipe],
  templateUrl: './desks.component.html',
})
export class DesksPage implements OnInit {
  private readonly api = inject(WorkspaceApiService);
  public readonly offices = signal<Office[]>([]);
  public readonly data = signal<Availability | null>(null);
  public readonly selected = signal<Desk | null>(null);
  public readonly loading = signal(true);
  public readonly reserving = signal(false);
  public readonly error = signal('');
  public readonly notice = signal('');
  public readonly available = computed(
    () => this.data()?.items.filter((desk) => desk.available).length ?? 0,
  );
  public officeId = 0;
  public day = 0;
  public search = '';
  public view: 'map' | 'list' = 'map';
  private requestId = 0;

  public async ngOnInit(): Promise<void> {
    try {
      const offices = await this.api.offices();
      this.offices.set(offices);
      this.officeId = offices[0]?.id ?? 0;
      await this.load();
    } catch (error) {
      this.error.set(errorMessage(error));
      this.loading.set(false);
    }
  }

  public get office(): Office | undefined {
    return this.offices().find((office) => office.id === +this.officeId);
  }
  public get date(): string {
    return officeDate(this.office?.timezone ?? 'Europe/Warsaw', this.day);
  }
  public get desks(): Desk[] {
    const query = this.search.toLowerCase().trim();
    return (
      this.data()?.items.filter((desk) =>
        `${desk.label} ${desk.equipment}`.toLowerCase().includes(query),
      ) ?? []
    );
  }
  public get columns(): string {
    return `repeat(${Math.max(4, ...this.desks.map((desk) => desk.x + 1))}, minmax(65px, 1fr))`;
  }

  public async load(): Promise<void> {
    if (!this.officeId) {
      this.loading.set(false);
      return;
    }
    const requestId = ++this.requestId;
    this.loading.set(true);
    this.error.set('');
    this.selected.set(null);
    try {
      const result = await this.api.availability(this.officeId, this.date);
      if (requestId === this.requestId) {
        this.data.set(result);
      }
    } catch (error) {
      if (requestId === this.requestId) {
        this.error.set(errorMessage(error));
      }
    } finally {
      if (requestId === this.requestId) {
        this.loading.set(false);
      }
    }
  }

  public select(desk: Desk): void {
    this.selected.set(desk);
    this.notice.set('');
  }
  public async book(): Promise<void> {
    const desk = this.selected();
    if (!desk?.available || this.reserving()) {
      return;
    }
    this.reserving.set(true);
    this.error.set('');
    try {
      await this.api.reserve({ deskId: desk.id, date: this.date });
      this.notice.set(
        `Desk ${desk.label} is reserved for ${this.date}. You can check in from My reservations.`,
      );
      await this.load();
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.reserving.set(false);
    }
  }
}
