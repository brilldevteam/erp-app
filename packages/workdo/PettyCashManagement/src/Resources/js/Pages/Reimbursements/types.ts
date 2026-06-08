import { PaginatedData, ModalState, AuthContext } from '@/types/common';

export interface User {
    id: number;
    name: string;
}

export interface Category {
    id: number;
    name: string;
}

export interface Reimbursement {
    id: number;
    user_id: number;
    category_id: number;
    amount: number;
    status: string;
    description?: string;
    request_date: any;
    approved_date?: any;
    approved_by?: number;
    receipt_path?: string;
    approved_amount?: number;
    rejection_reason?: string;
    user?: User;
    category?: Category;
    approver?: User;
    created_by?: number;
    creator?: User;
    created_at: string;
}

export interface CreateReimbursementFormData {
    user_id: string;
    category_id: string;
    amount: string;
    status: string;
    description: string;
    request_date: any;
    approved_date: any;
    approved_by: string;
    receipt_path: string;
    approved_amount: string;
    rejection_reason: string;
    created_by: string;
}

export interface EditReimbursementFormData {
    user_id: string;
    category_id: string;
    amount: string;
    status: string;
    description: string;
    request_date: any;
    approved_date: any;
    approved_by: string;
    receipt_path: string;
    approved_amount: string;
    rejection_reason: string;
    created_by: string;
}

export interface ReimbursementFilters {
    description: string;
    rejection_reason: string;
    user_id: string;
    category_id: string;
    status: string;
    approved_by: string;
}

export type PaginatedReimbursements = PaginatedData<Reimbursement>;
export type ReimbursementModalState = ModalState<Reimbursement>;

export interface ReimbursementsIndexProps {
    reimbursements: PaginatedReimbursements;
    auth: AuthContext;
    users: any[];
    categories: any[];
    [key: string]: unknown;
}

export interface CreateReimbursementProps {
    onSuccess: () => void;
}

export interface EditReimbursementProps {
    reimbursement: Reimbursement;
    onSuccess: () => void;
}

export interface ReimbursementShowProps {
    reimbursement: Reimbursement;
    [key: string]: unknown;
}