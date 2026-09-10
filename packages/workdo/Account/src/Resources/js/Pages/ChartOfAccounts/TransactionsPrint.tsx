import { useEffect, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import html2pdf from 'html2pdf.js';
import { Button } from '@/components/ui/button';
import { Download } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function TransactionsPrint() {
    const { t } = useTranslation();
    const { rows, metadata, headers, filename } = usePage<{rows: (string|number|null)[][]; metadata: Record<string,string>; headers: string[]; filename: string}>().props;
    const report = useRef<HTMLDivElement>(null);
    const started = useRef(false);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const save = async () => {
        if (!report.current) return;
        setBusy(true);setError('');
        try { await html2pdf().set({ filename: filename+'.pdf', margin: 8, html2canvas: { scale: 2 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }, pagebreak: { mode: ['css','legacy'], avoid: 'tr' } }).from(report.current).save(); }
        catch { setError(t('PDF could not be generated. Please try again.')); }
        finally {setBusy(false);}
    };
    useEffect(() => { if (!started.current) {started.current=true;void save();} }, []);
    return <div className="min-h-screen overflow-x-auto bg-white p-4 text-black">
        <Head title={t('Account Transactions')} />
        <div className="mb-4 flex items-center gap-3 print:hidden"><Button disabled={busy} onClick={save}><Download className="mr-2 h-4 w-4" />{busy?t('Generating PDF...'):t('Download PDF')}</Button>{error && <span role="alert">{error}</span>}</div>
        <div ref={report} className="mx-auto w-[1050px] bg-white p-6">
            <h1 className="mb-4 text-xl font-bold">{t('Account Transactions')}</h1>
            {Object.entries(metadata).map(([label,value]) => <div key={label} className="mb-1 text-sm"><strong>{label}: </strong>{value}</div>)}
            <table className="mt-5 w-full table-fixed border-collapse text-xs">
                <thead><tr>{headers.map((header,i)=><th key={i} className="border bg-gray-100 p-2 text-left">{header}</th>)}</tr></thead>
                <tbody>{rows.map((row,i)=><tr key={i}>{row.map((value,j)=><td key={j} className={`break-words border p-2 ${j>=4?'text-right':''}`} style={{overflowWrap:'anywhere'}}>{typeof value==='number'?value.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}):value}</td>)}</tr>)}</tbody>
            </table>
        </div>
    </div>;
}
