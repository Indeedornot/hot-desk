import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import type { Desk, Office } from '../../shared/models';

type DeskDraft = Pick<Desk, 'officeId' | 'label' | 'equipment' | 'x' | 'y' | 'active'>;

@Component({
  selector: 'app-desk-editor',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './desk-editor.component.html',
})
export class DeskEditorComponent {
  @Output() public readonly saved = new EventEmitter<DeskDraft>();

  private availableOffices: Office[] = [];

  public draft = this.emptyDraft();

  @Input() public set offices(offices: Office[]) {
    this.availableOffices = offices;
    if (this.draft.officeId === 0) {
      this.draft.officeId = offices[0]?.id ?? 0;
    }
  }

  public get offices(): Office[] {
    return this.availableOffices;
  }

  @Input() public set record(desk: Desk | null) {
    if (desk) {
      this.draft = {
        officeId: desk.officeId,
        label: desk.label,
        equipment: desk.equipment,
        x: desk.x,
        y: desk.y,
        active: desk.active,
      };
      return;
    }

    this.draft = this.emptyDraft();
  }

  private emptyDraft(): DeskDraft {
    return {
      officeId: this.offices[0]?.id ?? 0,
      label: '',
      equipment: '',
      x: 0,
      y: 0,
      active: true,
    };
  }
}
