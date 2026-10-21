import { formatCurrency } from '@/utils/helpers';

export type TrialBalanceSection = 'assets' | 'liabilities' | 'equity' | 'income' | 'expense' | 'other';

export interface TrialBalanceAccount {
    id: number | null;
    account_code: string;
    account_name: string;
    section: TrialBalanceSection;
    /** Signed: positive = debit, negative = credit. */
    opening_balance: number;
    net_debit: number;
    net_credit: number;
    /** Signed: positive = debit, negative = credit. */
    closing_balance: number;
}

export interface TrialBalanceTotals {
    opening_debit: number;
    opening_credit: number;
    net_debit: number;
    net_credit: number;
    closing_debit: number;
    closing_credit: number;
}

export interface TrialBalanceData {
    accounts: TrialBalanceAccount[];
    totals: TrialBalanceTotals;
    total_debit: number;
    total_credit: number;
    is_balanced: boolean;
    from_date: string;
    to_date: string;
    diagnostics?: {
        active_accounts: number;
        posted_journals: number;
        has_accounts: boolean;
        has_period_journals: boolean;
    };
}

const SECTION_ORDER: Array<[TrialBalanceSection, string]> = [
    ['assets', 'Assets'],
    ['liabilities', 'Liabilities'],
    ['equity', 'Equity'],
    ['income', 'Income'],
    ['expense', 'Expense'],
    ['other', 'Other'],
];

/** Accounts grouped under their report section, in balance sheet then income statement order. */
export function groupBySection(accounts: TrialBalanceAccount[]) {
    return SECTION_ORDER
        .map(([section, label]) => ({ section, label, accounts: accounts.filter((account) => account.section === section) }))
        .filter((group) => group.accounts.length > 0);
}

/** A signed balance shown the Zoho way, e.g. "10,606.40 Cr". */
export function formatBalance(value: number): string {
    if (Math.abs(value) < 0.005) return '-';
    return `${formatCurrency(Math.abs(value))} ${value > 0 ? 'Dr' : 'Cr'}`;
}

export function formatAmount(value: number): string {
    return Math.abs(value) < 0.005 ? '-' : formatCurrency(value);
}
