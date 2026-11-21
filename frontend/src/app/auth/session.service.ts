import { inject, Injectable, signal } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '../shared/api.service';
import { User } from '../shared/models';

@Injectable({ providedIn: 'root' })
export class SessionService {
  private readonly api = inject(ApiService);
  private readonly router = inject(Router);
  public readonly user = signal<User | null>(null);
  private initialized: Promise<void> | null = null;

  public initialize(): Promise<void> {
    return (this.initialized ??= this.load());
  }

  private async load(): Promise<void> {
    try {
      const result = await this.api.get<{ user: User; csrfToken: string }>('/auth/me');
      this.user.set(result.user);
      this.api.csrfToken = result.csrfToken;
    } catch {
      this.user.set(null);
    }
  }

  public async login(email: string, password: string): Promise<void> {
    await this.api.refreshCsrf();
    const result = await this.api.request<{ user: User; csrfToken: string }>(
      'POST',
      '/auth/login',
      {
        email,
        password,
      },
    );
    this.user.set(result.user);
    this.api.csrfToken = result.csrfToken;
  }

  public async logout(): Promise<void> {
    await this.api.request('POST', '/auth/logout', {});
    this.user.set(null);
    this.api.csrfToken = '';
    await this.router.navigateByUrl('/login');
  }
}
