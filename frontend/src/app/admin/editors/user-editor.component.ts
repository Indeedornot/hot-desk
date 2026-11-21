import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { User } from '../../shared/models';

type UserFields = Omit<User, 'id'>;
type UserPayload = UserFields & { password: string | null };

@Component({
  selector: 'app-user-editor',
  imports: [FormsModule],
  templateUrl: './user-editor.component.html',
})
export class UserEditorComponent {
  public editing = false;
  public draft: UserFields & { password: string } = {
    name: '',
    email: '',
    role: 'ROLE_USER',
    active: true,
    password: '',
  };

  @Input() public set record(user: User | null) {
    this.editing = user !== null;
    this.draft = {
      name: user?.name ?? '',
      email: user?.email ?? '',
      role: user?.role ?? 'ROLE_USER',
      active: user?.active ?? true,
      password: '',
    };
  }

  @Output() public readonly saved = new EventEmitter<UserPayload>();

  public save(): void {
    this.saved.emit({ ...this.draft, password: this.draft.password || null });
  }
}
