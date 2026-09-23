import { Address } from '@/types/address';
import { PartyAttachment } from '../Parties/SavedPartyDocuments';

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
  cr_number?: string;
  payment_terms?: string;
  billing_address: Address;
  shipping_address: Address;
  same_as_billing: boolean;
  notes?: string;
  creator_id: number;
  created_by: number;
  created_at: string;
  updated_at: string;
  attachments?: PartyAttachment[];
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
  user_id?: number;
  company_name: string;
  contact_person_name: string;
  contact_person_email: string;
  contact_person_mobile?: string;
  tax_number?: string;
  cr_number?: string;
  payment_terms?: string;
  billing_address: Address;
  shipping_address: Address;
  same_as_billing: boolean;
  notes?: string;
  attachments: File[];
}

export interface User {
  id: number;
  name: string;
  email: string;
  mobile_no?: string;
}
