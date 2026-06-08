import { PaginatedData, ModalState, AuthContext } from '@/types/common';



export interface PettyCashCategorie {
    id: number;
    name: string;
    created_at: string;
}

export interface CreatePettyCashCategorieFormData {
    name: string;
}

export interface EditPettyCashCategorieFormData {
    name: string;
}

export interface PettyCashCategorieFilters {
    name: string;
}

export type PaginatedPettyCashCategories = PaginatedData<PettyCashCategorie>;
export type PettyCashCategorieModalState = ModalState<PettyCashCategorie>;

export interface PettyCashCategoriesIndexProps {
    pettycashcategories: PaginatedPettyCashCategories;
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