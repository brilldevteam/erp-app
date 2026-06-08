import { useState, useMemo, useEffect } from 'react';
import { useForm, router } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFormFields } from '@/hooks/useFormFields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Checkbox } from '@/components/ui/checkbox';
import { PhoneInputComponent } from '@/components/ui/phone-input';
import { Calendar, Clock, User, Mail, Phone, Search } from 'lucide-react';
import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import PublicLayout from './components/PublicLayout';

// Custom DatePicker styles
const datePickerStyles = `
  .react-datepicker-wrapper {
    width: 100%;
  }
  .react-datepicker__input-container input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
  }
  .react-datepicker__input-container input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 1px #3b82f6;
  }
  .react-datepicker {
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  }
  .react-datepicker__header {
    background-color: #3b82f6;
    border-bottom: none;
    border-radius: 0.5rem 0.5rem 0 0;
  }
  .react-datepicker__current-month {
    color: white;
    font-weight: 600;
  }
  .react-datepicker__day--selected {
    background-color: #3b82f6;
    color: white;
  }
  .react-datepicker__day:hover {
    background-color: #dbeafe;
  }
`;

interface BookProps {
    appointment: {
        id: number;
        appointment_name: string;
        appointment_type: string;
        duration: string;
        week_day: string;
        phone_enable: number;
    };
    questions: Array<{
        id: number;
        question_name: string;
        question_type: number;
        available_answers: string;
        required_answer: boolean;
    }>;
    appointmentHours?: Array<{
        day_name: string;
        start_time: string;
        end_time: string;
        day_off: number;
    }>;
    userSlug?: string;
}

