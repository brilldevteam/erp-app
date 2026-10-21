import { ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';

interface AccountTransactionsLinkProps {
    accountId?: number | null;
    fromDate: string;
    toDate: string;
    children: ReactNode;
    className?: string;
}

/**
 * A report date as Y-m-d. Plain dates pass through; serialised timestamps (e.g. "2026-12-30T21:00:00Z" for
 * 31 Dec in Qatar) are read in local time so the day is not shifted by the time zone.
 */
export function toLocalYmd(value: string): string {
    const text = String(value ?? '');
    if (/^\d{4}-\d{2}-\d{2}$/.test(text)) return text;
    const date = new Date(text);
    if (Number.isNaN(date.getTime())) return text.slice(0, 10);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/**
 * Opens an account's transactions (opening balance, lines, running balance, closing) for a report's period.
 * Renders plain text when there is no account or the user cannot view the chart of accounts.
 */
export function AccountTransactionsLink({ accountId, fromDate, toDate, children, className }: AccountTransactionsLinkProps) {
    const { auth } = usePage<{ auth?: { user?: { permissions?: string[] } } }>().props;

    if (!accountId || !auth?.user?.permissions?.includes('view-chart-of-accounts')) {
        return <span className={className}>{children}</span>;
    }

    return (
        <Link
            href={route('account.chart-of-accounts.show', accountId)}
            data={{ from_date: toLocalYmd(fromDate), to_date: toLocalYmd(toDate) }}
            className={`${className ?? ''} hover:underline underline-offset-2`}
        >
            {children}
        </Link>
    );
}
