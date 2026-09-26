import { Address } from '@/types/address';

export type { Address } from '@/types/address';

export interface Customer {
  id: number;
  user_id?: number;
  customer_code: string;
  company_name: string;
  contact_person_name: string;
  contact_person_email?: string | null;
  contact_person_mobile?: string;
  tax_number?: string;
  payment_terms?: string;
  billing_address: Address;
  shipping_address: Address;
  same_as_billing: boolean;
  notes?: string;
  creator_id: number;
  created_by: number;
  created_at: string;
  updated_at: string;
  user?: {
    id: number;
    name: string;
    email?: string | null;
    is_disable?: number;
    is_enable_login?: boolean;
    avatar?: string;
  };
  creator?: {
    id: number;
    name: string;
  };
  created_by_user?: {
    id: number;
    name: string;
  };
}

export interface CustomerFormData {
  company_name: string;
  contact_person_name: string;
  contact_person_email: string;
  contact_person_mobile?: string;
  tax_number?: string;
  payment_terms?: string;
  billing_address: Address;
  shipping_address: Address;
  same_as_billing: boolean;
  notes?: string;
  portal_access_enabled: boolean;
  password: string;
  password_confirmation: string;
}
