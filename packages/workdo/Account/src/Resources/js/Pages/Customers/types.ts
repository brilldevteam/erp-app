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
  preferred_currency_code?: string;
  billing_address: Address;
  shipping_address: Address;
  same_as_billing: boolean;
  notes?: string;
  creator_id: number;
  created_by: number;
  created_at: string;
  updated_at: string;
  outstanding_base?: number | string;
  overdue_base?: number | string;
  last_activity_at?: string | null;
  attachments?: PartyAttachment[];
  user?: {
    id: number;
    name: string;
    email?: string | null;
    preferred_currency_code?: string | null;
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

export interface WorkspaceTransaction {
  id: number;
  type: string;
  number: string;
  date: string;
  due_date?: string | null;
  status: string;
  currency: string;
  total: number | string;
  balance: number | string;
  base_total?: number | string;
  debit?: number;
  credit?: number;
  running_balance?: number;
}

export interface CustomerWorkspaceData {
  customer: Customer;
  tab: 'overview' | 'transactions' | 'statement' | 'activity' | 'details';
  summary: Record<string, number | string | null> & { base_currency: string };
  aging: Record<string, number>;
  currency_breakdown: Array<{ currency: string; outstanding: number; credits: number }>;
  revenue_trend: Array<{ month: string; value: number }>;
  attention_items: Array<{ tone: string; title: string; detail: string }>;
  available_actions: Record<string, boolean>;
  transactions?: PaginationData<WorkspaceTransaction> | null;
  statement?: { data: WorkspaceTransaction[]; current_page: number; last_page: number; total: number; opening_balance: number; closing_balance: number } | null;
  activity?: Array<{ id: string; type: string; title: string; detail?: string | null; occurred_at: string }> | null;
}

export interface PaginationData<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page?: number;
  total: number;
  from?: number | null;
  to?: number | null;
}

export interface CustomerFormData {
  company_name: string;
  preferred_currency_code: string;
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
  portal_access_enabled: boolean;
  password: string;
  password_confirmation: string;
  attachments: File[];
}
