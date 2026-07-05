export interface JournalEntryItem {
    id?: number;
    account_id: number | string;
    description?: string;
    debit_amount: number | string;
    credit_amount: number | string;
    account?: {
        id: number;
        account_code: string;
        account_name: string;
        normal_balance: 'debit' | 'credit';
    };
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
}

export interface AccountOption {
    id: number;
    account_code: string;
    account_name: string;
    normal_balance: 'debit' | 'credit';
}
