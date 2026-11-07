import DocumentTemplatePreview from '@/components/document-templates/document-template-preview';
import { DocumentTemplate, TemplateSampleDocument } from '@/types/document-template';
import { Head, usePage } from '@inertiajs/react';
import html2pdf from 'html2pdf.js';
import { useEffect, useRef, useState } from 'react';

interface Props {
    customer: { customer_code: string };
    documentTemplate: DocumentTemplate;
    templateDocument: TemplateSampleDocument;
    [key: string]: any;
}

export default function StatementPrint() {
    const { customer, documentTemplate, templateDocument } = usePage<Props>().props;
    const content = useRef<HTMLDivElement>(null);
    const [isDownloading, setIsDownloading] = useState(false);

    useEffect(() => {
        if (new URLSearchParams(window.location.search).get('download') !== 'pdf') return;

        const timer = window.setTimeout(() => void downloadPdf(), 500);
        return () => window.clearTimeout(timer);
    }, []);

    const downloadPdf = async () => {
        if (!content.current || isDownloading) return;
        setIsDownloading(true);

        try {
            await html2pdf().set({
                margin: 0,
                filename: `customer-statement-${customer.customer_code}.pdf`,
                image: { type: 'jpeg' as const, quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' as const },
                pagebreak: { mode: ['css', 'legacy'], avoid: ['tr'] },
            }).from(content.current).save();
            window.setTimeout(() => window.close(), 800);
        } finally {
            setIsDownloading(false);
        }
    };

    return (
        <div className="min-h-screen bg-white">
            <Head title="Customer Statement" />
            {isDownloading && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40"><div className="rounded bg-white px-6 py-4 font-semibold shadow-lg">Generating statement PDF...</div></div>}
            <div ref={content} className="statement-container bg-white">
                <DocumentTemplatePreview template={documentTemplate} document={templateDocument} />
            </div>
        </div>
    );
}
