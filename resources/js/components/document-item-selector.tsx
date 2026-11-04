import { useMemo, useRef, useState } from 'react';
import { Check, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';
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
    manualValue?: string;
    canCreateItem?: boolean;
    onChange: (productId: number, product?: T) => void;
    onManualChange?: (value: string) => void;
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
    manualValue = '',
    canCreateItem = false,
    onChange,
    onManualChange,
    onCreateItem,
}: Props<T>) {
    const { t } = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);
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
            <PopoverAnchor asChild>
                <Input
                    ref={inputRef}
                    value={selected?.name || manualValue}
                    onFocus={() => setOpen(true)}
                    onClick={() => setOpen(true)}
                    onChange={event => {
                        onManualChange?.(event.target.value);
                        setQuery(event.target.value);
                        setOpen(true);
                    }}
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="document-item-suggestions"
                    aria-expanded={open}
                    autoComplete="off"
                    placeholder={placeholder}
                    className="h-10"
                />
            </PopoverAnchor>
            <PopoverContent
                id="document-item-suggestions"
                align="start"
                onOpenAutoFocus={event => event.preventDefault()}
                onInteractOutside={event => {
                    if (inputRef.current?.contains(event.target as Node)) {
                        event.preventDefault();
                    }
                }}
                style={{ width: Math.max(inputRef.current?.offsetWidth || 0, 320) }}
                className="max-w-[calc(100vw-2rem)] p-0"
            >
                <div className="max-h-72 overflow-y-auto overscroll-contain p-1">
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
                        <div className="px-3 py-8 text-center text-sm text-muted-foreground">
                            {manualValue.trim() ? t('Use the typed text as a manual item') : t('No items found.')}
                        </div>
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
