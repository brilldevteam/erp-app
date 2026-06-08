import { RefreshCw } from 'lucide-react';

export interface SettingMenuItem {
  order: number;
  title: string;
  href: string;
  icon: any;
  permission: string;
  component: string;
}

export const getRecurringInvoiceBillCompanySettings = (t: (key: string) => string): SettingMenuItem[] => [
  {
    order: 115,
    title: t('Recurring Invoice Settings'),
    href: '#company-recurring-settings',
    icon: RefreshCw,
    permission: 'manage-recurring-invoice-bill',
    component: 'company-recurring-settings'
  }
];
