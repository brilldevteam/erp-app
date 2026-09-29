import { formatDate } from '@/utils/helpers';

// Balance sheets generated for a reporting period show "opening – closing"; older ones only have a closing date.
export const formatBalanceSheetPeriod = (sheet: { balance_sheet_date: string; period_start_date?: string | null }) =>
    sheet.period_start_date
        ? `${formatDate(sheet.period_start_date)} – ${formatDate(sheet.balance_sheet_date)}`
        : formatDate(sheet.balance_sheet_date);

// Lines such as "Net Income for the Period" have a label instead of a chart-of-accounts row.
export const balanceSheetLineName = (
    item: { label?: string | null; account?: { account_name: string } | null },
    t: (key: string) => string,
) => item.account?.account_name ?? (item.label ? t(item.label) : '');
