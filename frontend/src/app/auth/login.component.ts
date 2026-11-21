import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { SessionService } from './session.service';
import { errorMessage } from '../shared/api.service';

@Component({
  standalone: true,
  imports: [FormsModule],
  templateUrl: './login.component.html',
})
export class LoginPage {
  private readonly session = inject(SessionService);
  private readonly router = inject(Router);
  public email = '';
  public password = '';
  public readonly signingIn = signal(false);
  public readonly error = signal('');
  public constructor() {
    void this.session.initialize().then(() => {
      if (this.session.user()) {
        void this.router.navigateByUrl('/desks');
      }
    });
  }
  public async submit(): Promise<void> {
    if (this.signingIn()) {
      return;
    }
    this.signingIn.set(true);
    this.error.set('');
    try {
      await this.session.login(this.email, this.password);
      await this.router.navigateByUrl('/desks');
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.signingIn.set(false);
    }
  }
}
