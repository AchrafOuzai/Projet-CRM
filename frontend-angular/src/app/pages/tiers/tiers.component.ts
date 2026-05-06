import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';

export interface Tiers {
  id?: number;
  referent?: string;
  nom: string;
  nomAlternatif: string;
  codeBarres: string;
  codeClient: string;
  compteComptableClient: string;
  commerciaux: string;
  codePostal: string;
  typeTiers: string[];
  telephone: string;
  natureTiers: string;
  etat: string;
  email?: string;
  adresse?: string;
  ville?: string;
  pays?: string;
  source?: string;
}

export interface Contact {
  id?: number;
  nom: string;
  prenom: string;
  telephone: string;
  telPortable: string;
  email: string;
  nomAlternatif: string;
  visibilite: string;
  etat: string;
  tiersId?: number;
  tiersNom?: string;
}

@Component({
  selector: 'app-tiers',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './tiers.component.html',
  styleUrls: ['./tiers.component.scss']
})
export class TiersComponent implements OnInit {
  tiers: Tiers[]    = [];
  filtered: Tiers[] = [];
  loading           = true;
  showModal         = false;
  editMode          = false;
  search            = '';
  filterType        = '';
  filterEtat        = '';
  filterSource      = '';
  form: Tiers       = this.empty();
  errors: any       = {};

  contacts: Contact[]  = [];
  contactForm: Contact = this.emptyContact();
  showContactForm      = false;
  editContactMode      = false;
  editContactIndex     = -1;

  etats       = ['Actif', 'Inactif'];
  natures     = ['Particulier', 'Professionnel'];
  visibilites = ['Publique', 'Privée'];
  sources     = ['manuel', 'prestashop', 'woocommerce'];

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() { this.load(); }

  load() {
    this.loading = true;
    this.ds.getTiersObs().subscribe({
      next: (data) => {
        this.tiers    = data || [];
        this.filtered = [...this.tiers];
        this.applyFilters();
        this.loading  = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  applyFilters() {
    this.filtered = this.tiers.filter(t => {
      const s           = this.search.toLowerCase();
      const matchSearch = !s
        || (t.nom        || '').toLowerCase().includes(s)
        || (t.telephone  || '').includes(s)
        || (t.email      || '').toLowerCase().includes(s)
        || (t.ville      || '').toLowerCase().includes(s)
        || (t.pays       || '').toLowerCase().includes(s)
        || (t.codeClient || '').toLowerCase().includes(s)
        || (t.referent   || '').toLowerCase().includes(s);
      const matchType   = !this.filterType   || (t.typeTiers || []).includes(this.filterType);
      const matchEtat   = !this.filterEtat   || t.etat   === this.filterEtat;
      const matchSource = !this.filterSource || t.source === this.filterSource;
      return matchSearch && matchType && matchEtat && matchSource;
    });
  }

  openAdd() {
    this.form            = this.empty();
    this.contacts        = [];
    this.errors          = {};
    this.editMode        = false;
    this.showModal       = true;
    this.showContactForm = false;
  }

  openEdit(t: Tiers) {
    this.form            = { ...t, typeTiers: [...(t.typeTiers || [])] };
    this.errors          = {};
    this.editMode        = true;
    this.showModal       = true;
    this.showContactForm = false;
    this.ds.getContactsObs(t.id).subscribe({
      next: (data) => { this.contacts = data || []; this.cdr.detectChanges(); },
      error: () => {}
    });
  }

  closeModal() { this.showModal = false; }

  toggleType(type: string) {
    const idx = this.form.typeTiers.indexOf(type);
    if (idx === -1) this.form.typeTiers.push(type);
    else            this.form.typeTiers.splice(idx, 1);
  }

  isTypeChecked(type: string): boolean { return this.form.typeTiers.includes(type); }

  validate(): boolean {
    this.errors = {};
    if (!this.form.nom?.trim())
      this.errors['nom'] = 'Le nom est obligatoire';
    if (!this.form.typeTiers || this.form.typeTiers.length === 0)
      this.errors['typeTiers'] = 'Sélectionner au moins un type';
    if (this.form.telephone && !/^[0-9+\s]{6,20}$/.test(this.form.telephone))
      this.errors['telephone'] = 'Téléphone invalide';
    if (this.form.codePostal && !/^[0-9]{4,10}$/.test(this.form.codePostal))
      this.errors['codePostal'] = 'Code postal invalide';
    return Object.keys(this.errors).length === 0;
  }

  save() {
    if (!this.validate()) return;
    const obs = this.editMode
      ? this.ds.updateTiersObs(this.form)
      : this.ds.addTiersObs(this.form);
    obs.subscribe({
      next: (tiers: any) => {
        this.saveContacts(tiers.id);
        this.load();
        this.closeModal();
      },
      error: (err) => console.error(err)
    });
  }

  saveContacts(tiersId: number) {
    this.contacts.filter(c => !c.id).forEach(c => {
      c.tiersId = tiersId;
      this.ds.addContactObs(c).subscribe();
    });
  }

  openContactForm() {
    this.contactForm     = this.emptyContact();
    this.editContactMode = false;
    this.showContactForm = true;
  }

  editContact(i: number) {
    this.contactForm      = { ...this.contacts[i] };
    this.editContactMode  = true;
    this.editContactIndex = i;
    this.showContactForm  = true;
  }

  saveContact() {
    if (!this.contactForm.nom?.trim()) return;
    if (this.editContactMode && this.editContactIndex >= 0) {
      this.contacts[this.editContactIndex] = { ...this.contactForm };
      if (this.contactForm.id) this.ds.updateContactObs(this.contactForm).subscribe();
    } else {
      this.contacts.push({ ...this.contactForm });
    }
    this.showContactForm = false;
    this.cdr.detectChanges();
  }

  removeContact(i: number) {
    const c = this.contacts[i];
    if (c.id) this.ds.deleteContactObs(c.id).subscribe();
    this.contacts.splice(i, 1);
    this.cdr.detectChanges();
  }

  delete(id: number) {
    if (confirm('Supprimer ce tiers et tous ses contacts ?')) {
      this.ds.deleteTiersObs(id).subscribe({
        next: () => this.load(),
        error: (err) => console.error(err)
      });
    }
  }

  getSourceBadge(source: string): string {
    const map: any = { 'prestashop': 'info', 'woocommerce': 'expediee', 'manuel': 'confirmee' };
    return map[source] || 'confirmee';
  }

  getSourceIcon(source: string): string {
    const map: any = { 'prestashop': '🛒', 'woocommerce': '🛍️', 'manuel': '✏️' };
    return map[source] || '✏️';
  }

  getTypeBadge(types: string[]): string {
    if (!types || types.length === 0) return '—';
    return types.join(' + ');
  }

  empty(): Tiers {
    return {
      nom: '', nomAlternatif: '', codeBarres: '', codeClient: '',
      compteComptableClient: '', commerciaux: '', codePostal: '',
      typeTiers: [], telephone: '', natureTiers: '', etat: 'Actif',
      email: '', adresse: '', ville: '', pays: '', source: 'manuel'
    };
  }

  emptyContact(): Contact {
    return {
      nom: '', prenom: '', telephone: '', telPortable: '',
      email: '', nomAlternatif: '', visibilite: 'Publique', etat: 'Actif'
    };
  }
}