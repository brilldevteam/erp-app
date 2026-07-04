import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import NoRecordsFound from '@/components/no-records-found';
import { formatCurrency, formatDate } from '@/utils/helpers';
import { DollarSign, Eye, Plus, Trash2 } from 'lucide-react';
import { JournalEntry } from './types';

interface IndexProps {
    journalEntries: {
        data: JournalEntry[];
        links: any[];
        meta: any;
    };
    filters: {
        search?: string;
    };
    auth: any;
}

export default function Index() {
    const { t } = useTranslation();
    const { journalEntries, filters: initialFilters, auth } = usePage<IndexProps>().props;
    const [search, setSearch] = useState(initialFilters.search || '');

    const handleSearch = () => {
        router.get(route('account.journal-entries.index'), { search }, { preserveState: true, replace: true });
    };

    const deleteEntry = (entry: JournalEntry) => {
        if (confirm(t('Delete this journal entry?'))) {
            router.delete(route('account.journal-entries.destroy', entry.id), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Accounting'), url: route('account.index') },
                { label: t('Journal Entries') },
            ]}
            pageTitle={t('Journal Entries')}
            pageActions={
                auth.user?.permissions?.includes('create-journal-entries') && (
                    <Button asChild size="icon">
                        <Link href={route('account.journal-entries.create')}>
                            <Plus className="h-4 w-4" />
                        </Link>
                    </Button>
                )
            }
        >
            <Head title={t('Journal Entries')} />

            <Card>
                <CardContent className="border-b bg-gray-50/50 p-6">
                    <div className="flex max-w-xl gap-3">
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('Search journal entries...')}
                            onKeyDown={(event) => event.key === 'Enter' && handleSearch()}
                        />
                        <Button onClick={handleSearch}>{t('Search')}</Button>
                    </div>
                </CardContent>

                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b bg-muted/40">
                                <tr>
                                    <th className="px-4 py-3 text-left">{t('Journal Number')}</th>
                                    <th className="px-4 py-3 text-left">{t('Date')}</th>
                                    <th className="px-4 py-3 text-left">{t('Reference')}</th>
                                    <th className="px-4 py-3 text-right">{t('Debit')}</th>
                                    <th className="px-4 py-3 text-right">{t('Credit')}</th>
                                    <th className="px-4 py-3 text-left">{t('Status')}</th>
                                    <th className="px-4 py-3 text-right">{t('Actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {journalEntries.data.length > 0 ? (
                                    journalEntries.data.map((entry) => (
                                        <tr key={entry.id} className="border-b">
                                            <td className="px-4 py-3 font-medium text-blue-600">{entry.journal_number}</td>
                                            <td className="px-4 py-3">{formatDate(entry.journal_date)}</td>
                                            <td className="px-4 py-3">{entry.reference_type || '-'}</td>
                                            <td className="px-4 py-3 text-right">{formatCurrency(entry.total_debit)}</td>
                                            <td className="px-4 py-3 text-right">{formatCurrency(entry.total_credit)}</td>
                                            <td className="px-4 py-3">
                                                <span className="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                                    {t(entry.status)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-2">
                                                    {auth.user?.permissions?.includes('view-journal-entries') && (
                                                        <Button size="sm" variant="ghost" asChild>
                                                            <Link href={route('account.journal-entries.show', entry.id)}>
                                                                <Eye className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {auth.user?.permissions?.includes('delete-journal-entries') && (
                                                        <Button size="sm" variant="ghost" className="text-red-600" onClick={() => deleteEntry(entry)}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7}>
                                            <NoRecordsFound
                                                icon={DollarSign}
                                                title={t('No journal entries found')}
                                                description={t('Get started by creating your first journal entry.')}
                                                createPermission="create-journal-entries"
                                                onCreateClick={() => router.visit(route('account.journal-entries.create'))}
                                                createButtonText={t('Create Journal Entry')}
                                                className="h-auto py-10"
                                            />
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>

                <CardContent className="border-t bg-gray-50/30 px-4 py-2">
                    <Pagination data={journalEntries} routeName="account.journal-entries.index" filters={{ search }} />
                </CardContent>
            </Card>
        </AuthenticatedLayout>
    );
}
