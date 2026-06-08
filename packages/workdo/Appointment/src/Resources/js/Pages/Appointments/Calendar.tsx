import { Head, usePage } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { useFlashMessages } from '@/hooks/useFlashMessages'
import { useDeleteHandler } from '@/hooks/useDeleteHandler'
import AuthenticatedLayout from '@/layouts/authenticated-layout'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog'
import { Calendar as CalendarIcon, Clock, Users, Trash2, Eye } from 'lucide-react'
import CalendarView from '@/components/calendar-view'
import { formatDate, formatTime } from '@/utils/helpers'

interface CalendarEvent {
  id: number
  title: string
  date: string
  time: string
  description: string
  type: string
  color: string
  attendees: string[]
  status: string
  schedule_data: any
  appointment_id?: number
  schedules_count?: number
}

interface AppointmentCalendarProps {
  events: CalendarEvent[]
  appointmentScheduleCounts?: Record<number, number>
  auth: {
    user: {
      permissions: string[]
    }
  }
}

export default function AppointmentCalendar() {
  const { t } = useTranslation()
  const { events, appointmentScheduleCounts = {}, auth } = usePage<AppointmentCalendarProps>().props
  const breadcrumbs = [
    { label: t('Appointments'), url: route('appointment.appointments.index') },
    { label: t('Appointment Calendar') }
  ]

  useFlashMessages()

  const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
    routeName: 'appointment.schedules.destroy',
    defaultMessage: t('Are you sure you want to delete this schedule?'),
    redirectRoute: 'appointment.appointments.calendar'
  })

  const eventTypes = [
    { name: 'pending', color: '#f59e0b' },
    { name: 'approved', color: '#10b981' },
    { name: 'reject', color: '#ef4444' },
    { name: 'complete', color: '#3b82f6' }
  ]

  const handleViewSchedules = (appointmentId: number) => {
    window.location.href = route('appointment.schedules.index', { appointment_id: appointmentId })
  }

  const formattedEvents = events.map(event => ({
    ...event,
    color: eventTypes.find(t => t.name === event.status)?.color || '#6b7280'
  }))

  return (
    <AuthenticatedLayout
      breadcrumbs={breadcrumbs}
      pageTitle={t('Appointment Calendar')}
    >
      <Head title={t('Appointment Calendar')} />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2">
          <CalendarView events={formattedEvents} />
        </div>

        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Clock className="h-4 w-4" />
                {t('All Events')}
              </CardTitle>
            </CardHeader>
            <CardContent className="max-h-[75vh] overflow-y-auto">
              {events.length === 0 ? (
                <div className="text-center py-8 text-gray-500">
                  <CalendarIcon className="h-12 w-12 mx-auto mb-2 opacity-50" />
                  <p>{t('No Events')}</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {events.map(event => (
                    <div key={event.id} className="border rounded-lg p-4">
                      <div className="flex items-start justify-between mb-2">
                        <h4 className="font-medium">{event.title}</h4>
                        <Badge style={{ backgroundColor: `${eventTypes.find(t => t.name === event.status)?.color || '#6b7280'}1A`, color: eventTypes.find(t => t.name === event.status)?.color || '#6b7280' }}>
                          {event.status}
                        </Badge>
                      </div>

                      <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center gap-2 text-sm text-gray-600">
                          <Clock className="h-4 w-4" />
                          <span>{formatDate(event.date)} - {formatTime(event.time)}</span>
                        </div>
                        <div className="flex items-center gap-1">
                          <TooltipProvider>
                            {event.schedule_data?.appointment_id && auth.user?.permissions?.includes('view-schedules') && (
                              <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => handleViewSchedules(event.schedule_data.appointment_id)}
                                    className="h-8 w-8 p-0 text-orange-600 hover:text-orange-700"
                                  >
                                    <Clock className="h-4 w-4" />
                                  </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                  <p>{t('View Schedules')} ({appointmentScheduleCounts[event.schedule_data.appointment_id] || 0})</p>
                                </TooltipContent>
                              </Tooltip>
                            )}
                            {auth.user?.permissions?.includes('delete-schedules') && (
                              <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => openDeleteDialog(event.id)}
                                    className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                  >
                                    <Trash2 className="h-4 w-4" />
                                  </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                  <p>{t('Delete')}</p>
                                </TooltipContent>
                              </Tooltip>
                            )}
                          </TooltipProvider>
                        </div>
                      </div>

                      <p className="text-sm text-gray-600 mb-3">{event.schedule_data?.name || event.description}</p>

                      <div className="flex items-center gap-2 text-sm">
                        <Users className="h-4 w-4 text-gray-400" />
                        <span className="text-gray-600">
                          {event.attendees.join(', ')}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
      <ConfirmationDialog
        open={deleteState.isOpen}
        onOpenChange={closeDeleteDialog}
        title={t('Delete Schedule')}
        message={deleteState.message}
        confirmText={t('Delete')}
        onConfirm={confirmDelete}
        variant="destructive"
      />
    </AuthenticatedLayout>
  )
}