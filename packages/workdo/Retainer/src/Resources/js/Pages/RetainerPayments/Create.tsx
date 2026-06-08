import { useState, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CurrencyInput } from '@/components/ui/currency-input';
import { DatePicker } from '@/components/ui/date-picker';
import InputError from '@/components/ui/input-error';
import { Trash2 } from 'lucide-react';
import { formatCurrency } from '@/utils/helpers';

interface SalesRetainer {
    id: number;
    retainer_number: string;
    total_amount: number | string;
    paid_amount: number | string;
    balance_amount: number | string;
    status: string;
}

interface CreateRetainerPaymentFormData {
    payment_date: string;
    customer_id: string;
    bank_account_id: string;
    reference_number: string;
    payment_amount: string;
    notes: string;
    allocations: {retainer_id: number; amount: number}[];
}

interface CreateRetainerPaymentProps {
    customers: Array<{id: number; name: string}>;
    bankAccounts: Array<{id: number; account_name: string; account_number: string}>;
    onSuccess: () => void;
}

export default function Create({ customers, bankAccounts, onSuccess }: CreateRetainerPaymentProps) {
    const { t } = useTranslation();
    const [outstandingRetainers, setOutstandingRetainers] = useState<SalesRetainer[]>([]);
    const [selectedAllocations, setSelectedAllocations] = useState<{retainer_id: number; amount: number}[]>([]);

    const { data, setData, post, processing, errors } = useForm<CreateRetainerPaymentFormData>({
        payment_date: new Date().toISOString().split('T')[0],
        customer_id: '',
        bank_account_id: '',
        reference_number: '',
        payment_amount: '',
        notes: '',
        allocations: []
    });

    const fetchOutstandingRetainers = async (customerId: string) => {
        if (!customerId) {
            setOutstandingRetainers([]);
            return;
        }

        try {
            const response = await fetch(route('retainer-payments.outstanding-retainers', customerId));
            const result = await response.json();
            setOutstandingRetainers(result.retainers || []);
        } catch (error) {
            console.error('Failed to fetch outstanding retainers:', error);
            setOutstandingRetainers([]);
        }
    };

    useEffect(() => {
        setData('allocations', selectedAllocations);
    }, [selectedAllocations]);

    useEffect(() => {
        if (data.customer_id) {
            fetchOutstandingRetainers(data.customer_id);
        } else {
            setOutstandingRetainers([]);
        }
        setSelectedAllocations([]);
        setData('payment_amount', '');
    }, [data.customer_id]);

    const addAllocation = (retainer: SalesRetainer) => {
        const existing = selectedAllocations.find(a => a.retainer_id === retainer.id);
        if (existing) return;

        const newAllocation = {
            retainer_id: retainer.id,
            amount: Number(retainer.balance_amount)
        };

        const newAllocations = [...selectedAllocations, newAllocation];
        setSelectedAllocations(newAllocations);
        updateTotalAmount(newAllocations);
    };

    const removeAllocation = (retainerId: number) => {
        const newAllocations = selectedAllocations.filter(a => a.retainer_id !== retainerId);
        setSelectedAllocations(newAllocations);
        updateTotalAmount(newAllocations);
    };

    const updateAllocationAmount = (retainerId: number, amount: number) => {
        const newAllocations = selectedAllocations.map(a =>
            a.retainer_id === retainerId ? { ...a, amount: Number(amount || 0) } : a
        );
        setSelectedAllocations(newAllocations);
        updateTotalAmount(newAllocations);
    };

    const updateTotalAmount = (allocations: {retainer_id: number; amount: number}[]) => {
        const total = allocations.reduce((sum, allocation) => sum + Number(allocation.amount || 0), 0);
        setData('payment_amount', Number(total).toFixed(2));
    };

    const getRetainerById = (id: number) => outstandingRetainers.find(ret => ret.id === id);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        post(route('retainer-payments.store'), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent className="max-w-4xl">
            <DialogHeader>
                <DialogTitle>{t('Create Retainer Payment')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="payment_date" required>{t('Payment Date')}</Label>
                        <DatePicker
                            id="payment_date"
                            value={data.payment_date}
                            onChange={(value) => {
                                const formattedDate = value instanceof Date ? value.toISOString().split('T')[0] : value;
                                setData('payment_date', formattedDate);
                            }}
                            placeholder={t('Select payment date')}
                            required
                        />
                        <InputError message={errors.payment_date} />
                    </div>

                    <div>
                        <Label htmlFor="customer_id" required>{t('Customer')}</Label>
                        <Select value={data.customer_id} onValueChange={(value) => {
                            setData('customer_id', value);
                        }}>
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select Customer')} />
                            </SelectTrigger>
                            <SelectContent>
                                {customers?.map((customer) => (
                                    <SelectItem key={customer.id} value={customer.id.toString()}>
                                        {customer.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.customer_id} />
                    </div>

                    <div>
                        <Label htmlFor="bank_account_id" required>{t('Bank Account')}</Label>
                        <Select value={data.bank_account_id} onValueChange={(value) => setData('bank_account_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select Bank Account')} />
                            </SelectTrigger>
                            <SelectContent>
                                {bankAccounts?.map((account) => (
                                    <SelectItem key={account.id} value={account.id.toString()}>
                                        {account.account_name} ({account.account_number})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.bank_account_id} />
                    </div>

                    <div>
                        <Label htmlFor="reference_number">{t('Reference Number')}</Label>
                        <Input
                            id="reference_number"
                            value={data.reference_number}
                            onChange={(e) => setData('reference_number', e.target.value)}
                            placeholder={t('Check number, etc.')}
                        />
                        <InputError message={errors.reference_number} />
                    </div>
                </div>

                {data.customer_id && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">{t('Outstanding Retainers')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {outstandingRetainers.length > 0 ? (
                                <div className="space-y-2 max-h-40 overflow-y-auto">
                                    {outstandingRetainers.map((retainer) => (
                                        <div key={retainer.id} className="flex items-center justify-between p-2 border rounded">
                                            <div>
                                                <span className="font-medium">{retainer.retainer_number}</span>
                                                <span className="text-sm text-gray-500 ml-2">
                                                    {t('Balance:')} {formatCurrency(retainer.balance_amount)}
                                                </span>
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() => addAllocation(retainer)}
                                                disabled={selectedAllocations.some(a => a.retainer_id === retainer.id)}
                                            >
                                                {t('Add')}
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-4 text-gray-500">
                                    {t('No outstanding retainers found for this customer')}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {selectedAllocations.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">{t('Payment Summary')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {selectedAllocations.map((allocation) => {
                                    const retainer = getRetainerById(allocation.retainer_id);
                                    return (
                                        <div key={allocation.retainer_id} className="flex items-center gap-3 p-3 border rounded">
                                            <div className="flex-1">
                                                <div className="font-medium">{retainer?.retainer_number}</div>
                                                <div className="text-sm text-gray-500">
                                                    {t('Balance')}: {formatCurrency(retainer?.balance_amount || 0)}
                                                </div>
                                            </div>
                                            <div className="w-32">
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    value={allocation.amount}
                                                    onChange={(e) => updateAllocationAmount(allocation.retainer_id, Number(e.target.value) || 0)}
                                                    max={Number(retainer?.balance_amount)}
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => removeAllocation(allocation.retainer_id)}
                                            >
                                                <Trash2 className="h-4 w-4 text-red-600" />
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div>
                    <CurrencyInput
                        label={t('Total Payment Amount')}
                        value={data.payment_amount}
                        onChange={(value) => {
                            setData('payment_amount', value);
                            // Clear allocations if total is changed manually
                            if (parseFloat(value) !== selectedAllocations.reduce((sum, a) => sum + a.amount, 0)) {
                                setSelectedAllocations([]);
                            }
                        }}
                        error={errors.payment_amount}
                        required
                    />
                </div>

                <div>
                    <Label htmlFor="notes">{t('Notes')}</Label>
                    <Textarea
                        id="notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        rows={3}
                        placeholder={t('Enter notes')}
                    />
                    <InputError message={errors.notes} />
                </div>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing || !selectedAllocations.length}
                    >
                        {processing ? t('Creating...') : t('Create')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}