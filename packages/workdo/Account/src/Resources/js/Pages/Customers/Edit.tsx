import CustomerForm from './CustomerForm';
import { Customer } from './types';

interface EditCustomerProps {
    customer: Customer;
    onSuccess: () => void;
}

export default function Edit(props: EditCustomerProps) {
    return <CustomerForm mode="edit" {...props} />;
}
