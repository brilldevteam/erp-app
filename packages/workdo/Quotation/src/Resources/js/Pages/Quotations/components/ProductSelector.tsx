import { useTranslation } from 'react-i18next';
import DocumentItemSelector from '@/components/document-item-selector';

interface Product {
    id: number;
    name: string;
    sku?: string;
    description?: string;
    sale_price: number;
    unit?: string;
    stock_quantity?: number;
    taxes?: Array<{id: number; tax_name: string; rate: number}>;
}

interface Props {
    products: Product[];
    value: number;
    currencyCode: string;
    baseCurrencyCode: string;
    exchangeRate: string | number;
    canCreateItem?: boolean;
    onCreateItem?: () => void;
    onChange: (productId: number, product?: Product) => void;
}

export default function ProductSelector({ products, value, currencyCode, baseCurrencyCode, exchangeRate, canCreateItem, onCreateItem, onChange }: Props) {
    const { t } = useTranslation();
    return <DocumentItemSelector
        products={products}
        value={value}
        currencyCode={currencyCode}
        baseCurrencyCode={baseCurrencyCode}
        exchangeRate={exchangeRate}
        placeholder={t('Type or click to select an item')}
        canCreateItem={canCreateItem}
        onCreateItem={onCreateItem}
        onChange={onChange}
    />;
}
