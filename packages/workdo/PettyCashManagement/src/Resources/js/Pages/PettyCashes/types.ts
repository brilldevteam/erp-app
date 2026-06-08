import { PaginatedData, ModalState, AuthContext } from '@/types/common';



export interface PettyCash {
    id: number;
    pettycash_number: string;
    date: string;
    opening_balance: number;
    added_amount: number;
    total_balance: number;
    total_expense: number;
    closing_balance: number;
    status: number;
    remarks?: string;
    creator_id: number;
    created_at: string;
    expenses?: PettyCashExpense[];
}

export interface PettyCashExpense {
    id: number;
    type: string;
    amount: string;
    remarks: string;
    approved_at: string;
    approver?: { name: string };
    request?: {
        request_number: string;
        user?: { name: string };
        category?: { name: string };
    };
    reimbursement?: {
        reimbursement_number: string;
        user?: { name: string };
        category?: { name: string };
    };
}

export interface CreatePettyCashFormData {
    date: string;
    added_amount: string;
    remarks: string;
}

export interface EditPettyCashFormData {
    date: string;
    added_amount: string;
    remarks: string;
}

export interface PettyCashFilters {
    pettycash_number: string;
    status: string;
}

export type PaginatedPettyCashes = PaginatedData<PettyCash>;
export type PettyCashModalState = ModalState<PettyCash>;

export interface PettyCashesIndexProps {
    pettycashes: PaginatedPettyCashes;
    auth: AuthContext;
    latestEntryId: number;
    [key: string]: unknown;
}

export interface CreatePettyCashProps {
    onSuccess: () => void;
}

export interface EditPettyCashProps {
    pettycash: PettyCash;
    onSuccess: () => void;
}

export interface PettyCashShowProps {
    pettycash: PettyCash;
    [key: string]: unknown;
}