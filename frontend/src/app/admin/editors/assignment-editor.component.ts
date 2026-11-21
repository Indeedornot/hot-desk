import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Desk, User } from '../../shared/models';

@Component({
  selector: 'app-assignment-editor',
  imports: [FormsModule],
  templateUrl: './assignment-editor.component.html',
})
export class AssignmentEditorComponent {
  @Input() public people: User[] = [];
  @Input() public desks: Desk[] = [];
  @Output() public readonly saved = new EventEmitter<{ userId: number; deskId: number }>();

  public draft = { userId: 0, deskId: 0 };

  public save(): void {
    this.saved.emit({ ...this.draft });
  }
}
