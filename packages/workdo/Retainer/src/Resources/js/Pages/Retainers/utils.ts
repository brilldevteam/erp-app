export const getStatusBadgeClasses = (status: string) => {
    const colors = {
        draft: 'bg-gray-100 text-gray-800',
        sent: 'bg-blue-100 text-blue-800',
        accepted: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
        expired: 'bg-orange-100 text-orange-800',
        partial:  'bg-yellow-100 text-yellow-800',
        paid:  'bg-green-100 text-green-800',
        converted:  'bg-purple-100 text-purple-800',
        
    };
    return `px-2 py-1 rounded-full text-sm ${colors[status as keyof typeof colors]}`;
};