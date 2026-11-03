import { useMemo, useState } from 'react';
import { Check, ChevronsUpDown, Plus, Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { baseToTransactionAmount, formatTransactionMoney } from '@/utils/transaction-money';
import { cn } from '@/lib/utils';

export interface DocumentItemOption {
    id: number;
    name: string;
    sku?: string;
    description?: string;
    sale_price?: number;
    purchase_price?: number;
    type?: string;
}

interface Props<T extends DocumentItemOption> {
    products: T[];
    value: number;
    currencyCode: string;
    baseCurrencyCode: string;
    exchangeRate: string | number;
    priceMode?: 'sale' | 'purchase';
    placeholder: string;
    canCreateItem?: boolean;
    onChange: (productId: number, product?: T) => void;
    onCreateItem?: () => void;
}

export default function DocumentItemSelector<T extends DocumentItemOption>({
    products,
    value,
    currencyCode,
    baseCurrencyCode,
    exchangeRate,
    priceMode = 'sale',
    placeholder,
    canCreateItem = false,
    onChange,
    onCreateItem,
}: Props<T>) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const selected = products.find(product => product.id === value);
    const filtered = useMemo(() => {
        const needle = query.trim().toLocaleLowerCase();
        if (!needle) return products;
        return products.filter(product => `${product.name} ${product.sku || ''}`.toLocaleLowerCase().includes(needle));
    }, [products, query]);

    const selectItem = (product: T) => {
        onChange(product.id, product);
        setOpen(false);
        setQuery('');
    };

    return (
        <Popover open={open} onOpenChange={next => {
            setOpen(next);
            if (!next) setQuery('');
        }}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="h-auto min-h-10 w-full justify-between px-3 py-2 text-left font-normal"
                >
                    <span className={cn('min-w-0 flex-1 truncate', !selected && 'text-muted-foreground')}>
                        {selected?.name || placeholder}
                    </span>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-[var(--radix-popover-trigger-width)] min-w-[320px] p-0">
                <div className="flex items-center gap-2 border-b px-3 py-2">
                    <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={event => setQuery(event.target.value)}
                        placeholder={t('Type or click to select an item')}
                        className="h-8 border-0 bg-transparent p-0 shadow-none focus-visible:ring-0"
                        autoFocus
                    />
                </div>
                <div className="max-h-72 overflow-y-auto p-1">
                    {filtered.length ? filtered.map(product => {
                        const basePrice = priceMode === 'purchase' ? product.purchase_price : product.sale_price;
                        const price = baseToTransactionAmount(Number(basePrice) || 0, currencyCode, baseCurrencyCode, exchangeRate);
                        const isSelected = product.id === value;
                        return (
                            <button
                                key={product.id}
                                type="button"
                                onClick={() => selectItem(product)}
                                className={cn(
                                    'flex w-full items-start gap-3 rounded px-3 py-2 text-left transition-colors hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                    isSelected && 'bg-primary text-primary-foreground hover:bg-primary/90',
                                )}
                            >
                                <Check className={cn('mt-0.5 h-4 w-4 shrink-0', isSelected ? 'opacity-100' : 'opacity-0')} />
                                <span className="min-w-0 flex-1">
                                    <span className="block break-words text-sm font-medium">{product.name}</span>
                                    <span className={cn('mt-0.5 block text-xs', isSelected ? 'text-primary-foreground/80' : 'text-muted-foreground')}>
                                        {t('Rate')}: {formatTransactionMoney(Number(price) || 0, currencyCode)}
                                    </span>
                                </span>
                            </button>
                        );
                    }) : (
                        <div className="px-3 py-8 text-center text-sm text-muted-foreground">{t('No items found.')}</div>
                    )}
                </div>
                {canCreateItem && onCreateItem && (
                    <button
                        type="button"
                        onClick={() => {
                            setOpen(false);
                            setQuery('');
                            onCreateItem();
                        }}
                        className="flex w-full items-center gap-2 border-t px-4 py-3 text-left text-sm font-medium text-primary hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                    >
                        <Plus className="h-4 w-4" />
                        {t('Add New Item')}
                    </button>
                )}
            </PopoverContent>
        </Popover>
    );
}
