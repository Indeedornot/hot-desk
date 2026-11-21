import { Component, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { SessionService } from '../auth/session.service';
import { errorMessage } from '../shared/api.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './app.component.html',
})
export class App {
  public readonly session = inject(SessionService);
  public readonly error = signal('');
  public async logout(): Promise<void> {
    try {
      await this.session.logout();
    } catch (error) {
      this.error.set(errorMessage(error));
    }
  }
}
