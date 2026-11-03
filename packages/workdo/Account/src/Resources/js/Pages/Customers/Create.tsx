import CustomerForm from './CustomerForm';

interface CreateCustomerProps {
    onSuccess: (userId?: number) => void;
    returnToCurrentPage?: boolean;
    returnTo?: 'quotation' | 'current';
}

export default function Create(props: CreateCustomerProps) {
    return <CustomerForm mode="create" {...props} />;
}