export default function Book({ appointment, questions, appointmentHours = [], userSlug }: BookProps) {
    const { t } = useTranslation();

    const [bookedSlots, setBookedSlots] = useState<string[]>([]);
    const tawktoFields = useFormFields('getIntegrationFields', {}, () => { }, {}, 'create', t, 'Appointment');
    const [loadingSlots, setLoadingSlots] = useState(false);
    const [questionErrors, setQuestionErrors] = useState<{[key: number]: string}>({});

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        date: '',
        start_time: '',
        end_time: '',
        questions: {}
    });

    const allowedWeekDays = useMemo(() => {
        if (!appointment.week_day) return [];
        try {
            const weekDayNames = JSON.parse(appointment.week_day);
            const dayMap = { sunday: 0, monday: 1, tuesday: 2, wednesday: 3, thursday: 4, friday: 5, saturday: 6 };
            const result = weekDayNames.map((day: string) => dayMap[day.toLowerCase()]).filter((day: number) => day !== undefined);
            return result;
        } catch (error) {
            return [];
        }
    }, [appointment.week_day]);

    const isDateAllowed = (date: Date) => {
        if (allowedWeekDays.length === 0) {
            return true;
        }
        const dayOfWeek = date.getDay();
        const allowed = allowedWeekDays.includes(dayOfWeek);
        return allowed;
    };

    const generateTimeSlots = useMemo(() => {
        if (!data.date || !appointmentHours || !Array.isArray(appointmentHours)) {
            return [];
        }

        const selectedDate = new Date(data.date);
        const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const dayName = dayNames[selectedDate.getDay()];

        const dayEntry = appointmentHours.find(hour => hour.day_name === dayName);
        if (!dayEntry || !dayEntry.start_time || !dayEntry.end_time) {
            return [];
        }

        const timeToMinutes = (time) => {
            const [hours, minutes] = time.split(':').map(Number);
            return (hours * 60) + minutes;
        };

        const minutesToTime = (minutes) => {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
        };

        const slots = [];
        const duration = parseInt(appointment.duration) || 60;
        const startMinutes = timeToMinutes(dayEntry.start_time);
        const endMinutes = timeToMinutes(dayEntry.end_time);

        for (let currentMinutes = startMinutes; currentMinutes < endMinutes; currentMinutes += duration) {
            const slotEndMinutes = currentMinutes + duration;
            if (slotEndMinutes > endMinutes) break;

            const startTime = minutesToTime(currentMinutes);
            const endTime = minutesToTime(slotEndMinutes);
            slots.push(`${startTime}-${endTime}`);
        }

        return slots;
    }, [appointment.duration, data.date, appointmentHours]);

    const fetchBookedSlots = async (date: string) => {
        setLoadingSlots(true);
        try {
            // Get encrypted ID and userSlug from current URL
            const urlParts = window.location.pathname.split('/');
            const encryptedId = urlParts[urlParts.indexOf('appointments') + 1];
            const response = await axios.get(route('appointment.public.booked-slots', { userSlug, encryptedId, date }));
            setBookedSlots(response.data.bookedSlots || []);
        } catch (error) {
            setBookedSlots([]);
        } finally {
            setLoadingSlots(false);
        }
    };

    const isSelectedDateDayOff = useMemo(() => {
        if (!data.date || !appointmentHours || !Array.isArray(appointmentHours)) {
            return false;
        }

        const selectedDate = new Date(data.date);
        const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const dayName = dayNames[selectedDate.getDay()];

        const dayOffEntry = appointmentHours.find(hour => hour.day_name === dayName);
        const isDayOff = dayOffEntry?.day_off === true || dayOffEntry?.day_off === 1;

        return isDayOff;
    }, [data.date, appointmentHours]);

    const isSelectedDateAllowed = useMemo(() => {
        if (!data.date) return true;
        const selectedDate = new Date(data.date);
        const allowed = isDateAllowed(selectedDate);
        return allowed;
    }, [data.date, allowedWeekDays]);

    useEffect(() => {
        if (data.date && isSelectedDateAllowed && !isSelectedDateDayOff) {
            fetchBookedSlots(data.date);
        } else {
            setBookedSlots([]);
        }
    }, [data.date, isSelectedDateAllowed, isSelectedDateDayOff]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!isSelectedDateAllowed || isSelectedDateDayOff) {
            alert(t('This day is off. Please select a different date.'));
            return;
        }

        // Validate required questions
        const requiredQuestions = questions.filter(q => q.required_answer);
        const newQuestionErrors: {[key: number]: string} = {};

        for (const question of requiredQuestions) {
            const answer = data.questions[question.id];
            if (!answer || answer.trim() === '') {
                newQuestionErrors[question.id] = `${question.question_name} is required`;
            }
        }

        setQuestionErrors(newQuestionErrors);

        if (Object.keys(newQuestionErrors).length > 0) {
            return;
        }

        // Get encrypted ID and userSlug from current URL
        const urlParts = window.location.pathname.split('/');
        const encryptedId = urlParts[urlParts.indexOf('appointments') + 1];
        post(route('appointment.public.store', { userSlug, encryptedId }));
    };

    return (
        <PublicLayout
            title={`${t('Book')} ${appointment.appointment_name}`}
        >
            <style dangerouslySetInnerHTML={{ __html: datePickerStyles }} />

            {/* Hero Section */}
            <section className="bg-gradient-to-br from-blue-50 to-indigo-100 py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 className="text-5xl font-bold text-gray-900 mb-6">
                        {t('Book Your')} <span className="text-blue-600">{appointment.appointment_name}</span>
                    </h1>
                    <p className="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
                        {t('Schedule your consultation with our experts. Professional, reliable, and convenient booking system.')}
                    </p>
                    <div className="flex justify-center items-center space-x-8 text-sm text-gray-500">
                        <div className="flex items-center">
                            <Clock className="h-5 w-5 mr-2" />
                            {appointment.duration} {t('minutes')}
                        </div>
                        <div className="flex items-center">
                            <Calendar className="h-5 w-5 mr-2" />
                            {t('Available weekdays')}
                        </div>
                        <div className="flex items-center">
                            <User className="h-5 w-5 mr-2" />
                            {t('Professional consultation')}
                        </div>
                    </div>
                </div>
            </section>

            {/* Booking Form Section */}
            <section className="py-16 bg-white">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                        <div className="bg-gradient-to-r from-gray-900 to-gray-800 px-8 py-8 text-center">
                            <h2 className="text-3xl font-bold text-white mb-2">{t('Book Your Appointment')}</h2>
                            <p className="text-gray-300">{t('Fill out the form below to schedule your meeting')}</p>
                        </div>

                        <div className="p-8">
                    <div className="mb-8">
                        <div className="flex items-center mb-6">
                            <User className="h-5 w-5 text-blue-600 mr-2" />
                            <h2 className="text-lg font-semibold text-blue-600">{t('Personal Information')}</h2>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-8">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <Label htmlFor="name" className="text-sm font-medium text-gray-700">{t('Full Name')}</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder={t('Enter your full name')}
                                        className="mt-1 border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    />
                                    {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="email" className="text-sm font-medium text-gray-700">{t('Email Address')}</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder={t('Enter your email address')}
                                        className="mt-1 border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    />
                                    {errors.email && <p className="text-red-500 text-sm mt-1">{errors.email}</p>}
                                </div>
                            </div>

                            <div className={`grid grid-cols-1 ${(appointment.phone_enable == 1 || appointment.phone_enable === '1') ? 'md:grid-cols-2' : 'md:grid-cols-1'} gap-6`}>
                                {(appointment.phone_enable == 1 || appointment.phone_enable === '1') && (
                                    <PhoneInputComponent
                                        id="phone"
                                        label={t('Phone Number')}
                                        value={data.phone}
                                        onChange={(value) => setData('phone', value)}
                                        placeholder={t('Enter phone number')}
                                        className="mt-1 border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                        error={errors.phone}
                                        required
                                    />
                                )}

                                <div>
                                    <Label htmlFor="date" className="text-sm font-medium text-gray-700" required>{t('Preferred Date')}</Label>
                                    <DatePicker
                                        selected={data.date ? new Date(data.date) : null}
                                        onChange={(date: Date | null) => {
                                            if (date) {
                                                const year = date.getFullYear();
                                                const month = String(date.getMonth() + 1).padStart(2, '0');
                                                const day = String(date.getDate()).padStart(2, '0');
                                                setData('date', `${year}-${month}-${day}`);
                                            } else {
                                                setData('date', '');
                                            }
                                        }}
                                        filterDate={isDateAllowed}
                                        minDate={new Date()}
                                        placeholderText={t('Select a date')}
                                        dateFormat="dd/MM/yyyy"
                                        className="mt-1"
                                    />
                                    {allowedWeekDays.length > 0 && (
                                        <p className="text-xs text-gray-500 mt-1">
                                            {t('Available on')}: {allowedWeekDays.map(day =>
                                                [t('Sunday'), t('Monday'), t('Tuesday'), t('Wednesday'), t('Thursday'), t('Friday'), t('Saturday')][day]
                                            ).join(', ')}
                                        </p>
                                    )}
                                    {errors.date && <p className="text-red-500 text-sm mt-1">{errors.date}</p>}
                                    {isSelectedDateDayOff && data.date && (
                                        <div className="bg-red-50 border border-red-200 rounded-lg p-3 mt-2">
                                            <p className="text-red-600 text-sm">
                                                ⚠️ {t('This day is off. Please select a different date.')}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Time Selection */}
                            {data.date && isSelectedDateAllowed && !isSelectedDateDayOff && (
                                <div>
                                    <div className="flex items-center mb-4">
                                        <Clock className="h-5 w-5 text-blue-600 mr-2" />
                                        <h3 className="text-lg font-semibold text-blue-600">{t('Select Time Slot')}</h3>
                                        {loadingSlots && <span className="ml-2 text-sm text-gray-500">{t('Loading slots...')}</span>}
                                    </div>

                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        {generateTimeSlots.map((timeSlot) => {
                                            const [start, end] = timeSlot.split('-');
                                            const isSelected = data.start_time === start && data.end_time === end;
                                            const isBooked = bookedSlots.includes(`${start}-${end}`);
                                            const isDisabled = isBooked || !isSelectedDateAllowed || loadingSlots;

                                            return (
                                                <button
                                                    key={timeSlot}
                                                    type="button"
                                                    disabled={isDisabled}
                                                    onClick={() => {
                                                        if (!isDisabled) {
                                                            setData('start_time', start);
                                                            setData('end_time', end);
                                                        }
                                                    }}
                                                    className={`p-3 text-sm border rounded-lg transition-colors ${
                                                        isSelected
                                                            ? 'bg-blue-600 text-white border-blue-600'
                                                            : isBooked
                                                            ? 'bg-red-100 text-red-600 border-red-300 cursor-not-allowed'
                                                            : !isSelectedDateAllowed
                                                            ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'
                                                            : 'bg-white text-gray-700 border-gray-300 hover:border-blue-300'
                                                    }`}
                                                >
                                                    {timeSlot}
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {(errors.start_time || errors.end_time) && (
                                        <p className="text-red-500 text-sm mt-2">{t('Please select a time slot')}</p>
                                    )}
                                </div>
                            )}

                            {!data.date && (
                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <p className="text-blue-600 text-sm">
                                        📅 {t('Please select a date first to see available time slots')}
                                    </p>
                                </div>
                            )}

                            {questions.length > 0 && (
                                <div>
                                    <div className="flex items-center mb-4">
                                        <div className="h-5 w-5 text-blue-600 mr-2">📝</div>
                                        <h3 className="text-lg font-semibold text-blue-600">{t('Additional Information')}</h3>
                                    </div>
                                    <div className="space-y-4">
                                        {questions.map((question) => {
                                            let options = [];
                                            try {
                                                options = question.available_answers ? JSON.parse(question.available_answers) : [];
                                            } catch (error) {
                                                options = question.available_answers ? question.available_answers.split(',').map(opt => opt.trim()) : [];
                                            }

                                            return (
                                                <div key={question.id}>
                                                    <Label className="text-sm font-medium text-gray-700" required>
                                                        {question.question_name} {question.required_answer}
                                                    </Label>

                                                    {/* Type 0: Radio Buttons */}
                                                    {(question.question_type == 0 || question.question_type === '0') && (
                                                        <>
                                                            <RadioGroup
                                                                value={data.questions[question.id] || ''}
                                                                onValueChange={(value) => {
                                                                    setData('questions', {
                                                                        ...data.questions,
                                                                        [question.id]: value
                                                                    });
                                                                    if (questionErrors[question.id]) {
                                                                        setQuestionErrors(prev => {
                                                                            const newErrors = {...prev};
                                                                            delete newErrors[question.id];
                                                                            return newErrors;
                                                                        });
                                                                    }
                                                                }}
                                                                direction="vertical"
                                                                className="mt-2"
                                                            >
                                                                {options.map((option, index) => (
                                                                    <div key={index} className="flex items-center space-x-2">
                                                                        <RadioGroupItem value={option} id={`${question.id}_${index}`} />
                                                                        <Label htmlFor={`${question.id}_${index}`} className="text-sm text-gray-700">{option}</Label>
                                                                    </div>
                                                                ))}
                                                            </RadioGroup>
                                                            {questionErrors[question.id] && <p className="text-red-500 text-sm mt-1">{questionErrors[question.id]}</p>}
                                                        </>
                                                    )}

                                                    {/* Type 1: Dropdown */}
                                                    {(question.question_type == 1 || question.question_type === '1') && (
                                                        <>
                                                            <Select
                                                                value={data.questions[question.id] || ''}
                                                                onValueChange={(value) => {
                                                                    setData('questions', {
                                                                        ...data.questions,
                                                                        [question.id]: value
                                                                    });
                                                                    if (questionErrors[question.id]) {
                                                                        setQuestionErrors(prev => {
                                                                            const newErrors = {...prev};
                                                                            delete newErrors[question.id];
                                                                            return newErrors;
                                                                        });
                                                                    }
                                                                }}
                                                            >
                                                                <SelectTrigger className="mt-1">
                                                                    <SelectValue placeholder="Select an option" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {options.map((option, index) => (
                                                                        <SelectItem key={index} value={option}>{option}</SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                            {questionErrors[question.id] && <p className="text-red-500 text-sm mt-1">{questionErrors[question.id]}</p>}
                                                        </>
                                                    )}

                                                    {/* Type 2: Text Input */}
                                                    {(question.question_type == 2 || question.question_type === '2') && (
                                                        <>
                                                            <Input
                                                                type="text"
                                                                value={data.questions[question.id] || ''}
                                                                onChange={(e) => {
                                                                    setData('questions', {
                                                                        ...data.questions,
                                                                        [question.id]: e.target.value
                                                                    });
                                                                    if (questionErrors[question.id]) {
                                                                        setQuestionErrors(prev => {
                                                                            const newErrors = {...prev};
                                                                            delete newErrors[question.id];
                                                                            return newErrors;
                                                                        });
                                                                    }
                                                                }}
                                                                placeholder={`Enter ${question.question_name.toLowerCase()}`}
                                                                className="mt-1"
                                                                required={question.required_answer}
                                                            />
                                                            {questionErrors[question.id] && <p className="text-red-500 text-sm mt-1">{questionErrors[question.id]}</p>}
                                                        </>
                                                    )}

                                                    {/* Type 3: Checkboxes */}
                                                    {(question.question_type == 3 || question.question_type === '3') && (
                                                        <>
                                                            <div className="mt-2 space-y-2">
                                                                {options.map((option, index) => {
                                                                    const selectedOptions = data.questions[question.id] ? data.questions[question.id].split(',') : [];
                                                                    return (
                                                                        <div key={index} className="flex items-center space-x-2">
                                                                            <Checkbox
                                                                                id={`${question.id}_${index}`}
                                                                                checked={selectedOptions.includes(option)}
                                                                                onCheckedChange={(checked) => {
                                                                                    let newOptions = [...selectedOptions];
                                                                                    if (checked) {
                                                                                        newOptions.push(option);
                                                                                    } else {
                                                                                        newOptions = newOptions.filter(opt => opt !== option);
                                                                                    }
                                                                                    setData('questions', {
                                                                                        ...data.questions,
                                                                                        [question.id]: newOptions.join(',')
                                                                                    });
                                                                                    if (questionErrors[question.id]) {
                                                                                        setQuestionErrors(prev => {
                                                                                            const newErrors = {...prev};
                                                                                            delete newErrors[question.id];
                                                                                            return newErrors;
                                                                                        });
                                                                                    }
                                                                                }}
                                                                            />
                                                                            <Label htmlFor={`${question.id}_${index}`} className="text-sm text-gray-700">{option}</Label>
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                            {questionErrors[question.id] && <p className="text-red-500 text-sm mt-1">{questionErrors[question.id]}</p>}
                                                        </>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* Action Buttons */}
                            <div className="flex gap-4 pt-8">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-4 text-lg font-semibold rounded-xl shadow-lg"
                                >
                                    {processing ? t('Creating Appointment...') : t('Book Appointment')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="flex-1 border-2 border-gray-300 text-gray-700 py-4 text-lg font-semibold rounded-xl hover:bg-gray-50"
                                    onClick={() => {
                                        setData({
                                            name: '',
                                            email: '',
                                            phone: '',
                                            date: '',
                                            start_time: '',
                                            end_time: '',
                                            questions: {}
                                        });
                                    }}
                                >
                                    {t('Clear Form')}
                                </Button>
                            </div>
                        </form>
                    </div>
                        </div>
                    </div>
                </div>
            </section>
            {/* TawktoMessenger Integration */}
            {tawktoFields.map((field) => (
                <div key={field.id}>
                    {field.component}
                </div>
            ))}
        </PublicLayout>
    );
}
