import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { NgIconComponent } from '@ng-icons/core';

@Component({
  selector: 'app-data-reference',
  standalone: true,
  imports: [CommonModule, FormsModule,NgIconComponent],
  templateUrl: './data-reference.component.html',
  styleUrls: ['./data-reference.component.scss']
})
export class DataReferenceComponent implements OnInit {
  refs: string[] = [];
  newRef = '';
  editRef: string | null = null;
  editValue = '';
  loading = true;

  constructor(private ds: DataService) {}

  ngOnInit() { this.load(); }

  load() {
    this.loading = true;
    this.ds.getConfigObs().subscribe({
      next: (cfg: any) => {
        this.refs = cfg.refs || [];
        this.loading = false;
      },
      error: () => { this.loading = false; }
    });
  }

  add() {
    if (this.newRef.trim()) {
      this.refs.push(this.newRef.trim());
      this.saveRefs();
      this.newRef = '';
    }
  }

  delete(r: string) {
    if (confirm('Supprimer cette référence ?')) {
      this.refs = this.refs.filter(x => x !== r);
      this.saveRefs();
    }
  }

  startEdit(r: string)  { this.editRef = r; this.editValue = r; }

  saveEdit(old: string) {
    if (this.editValue.trim() && this.editValue !== old) {
      this.refs = this.refs.map(x => x === old ? this.editValue.trim() : x);
      this.saveRefs();
    }
    this.editRef = null;
  }

  private saveRefs() {
    this.ds.saveConfigObs({ refs: this.refs } as any).subscribe();
  }
}