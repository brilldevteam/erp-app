export interface JournalEntryItem {
    id?: number;
    account_id: number | string;
    contact_id?: number | string | null;
    description?: string;
    debit_amount: number | string;
    credit_amount: number | string;
    account?: {
        id: number;
        account_code: string;
        account_name: string;
        normal_balance: 'debit' | 'credit';
    };
    contact_name?: string | null;
    contact_type?: 'vendor' | 'customer' | null;
}

export interface JournalEntry {
    id: number;
    journal_number: string;
    journal_date: string;
    entry_type: 'automatic' | 'manual';
    reference_type?: string;
    description: string;
    total_debit: number;
    total_credit: number;
    status: 'draft' | 'posted' | 'reversed';
    items: JournalEntryItem[];
    attachments?: { id: number; file_name: string; file_type: string; file_size: number; created_at: string; uploader?: { name: string } }[];
}

export interface ContactOption {
    id: number;
    name: string;
    company_name: string | null;
    type: 'vendor' | 'customer';
}

export interface AccountOption {
    id: number;
    account_code: string;
    account_name: string;
    normal_balance: 'debit' | 'credit';
}
