import {    CalendarClock , HelpCircle , Calendar, CalendarDays , Tag } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const appointmentCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Appointment Dashboard'),
        href: route('appointment.index'),
        permission: 'manage-appointment-dashboard',
        parent: 'dashboard',
        order: 150,
    },
    {
        title: t('Appointment'),
        icon: CalendarClock,
        permission: 'manage-appointment',
        order: 1000,
        children: [
            {
                title: t('Appointments'),
                href: route('appointment.appointments.index'),
                permission: 'manage-appointments',
            },
            {
                title: t('Questions'),
                href: route('appointment.questions.index'),
                permission: 'manage-questions',
            },
            {
                title: t('Schedules'),
                href: route('appointment.schedules.index'),
                permission: 'manage-schedules',
            },
            {
                title: t('Appointment Callbacks'),
                href: route('appointment.callbacks.index'),
                permission: 'manage-appointment-callbacks',
            },
            {
                title: t('System Setup'),
                href: route('appointment.settings.hours'),
                permission: 'manage-appointment-settings',
            },
        ],
    },
];
