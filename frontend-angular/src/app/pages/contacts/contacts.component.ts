import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { Contact } from '../tiers/tiers.component';

@Component({
  selector: 'app-contacts',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './contacts.component.html',
  styleUrls: ['./contacts.component.scss']
})
export class ContactsComponent implements OnInit {
  contacts: Contact[] = [];
  filtered: Contact[] = [];
  tiers: any[]        = [];
  loading             = true;
  showModal           = false;
  editMode            = false;
  search              = '';
  filterEtat          = '';
  form: Contact       = this.empty();
  errors: any         = {};

  etats       = ['Actif', 'Inactif'];
  visibilites = ['Publique', 'Privée'];

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() {
    this.ds.getTiersObs().subscribe({ next: (data) => { this.tiers = data || []; } });
    this.load();
  }

  load() {
    this.loading = true;
    this.ds.getContactsObs().subscribe({
      next: (data) => {
        this.contacts = data || [];
        this.filtered = [...this.contacts];
        this.applyFilters();
        this.loading  = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  applyFilters() {
    this.filtered = this.contacts.filter(c => {
      const s = this.search.toLowerCase();
      const matchSearch = !s
        || (c.nom       || '').toLowerCase().includes(s)
        || (c.prenom    || '').toLowerCase().includes(s)
        || (c.email     || '').toLowerCase().includes(s)
        || (c.telephone || '').includes(s)
        || (c.tiersNom  || '').toLowerCase().includes(s);
      const matchEtat = !this.filterEtat || c.etat === this.filterEtat;
      return matchSearch && matchEtat;
    });
  }

  openAdd()            { this.form = this.empty(); this.errors = {}; this.editMode = false; this.showModal = true; }
  openEdit(c: Contact) { this.form = { ...c };     this.errors = {}; this.editMode = true;  this.showModal = true; }
  closeModal()         { this.showModal = false; }

  validate(): boolean {
    this.errors = {};
    if (!this.form.nom || this.form.nom.trim() === '')
      this.errors['nom'] = 'Le nom est obligatoire';
    if (!this.form.tiersId)
      this.errors['tiersId'] = 'Le tiers est obligatoire';
    if (this.form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email))
      this.errors['email'] = 'Email invalide';
    return Object.keys(this.errors).length === 0;
  }

  save() {
    if (!this.validate()) return;
    if (this.editMode) {
      this.ds.updateContactObs(this.form).subscribe({
        next: () => { this.load(); this.closeModal(); },
        error: (err) => console.error(err)
      });
    } else {
      this.ds.addContactObs(this.form).subscribe({
        next: () => { this.load(); this.closeModal(); },
        error: (err) => console.error(err)
      });
    }
  }

  delete(id: number) {
    if (confirm('Supprimer ce contact ?')) {
      this.ds.deleteContactObs(id).subscribe({
        next: () => this.load(),
        error: (err) => console.error(err)
      });
    }
  }

  empty(): Contact {
    return { nom: '', prenom: '', telephone: '', telPortable: '', email: '', nomAlternatif: '', visibilite: 'Publique', etat: 'Actif' };
  }
}