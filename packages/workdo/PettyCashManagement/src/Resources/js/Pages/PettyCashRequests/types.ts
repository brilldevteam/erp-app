import { PaginatedData, ModalState, AuthContext } from '@/types/common';

export interface User {
    id: number;
    name: string;
}

export interface PettyCashCategory {
    id: number;
    name: string;
}

export interface PettyCashRequest {
    id: number;
    user_id: number;
    categorie_id: number;
    requested_amount: number;
    status: string;
    remarks?: string;
    approved_at?: any;
    approved_by?: number;
    user?: User;
    category?: PettyCashCategory;
    approver?: User;
    created_by?: number;
    creator?: User;
    created_at: string;
}

export interface CreatePettyCashRequestFormData {
    user_id: string;
    categorie_id: string;
    requested_amount: string;
    status: string;
    remarks: string;
    approved_at: any;
    approved_by: string;
    created_by: string;
}

export interface EditPettyCashRequestFormData {
    user_id: string;
    categorie_id: string;
    requested_amount: string;
    status: string;
    remarks: string;
    approved_at: any;
    approved_by: string;
    created_by: string;
}

export interface PettyCashRequestFilters {
    remarks: string;
    user_id: string;
    categorie_id: string;
    status: string;
    approved_by: string;
}

export type PaginatedPettyCashRequests = PaginatedData<PettyCashRequest>;
export type PettyCashRequestModalState = ModalState<PettyCashRequest>;

export interface PettyCashRequestsIndexProps {
    pettycashrequests: PaginatedPettyCashRequests;
    auth: AuthContext;
    users: any[];
    pettycashcategories: any[];
    [key: string]: unknown;
}

export interface CreatePettyCashRequestProps {
    onSuccess: () => void;
}

export interface EditPettyCashRequestProps {
    pettycashrequest: PettyCashRequest;
    onSuccess: () => void;
}

export interface PettyCashRequestShowProps {
    pettycashrequest: PettyCashRequest;
    [key: string]: unknown;
}