export function isPlaceholderEmail(email?: string | null): boolean {
    const value = (email || '').trim().toLowerCase();

    return value === '' || value.endsWith('@import.local') || value.startsWith('zoho.customer.') || value.startsWith('zoho.vendor.');
}

export function displayEmail(...emails: Array<string | null | undefined>): string {
    const email = emails.find((value) => value && !isPlaceholderEmail(value));

    return email || '';
}
