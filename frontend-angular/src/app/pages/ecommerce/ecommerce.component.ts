import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { NgIconComponent } from '@ng-icons/core';

interface PlatformInstance {
  instanceId: string;
  type: string;
  connected: boolean;
  loading: boolean;
  syncing: boolean;
  syncingCustomers: boolean;
  pollingActive: boolean;
  nextSyncIn: number;
  lastSync: string | null;
  lastOrderId: number;
  lastCustomerSync: string | null;
  form: { url: string; apiKey: string };
  errors: any;
  errorMessage: string;
  successMessage: string;
  syncResult: any;
  syncCustomersResult: any;
  pollingInterval: any;
  countdownInterval: any;
}

@Component({
  selector: 'app-ecommerce',
  standalone: true,
  imports: [CommonModule, FormsModule, NgIconComponent],
  templateUrl: './ecommerce.component.html',
  styleUrls: ['./ecommerce.component.scss']
})
export class EcommerceComponent implements OnInit, OnDestroy {

  platformConfigs: any = {
    prestashop: {
      label: 'PrestaShop',
      color: '#DF0067',
      bgColor: '#fff0f5',
      borderColor: '#fecdd3',
      urlPlaceholder: 'http://localhost:8080',
      apiKeyLabel: 'Clé API Webservice',
      apiKeyPlaceholder: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ123456',
      apiKeyHelp: 'Paramètres avancés → Webservice → Ajouter une clé'
    },
    woocommerce: {
      label: 'WooCommerce',
      color: '#7f54b3',
      bgColor: '#f5f0ff',
      borderColor: '#e9d5ff',
      urlPlaceholder: 'https://monshop.com',
      apiKeyLabel: 'Consumer Key:Consumer Secret',
      apiKeyPlaceholder: 'username:mot_de_passe_app',
      apiKeyHelp: 'WordPress → Utilisateurs → Profil → Mots de passe d\'application → format: username:password'
    },
    shopify: {
      label: 'Shopify',
      color: '#96bf48',
      bgColor: '#f0fff4',
      borderColor: '#bbf7d0',
      urlPlaceholder: 'https://monshop.myshopify.com',
      apiKeyLabel: 'Admin API Access Token',
      apiKeyPlaceholder: 'shpat_xxxxxxxxxxxxxxxxxxxx',
      apiKeyHelp: 'Applications → Développer des apps → API credentials → Admin API access token'
    }
  };

  instances: PlatformInstance[] = [];
  platformTypes = ['prestashop', 'woocommerce', 'shopify'];

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() { this.loadStatus(); }

  ngOnDestroy() { this.instances.forEach(i => this.stopPolling(i)); }

  loadStatus() {
    this.ds.getEcommerceStatus().subscribe({
      next: (res) => {
        this.instances = [];
        this.platformTypes.forEach(type => {
          const configs = res[type] ?? [];
          const list = Array.isArray(configs) ? configs : [configs];
          list.forEach((data: any) => {
            if (data && (data.connected || data.shopUrl)) {
              this.instances.push(this.makeInstance(type, data));
            }
          });
        });
        this.platformTypes.forEach(type => {
          if (!this.instances.some(i => i.type === type))
            this.instances.push(this.makeInstance(type));
        });
        this.cdr.detectChanges();
      },
      error: () => {
        this.platformTypes.forEach(type => this.instances.push(this.makeInstance(type)));
        this.cdr.detectChanges();
      }
    });
  }

  makeInstance(type: string, data?: any): PlatformInstance {
    return {
      instanceId:          data?.id ? String(data.id) : `${type}_${Date.now()}_${Math.random()}`,
      type,
      connected:           data?.connected ?? false,
      loading:             false,
      syncing:             false,
      syncingCustomers:    false,
      pollingActive:       false,
      nextSyncIn:          300,
      lastSync:            data?.lastSync ?? null,
      lastOrderId:         data?.lastOrderId ?? 0,
      lastCustomerSync:    data?.lastCustomerSync ?? null,
      form: { url: data?.shopUrl ?? '', apiKey: '' },
      errors:              {},
      errorMessage:        '',
      successMessage:      '',
      syncResult:          null,
      syncCustomersResult: null,
      pollingInterval:     null,
      countdownInterval:   null
    };
  }

