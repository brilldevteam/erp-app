import { FileText } from 'lucide-react';

export interface SettingMenuItem {
  order: number;
  title: string;
  href: string;
  icon: any;
  permission: string;
  component: string;
}

export const getEInvoiceCompanySettings = (t: (key: string) => string): SettingMenuItem[] => [
  {
    order: 710,
    title: t('E-Invoice Settings'),
    href: '#einvoice-settings',
    icon: FileText,
    permission: 'manage-einvoice-settings',
    component: 'einvoice-settings'
  }
];