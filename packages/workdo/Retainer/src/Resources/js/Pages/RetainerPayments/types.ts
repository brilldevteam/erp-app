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

export interface Retainer {
    id: number;
    retainer_number: string;
    retainer_date: string;
    total_amount: number;
    paid_amount: number;
    balance_amount: number;
    status: string;
}

export interface RetainerPayment {
    id: number;
    payment_number: string;
    payment_date: string;
    retainer_id: number;
    customer_id: number;
    bank_account_id: number;
    payment_amount: number;
    payment_method: 'cash' | 'bank_transfer' | 'cheque' | 'card';
    reference_number?: string;
    status: 'pending' | 'cleared' | 'cancelled';
    notes?: string;
    retainer: Retainer;
    customer: Customer;
    bank_account: BankAccount;
    created_at: string;
}

export interface CreateRetainerPaymentFormData {
    payment_date: string;
    retainer_id: string;
    bank_account_id: string;
    payment_amount: string;
    payment_method: string;
    reference_number: string;
    notes: string;
}

export interface RetainerPaymentFilters {
    customer_id: string;
    status: string;
    search: string;
    date_range: string;
}

export type PaginatedRetainerPayments = PaginatedData<RetainerPayment>;
export type RetainerPaymentModalState = ModalState<RetainerPayment>;

export interface RetainerPaymentsIndexProps {
    payments: PaginatedRetainerPayments;
    customers: Customer[];
    bankAccounts: BankAccount[];
    filters: RetainerPaymentFilters;
    auth: AuthContext;
}

export interface CreateRetainerPaymentProps {
    customers: Customer[];
    bankAccounts: BankAccount[];
    onSuccess: () => void;
}

export interface RetainerPaymentViewProps {
    payment: RetainerPayment;
}