import { PaginatedData, ModalState, AuthContext } from '@/types/common';

export interface Customer {
    id: number;
    name: string;
    email: string;
}

export interface BankAccount {
    id: number;
    account_name: string;
    account_number: string;
    bank_name: string;
}

export interface SalesInvoice {
    id: number;
    invoice_number: string;
    invoice_date: string;
    total_amount: number;
    balance_amount: number;
    status: string;
}

export interface CreditNote {
    id: number;
    credit_note_number: string;
    credit_note_date: string;
    total_amount: number;
    balance_amount: number;
    status: string;
}

export interface CustomerPaymentAllocation {
    id: number;
    invoice_id: number;
    allocated_amount: number;
    invoice: SalesInvoice;
}

export interface CreditNoteApplication {
    id: number;
    credit_note_id: number;
    applied_amount: number;
    application_date: string;
    credit_note?: CreditNote;
}

export interface CustomerDetails {
    contact_person_email?: string | null;
}

export interface CustomerPayment {
    id: number;
    payment_number: string;
    payment_date: string;
    customer_id: number;
    bank_account_id: number;
    reference_number?: string;
    payment_amount: number;
    available_deposit: number;
    status: 'pending' | 'cleared' | 'cancelled';
    notes?: string;
    customer: Customer;
    customer_details?: CustomerDetails | null;
    bank_account: BankAccount;
    allocations: CustomerPaymentAllocation[];
    credit_note_applications?: CreditNoteApplication[];
    created_at: string;
}

export interface CreateCustomerPaymentFormData {
    payment_date: string;
    customer_id: string;
    bank_account_id: string;
    reference_number: string;
    payment_amount: string;
    notes: string;
    allocations: {
        invoice_id: number;
        amount: number;
    }[];
    credit_notes: {
        credit_note_id: number;
        amount: number;
    }[];
    return_to?: string;
}

export interface CustomerPaymentFilters {
    customer_id: string;
    status: string;
    search: string;
}

export type PaginatedCustomerPayments = PaginatedData<CustomerPayment>;
export type CustomerPaymentModalState = ModalState<CustomerPayment>;

export interface CustomerPaymentsIndexProps {
    payments: PaginatedCustomerPayments;
    customers: Customer[];
    bankAccounts: BankAccount[];
    filters: CustomerPaymentFilters;
    auth: AuthContext;
}

export interface CreateCustomerPaymentProps {
    customers: Customer[];
    bankAccounts: BankAccount[];
    onSuccess: () => void;
    defaultCustomerId?: number | string;
    defaultInvoiceId?: number | string;
    defaultInvoiceBalance?: number | string;
    returnTo?: string;
}

export interface CustomerPaymentViewProps {
    payment: CustomerPayment;
    canApplyDeposit?: boolean;
    onApplied?: () => void;
}
