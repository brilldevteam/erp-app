import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Undo2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';

interface Props {
    routeName: string;
    invoiceId: number;
    label?: string;
}

export default function RevertInvoiceDialog({ routeName, invoiceId, label = 'Revert to Draft' }: Props) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');

    const submit = () => {
        const value = reason.trim();
        if (value.length < 5) {
            setError('Enter a reason of at least 5 characters.');
            return;
        }
        setProcessing(true);
        setError('');
        router.post(route(routeName, invoiceId), { reason: value }, {
            preserveScroll: true,
            onError: (errors) => setError(String(errors.reason || errors.error || 'The invoice could not be reverted.')),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Button type="button" variant="outline" size="sm" onClick={() => setOpen(true)}>
                <Undo2 className="mr-2 h-4 w-4" />
                {label}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Revert invoice to draft?</DialogTitle>
                        <DialogDescription>
                            This reverses the invoice accounting and inventory effects. The invoice must be posted again after correction.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <label htmlFor={`revert-reason-${invoiceId}`} className="text-sm font-medium">Reason <span className="text-red-500">*</span></label>
                        <textarea
                            id={`revert-reason-${invoiceId}`}
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            rows={4}
                            maxLength={1000}
                            className="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            placeholder="Explain why this posted invoice needs correction"
                        />
                        {error && <p className="text-sm text-red-600">{error}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={processing}>Cancel</Button>
                        <Button type="button" variant="destructive" onClick={submit} disabled={processing}>
                            {processing ? 'Reverting...' : 'Revert to Draft'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
