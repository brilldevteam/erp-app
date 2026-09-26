import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { BrandProvider } from '@/contexts/brand-context';

export default function AuthLayout({
    children,
    title,
    description,
    variant = 'default',
    ...props
}: {
    children: React.ReactNode;
    title: string;
    description: string;
    variant?: 'login' | 'register' | 'default';
}) {
    return (
        <BrandProvider>
            <AuthLayoutTemplate title={title} description={description} variant={variant} {...props}>
                {children}
            </AuthLayoutTemplate>
        </BrandProvider>
    );
}
