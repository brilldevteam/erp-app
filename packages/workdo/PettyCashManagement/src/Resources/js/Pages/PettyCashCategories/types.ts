import { PaginatedData, ModalState, AuthContext } from '@/types/common';



export interface ExpenseAccountOption {
    id: number;
    account_code: string;
    account_name: string;
}

export interface PettyCashCategorie {
    id: number;
    name: string;
    account_id?: number | null;
    account?: ExpenseAccountOption | null;
    created_at: string;
}

export interface CreatePettyCashCategorieFormData {
    name: string;
    account_id: string;
}

export interface EditPettyCashCategorieFormData {
    name: string;
    account_id: string;
}

export interface PettyCashCategorieFilters {
    name: string;
}

export type PaginatedPettyCashCategories = PaginatedData<PettyCashCategorie>;
export type PettyCashCategorieModalState = ModalState<PettyCashCategorie>;

export interface PettyCashCategoriesIndexProps {
    pettycashcategories: PaginatedPettyCashCategories;
    expenseAccounts?: ExpenseAccountOption[];
    auth: AuthContext;
    [key: string]: unknown;
}

export interface CreatePettyCashCategorieProps {
    onSuccess: () => void;
}

export interface EditPettyCashCategorieProps {
    pettycashcategorie: PettyCashCategorie;
    onSuccess: () => void;
}

export interface PettyCashCategorieShowProps {
    pettycashcategorie: PettyCashCategorie;
    [key: string]: unknown;
}