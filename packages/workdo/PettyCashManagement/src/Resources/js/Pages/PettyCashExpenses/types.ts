import { PaginatedData, ModalState, AuthContext } from '@/types/common';



export interface PettyCashExpense {
    id: number;
    request_id?: number;
    type: boolean;
    amount: string;
    remarks?: string;
    status: boolean;
    approved_at?: any;
    approved_by?: number;
    created_by: number;
    created_at: string;
}

export interface CreatePettyCashExpenseFormData {
    request_id: string;
    type: boolean;
    amount: string;
    remarks: string;
    status: boolean;
    approved_at: any;
    approved_by: string;
    created_by: string;
}

export interface EditPettyCashExpenseFormData {
    request_id: string;
    type: boolean;
    amount: string;
    remarks: string;
    status: boolean;
    approved_at: any;
    approved_by: string;
    created_by: string;
}

export interface PettyCashExpenseFilters {
    remarks: string;
    type: string;
    status: string;
    approved_by: string;
    created_by: string;
}

export type PaginatedPettyCashExpenses = PaginatedData<PettyCashExpense>;
export type PettyCashExpenseModalState = ModalState<PettyCashExpense>;

export interface PettyCashExpensesIndexProps {
    pettycashexpenses: PaginatedPettyCashExpenses;
    auth: AuthContext;
    pettycashrequests: any[];
    users: any[];
    [key: string]: unknown;
}

export interface CreatePettyCashExpenseProps {
    onSuccess: () => void;
}

export interface EditPettyCashExpenseProps {
    pettycashexpense: PettyCashExpense;
    onSuccess: () => void;
}

export interface PettyCashExpenseShowProps {
    pettycashexpense: PettyCashExpense;
    [key: string]: unknown;
}