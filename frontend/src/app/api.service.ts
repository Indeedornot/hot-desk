import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);
  csrfToken = '';

  async request<T>(method: string, path: string, body?: unknown): Promise<T> {
    return firstValueFrom(
      this.http.request<T>(method, '/api' + path, {
        body,
        headers: this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {},
      }),
    ).catch((error: unknown) => {
      if (
        error instanceof HttpErrorResponse &&
        error.status === 401 &&
        !path.startsWith('/auth/')
      ) {
        window.location.assign('/login');
      }
      throw error;
    });
  }

  get<T>(path: string): Promise<T> {
    return this.request<T>('GET', path);
  }

  async refreshCsrf(): Promise<void> {
    const result = await this.get<{ csrfToken: string }>('/auth/csrf');
    this.csrfToken = result.csrfToken;
  }
}

export function errorMessage(error: unknown): string {
  if (error instanceof HttpErrorResponse) {
    const payload = error.error?.error;
    const fields = payload?.fields as Record<string, string[]> | undefined;
    if (fields && Object.keys(fields).length) {
      return Object.entries(fields)
        .map(([field, messages]) => `${field}: ${messages.join(' ')}`)
        .join(' ');
    }
    return (
      payload?.message || error.error?.message || 'Unable to complete this request. Try again.'
    );
  }
  return 'Unable to connect. Check your connection and try again.';
}

export function officeDate(timezone: string, offset = 0): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date());
  const value = (type: string) => parts.find((part) => part.type === type)!.value;
  const date = new Date(`${value('year')}-${value('month')}-${value('day')}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() + offset);
  return date.toISOString().slice(0, 10);
}