  addInstance(type: string) {
    this.instances.push(this.makeInstance(type));
    this.cdr.detectChanges();
  }

  removeInstance(p: PlatformInstance) {
    if (p.connected) this.disconnect(p, true);
    this.stopPolling(p);
    this.instances = this.instances.filter(i => i.instanceId !== p.instanceId);
    this.cdr.detectChanges();
  }

  instancesOfType(type: string): PlatformInstance[] {
    return this.instances.filter(i => i.type === type);
  }

  connectedCount(type: string): number {
    return this.instances.filter(i => i.type === type && i.connected).length;
  }

  validate(p: PlatformInstance): boolean {
    p.errors = {};
    if (!p.form.url?.trim())
      p.errors['url'] = 'L\'URL du shop est obligatoire';
    else if (!p.form.url.startsWith('http'))
      p.errors['url'] = 'L\'URL doit commencer par http:// ou https://';
    if (!p.form.apiKey?.trim())
      p.errors['apiKey'] = 'La clé API est obligatoire';
    else if (p.form.apiKey.length < 8)
      p.errors['apiKey'] = 'La valeur semble trop courte';
    return Object.keys(p.errors).length === 0;
  }

  connect(p: PlatformInstance) {
    if (!this.validate(p)) return;
    p.loading = true;
    p.errorMessage = '';
    p.successMessage = '';
    this.ds.connectEcommerce(p.type, p.form.url, p.form.apiKey).subscribe({
      next: (res) => {
        p.connected = true;
        p.loading = false;
        p.successMessage = res.message;
        if (res.id) p.instanceId = String(res.id);
        this.loadStatus();
        this.cdr.detectChanges();
      },
      error: (err) => {
        p.loading = false;
        p.errorMessage = err?.error?.message || 'Connexion échouée. Vérifiez l\'URL et les credentials.';
        this.cdr.detectChanges();
      }
    });
  }

  disconnect(p: PlatformInstance, silent = false) {
    this.stopPolling(p);
    this.ds.disconnectEcommerce(p.type).subscribe({
      next: () => {
        if (!silent) {
          p.connected = false;
          p.syncResult = null;
          p.syncCustomersResult = null;
          p.successMessage = '';
          p.errorMessage = '';
          p.form.url = '';
          p.form.apiKey = '';
          this.cdr.detectChanges();
        }
      },
      error: () => {}
    });
  }

  syncOrders(p: PlatformInstance) {
    p.syncing = true;
    p.syncResult = null;
    this.ds.syncEcommerceOrders(p.type).subscribe({
      next: (res) => {
        p.syncing = false;
        p.syncResult = res;
        this.loadStatus();
        this.cdr.detectChanges();
      },
      error: () => { p.syncing = false; this.cdr.detectChanges(); }
    });
  }

  syncCustomers(p: PlatformInstance) {
    p.syncingCustomers = true;
    p.syncCustomersResult = null;
    this.ds.syncEcommerceCustomers(p.type).subscribe({
      next: (res) => {
        p.syncingCustomers = false;
        p.syncCustomersResult = res;
        this.loadStatus();
        this.cdr.detectChanges();
      },
      error: () => { p.syncingCustomers = false; this.cdr.detectChanges(); }
    });
  }

  startPolling(p: PlatformInstance) {
    p.pollingActive = true;
    p.nextSyncIn = 300;
    this.syncOrders(p);
    p.pollingInterval   = setInterval(() => { this.syncOrders(p); p.nextSyncIn = 300; }, 300000);
    p.countdownInterval = setInterval(() => {
      if (p.nextSyncIn > 0) { p.nextSyncIn--; this.cdr.detectChanges(); }
    }, 1000);
  }

  stopPolling(p: PlatformInstance) {
    p.pollingActive = false;
    if (p.pollingInterval)   { clearInterval(p.pollingInterval);   p.pollingInterval   = null; }
    if (p.countdownInterval) { clearInterval(p.countdownInterval); p.countdownInterval = null; }
  }

  formatCountdown(p: PlatformInstance): string {
    const m = Math.floor(p.nextSyncIn / 60);
    const s = p.nextSyncIn % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
  }

  getConfig(type: string) { return this.platformConfigs[type]; }
}