import { RefreshCw } from 'lucide-react';

export interface SettingMenuItem {
  order: number;
  title: string;
  href: string;
  icon: any;
  permission: string;
  component: string;
}

export const getRecurringInvoiceBillSuperAdminSettings = (t: (key: string) => string): SettingMenuItem[] => [
  {
    order: 115,
    title: t('Recurring Invoice Settings'),
    href: '#superadmin-recurring-settings',
    icon: RefreshCw,
    permission: 'manage-recurring-invoice-bill',
    component: 'superadmin-recurring-settings'
  }
];
