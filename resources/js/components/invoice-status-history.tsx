import { formatDate } from '@/utils/helpers';

interface HistoryItem {
    id: number;
    from_status: string;
    to_status: string;
    reason: string;
    created_at: string;
    actor?: { name?: string } | null;
}

export default function InvoiceStatusHistory({ items = [] }: { items?: HistoryItem[] }) {
    if (!items.length) return null;

    return (
        <section className="rounded-lg border bg-white p-6">
            <h2 className="text-lg font-semibold">Status History</h2>
            <div className="mt-4 divide-y">
                {items.map((item) => (
                    <div key={item.id} className="grid gap-1 py-3 text-sm sm:grid-cols-[minmax(12rem,1fr)_2fr_auto] sm:gap-4">
                        <div className="font-medium capitalize">{item.from_status} → {item.to_status}</div>
                        <div className="text-muted-foreground">{item.reason}</div>
                        <div className="text-muted-foreground sm:text-right">
                            <div>{item.actor?.name || 'System'}</div>
                            <div>{formatDate(item.created_at)}</div>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}
