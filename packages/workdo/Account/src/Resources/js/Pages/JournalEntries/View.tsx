import { Head, Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency, formatDate } from '@/utils/helpers';
import { ArrowLeft } from 'lucide-react';
import { JournalEntry } from './types';

interface ViewProps {
    journalEntry: JournalEntry;
}

export default function View() {
    const { t } = useTranslation();
    const { journalEntry } = usePage<ViewProps>().props;

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Accounting'), url: route('account.index') },
                { label: t('Journal Entries'), url: route('account.journal-entries.index') },
                { label: journalEntry.journal_number },
            ]}
            pageTitle={`${t('Journal Entry')} #${journalEntry.journal_number}`}
            pageActions={
                <Button variant="outline" asChild>
                    <Link href={route('account.journal-entries.index')}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        {t('Back')}
                    </Link>
                </Button>
            }
        >
            <Head title={`${t('Journal Entry')} #${journalEntry.journal_number}`} />

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('Journal Information')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <div className="text-sm text-muted-foreground">{t('Journal Number')}</div>
                                <div className="font-medium">{journalEntry.journal_number}</div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">{t('Journal Date')}</div>
                                <div className="font-medium">{formatDate(journalEntry.journal_date)}</div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">{t('Reference')}</div>
                                <div className="font-medium">{journalEntry.reference_type || '-'}</div>
                            </div>
                            <div>
                                <div className="text-sm text-muted-foreground">{t('Status')}</div>
                                <span className="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                    {t(journalEntry.status)}
                                </span>
                            </div>
                        </div>
                        <div className="mt-4 border-t pt-4">
                            <div className="text-sm text-muted-foreground">{t('Description')}</div>
                            <div>{journalEntry.description}</div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Journal Lines')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b bg-muted/40">
                                        <th className="px-4 py-3 text-left">{t('Account')}</th>
                                        <th className="px-4 py-3 text-left">{t('Description')}</th>
                                        <th className="px-4 py-3 text-right">{t('Debit')}</th>
                                        <th className="px-4 py-3 text-right">{t('Credit')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {journalEntry.items.map((item) => (
                                        <tr key={item.id} className="border-b">
                                            <td className="px-4 py-3">
                                                {item.account ? `${item.account.account_code} - ${item.account.account_name}` : '-'}
                                            </td>
                                            <td className="px-4 py-3">{item.description || '-'}</td>
                                            <td className="px-4 py-3 text-right">{Number(item.debit_amount) > 0 ? formatCurrency(Number(item.debit_amount)) : '-'}</td>
                                            <td className="px-4 py-3 text-right">{Number(item.credit_amount) > 0 ? formatCurrency(Number(item.credit_amount)) : '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="font-semibold">
                                        <td className="px-4 py-3" colSpan={2}>{t('Totals')}</td>
                                        <td className="px-4 py-3 text-right">{formatCurrency(journalEntry.total_debit)}</td>
                                        <td className="px-4 py-3 text-right">{formatCurrency(journalEntry.total_credit)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
