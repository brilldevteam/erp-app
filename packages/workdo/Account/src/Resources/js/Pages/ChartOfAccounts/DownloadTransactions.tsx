import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AccountingReportDownloadMenu } from '@/components/accounting-report-download-menu';
import { ChartOfAccount } from './types';

export default function DownloadTransactions({ account, onClose }: { account: ChartOfAccount; onClose: () => void }) {
    const { t } = useTranslation();
    const today = new Date();
    const [from, setFrom] = useState(`${today.getFullYear()}-01-01`);
    const [to, setTo] = useState(`${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`);
    const [error, setError] = useState('');
    const download = (format: 'pdf' | 'excel') => {
        if (!from || !to || from > to) { setError(t('Select a valid date range. To Date must be on or after From Date.')); return; }
        setError('');
        const params = new URLSearchParams({ from_date: from, to_date: to, format });
        window.open(route('account.chart-of-accounts.transactions', account.id) + '?' + params.toString(), '_blank', 'noopener,noreferrer');
    };
    return <Dialog open onOpenChange={open => { if (!open) onClose(); }}>
        <DialogContent className="max-w-lg">
            <DialogHeader><DialogTitle>{t('Download Account Transactions')}</DialogTitle><DialogDescription className="break-words">{account.account_code} - {account.account_name}</DialogDescription></DialogHeader>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><Label htmlFor="transactions-from">{t('From Date')}</Label><Input id="transactions-from" type="date" value={from} onChange={e => {setFrom(e.target.value);setError('');}} /></div>
                <div><Label htmlFor="transactions-to">{t('To Date')}</Label><Input id="transactions-to" type="date" value={to} min={from} onChange={e => {setTo(e.target.value);setError('');}} /></div>
            </div>
            {error && <p role="alert" className="text-sm text-red-600">{error}</p>}
            <DialogFooter><Button variant="outline" onClick={onClose}>{t('Cancel')}</Button><AccountingReportDownloadMenu onPdf={() => download('pdf')} onExcel={() => download('excel')} /></DialogFooter>
        </DialogContent>
    </Dialog>;
}
