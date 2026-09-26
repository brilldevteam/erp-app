import { PaginatedData, ModalState, AuthContext } from '@/types/common';

export interface Address {
    name: string;
    address_line_1: string;
    address_line_2?: string;
    city: string;
    state: string;
    country: string;
    zip_code: string;
}

export interface Vendor {
    id: number;
    user_id?: number;
    vendor_code: string;
    company_name: string;
    contact_person_name: string;
    contact_person_email?: string;
    contact_person_mobile?: string;
    primary_email?: string;
    primary_mobile?: string;
    tax_number?: string;
    payment_terms?: string;
    currency_code: string;
    credit_limit?: number;
    billing_address: Address;
    shipping_address: Address;
    same_as_billing: boolean;
    is_active: boolean;
    notes?: string;
    created_at: string;
    project_contracts?: Array<{
        id: number;
        type: 'main' | 'subcontractor';
        scope_of_work: string;
        contract_value: number;
        amount_paid?: number;
        work_start_date: string;
        completion_date: string;
        project?: { id: number; name: string };
    }>;
    user?: {
        id: number;
        name: string;
        avatar?: string;
        is_enable_login?: boolean;
        is_disable?: number;
    };
}

export interface VendorFormData {
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

export interface CreateVendorFormData {
    company_name: string;
    contact_person_name: string;
    contact_person_email: string;
    contact_person_mobile: string;
    tax_number: string;
    payment_terms: string;
    billing_address: Address;
    shipping_address: Address;
    same_as_billing: boolean;
    notes: string;
    return_to?: string;
    portal_access_enabled: boolean;
    password: string;
    password_confirmation: string;
}


export interface VendorFilters {
    company_name: string;
    vendor_code: string;
    contact_person_name: string;
}

export type PaginatedVendors = PaginatedData<Vendor>;
export type VendorModalState = ModalState<Vendor>;

export interface VendorsIndexProps {
    vendors: PaginatedVendors;
    auth: AuthContext;
    openCreate?: boolean;
    returnTo?: string | null;
    editVendor?: Vendor | null;
    [key: string]: unknown;
}

export interface CreateVendorProps {
    onSuccess: () => void;
    returnTo?: string | null;
}

export interface EditVendorProps {
    vendor: Vendor;
    onSuccess: () => void;
}
