import { useTranslation } from 'react-i18next';
import { Address, formatAddressLines } from '@/types/address';

interface AddressDisplayProps {
    address?: Partial<Address> | null;
    className?: string;
}

export function AddressDisplay({ address, className }: AddressDisplayProps) {
    const { t } = useTranslation();
    return (
        <div className={className}>
            {formatAddressLines(address, t).map((line, index) => <p key={`${index}-${line}`}>{line}</p>)}
        </div>
    );
}
