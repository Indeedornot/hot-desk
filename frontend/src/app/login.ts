import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { SessionService } from './session.service';
import { errorMessage } from './api.service';

@Component({
  standalone: true,
  imports: [FormsModule],
  template: `
    <div class="login-page">
      <section class="login-intro">
        <div class="brand light">hot desk.</div>
        <div>
          <h1>Office desk booking</h1>
          <div class="desk-illustration" aria-hidden="true">
            <span></span><span></span><span></span><span></span>
          </div>
        </div>
      </section>
      <section class="login-form-wrap">
        <form class="login-form" (ngSubmit)="submit()">
          <h2>Sign in</h2>
          <p class="muted">Sign in with the account provided by your office administrator.</p>
          <label
            >Email address<input
              type="email"
              name="email"
              [(ngModel)]="email"
              autocomplete="username"
              required
              placeholder="you@company.com" /></label
          ><label
            >Password<input
              type="password"
              name="password"
              [(ngModel)]="password"
              autocomplete="current-password"
              required
          /></label>
          @if (error()) {
            <p class="error-box" role="alert">{{ error() }}</p>
          }
          <button class="button primary full-width" [disabled]="busy()">
            {{ busy() ? 'Signing in…' : 'Sign in' }}
          </button>
          <p class="login-help">Need an account? Contact your office administrator.</p>
        </form>
      </section>
    </div>
  `,
})
export class LoginPage {
  private readonly session = inject(SessionService);
  private readonly router = inject(Router);
  email = '';
  password = '';
  readonly busy = signal(false);
  readonly error = signal('');
  constructor() {
    void this.session.initialize().then(() => {
      if (this.session.user()) void this.router.navigateByUrl('/desks');
    });
  }
  async submit(): Promise<void> {
    if (this.busy()) return;
    this.busy.set(true);
    this.error.set('');
    try {
      await this.session.login(this.email, this.password);
      await this.router.navigateByUrl('/desks');
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.busy.set(false);
    }
  }
}
