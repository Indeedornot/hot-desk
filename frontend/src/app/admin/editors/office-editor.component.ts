import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import type { Office } from '../../shared/models';

type OfficeDraft = Omit<Office, 'id'>;

@Component({
  selector: 'app-office-editor',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './office-editor.component.html',
})
export class OfficeEditorComponent {
  @Output() public readonly saved = new EventEmitter<OfficeDraft>();

  public draft = this.emptyDraft();

  @Input() public set record(office: Office | null) {
    if (office) {
      const { id, ...draft } = office;
      this.draft = draft;
      return;
    }

    this.draft = this.emptyDraft();
  }

  private emptyDraft(): OfficeDraft {
    return {
      name: '',
      address: '',
      timezone: 'Europe/Warsaw',
      openingTime: '08:00',
      closingTime: '18:00',
      active: true,
    };
  }
}
