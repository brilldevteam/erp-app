import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { PhoneInputComponent } from '@/components/ui/phone-input';
import { toast } from 'sonner';

interface ActionConfigFieldProps {
    actionType: string;
    config: any;
    onConfigChange: (field: string, value: string) => void;
}

export default function ActionConfigField({ actionType, config, onConfigChange }: ActionConfigFieldProps) {
    const { t } = useTranslation();
    const [staffList, setStaffList] = useState<any[]>([]);

    useEffect(() => {
        if ((actionType.toLowerCase() === 'email' && config.recipient_type === 'staff') ||
            (actionType.toLowerCase() === 'twilio' && config.recipient_type === 'staff') ||
            (actionType.toLowerCase() === 'whatsappapi' && config.recipient_type === 'staff')) {
            loadStaffList();
        }
    }, [actionType, config.recipient_type]);

    const loadStaffList = async () => {
        try {
            const response = await fetch(route('workflow.staff-list'));
            if (response.ok) {
                const data = await response.json();
                setStaffList(data);
            }
        } catch (error) {
            toast.error('Error loading field values: ' + error);
        }
    };

    const normalizedActionType = actionType.toLowerCase();

    if (normalizedActionType === 'email') {
        return (
            <div className="space-y-4">
                <div>
                    <Label className="text-sm font-medium text-gray-700">{t('Recipient Type')}</Label>
                    <RadioGroup
                        value={config.recipient_type || 'custom'}
                        onValueChange={(value) => onConfigChange('recipient_type', value)}
                        className="flex gap-4 mt-2"
                    >
                        <div className="flex items-center space-x-2">
                            <RadioGroupItem value="custom" id="custom" />
                            <Label htmlFor="custom" className="font-normal cursor-pointer">{t('Custom')}</Label>
                        </div>
                        <div className="flex items-center space-x-2">
                            <RadioGroupItem value="staff" id="staff" />
                            <Label htmlFor="staff" className="font-normal cursor-pointer">{t('Staff')}</Label>
                        </div>
                    </RadioGroup>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label className="text-sm font-medium text-gray-700">{t('Email')}</Label>
                        {config.recipient_type === 'staff' ? (
                            <Select
                                value={config.to || ''}
                                onValueChange={(value) => onConfigChange('to', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder={t('Select Staff')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {staffList.map((staff) => (
                                        <SelectItem key={staff.id} value={staff.email}>
                                            {staff.name} ({staff.email})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : (
                            <Input
                                type="email"
                                value={config.to || ''}
                                onChange={(e) => onConfigChange('to', e.target.value)}
                                placeholder={t('Please enter email address')}
                            />
                        )}
                    </div>
                </div>
            </div>
        );
    }

    if (normalizedActionType === 'slack') {
        return (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <Label className="text-sm font-medium text-gray-700">{t('Slack Webhook URL')}</Label>
                    <Input
                        type="url"
                        value={config.webhook_url || ''}
                        onChange={(e) => onConfigChange('webhook_url', e.target.value)}
                        placeholder={t('Please Enter Slack Webhook URL')}
                    />
                </div>
            </div>
        );
    }

    if (normalizedActionType === 'twilio') {
        return (
            <div className="space-y-4">
                <div>
                    <Label className="text-sm font-medium text-gray-700">{t('Recipient Type')}</Label>
                    <RadioGroup
                        value={config.recipient_type || 'custom'}
                        onValueChange={(value) => onConfigChange('recipient_type', value)}
                        className="flex gap-4 mt-2"
                    >
                        <div className="flex items-center space-x-2">
                            <RadioGroupItem value="custom" id="twilio-custom" />
                            <Label htmlFor="twilio-custom" className="font-normal cursor-pointer">{t('Custom')}</Label>
                        </div>
                        <div className="flex items-center space-x-2">
                            <RadioGroupItem value="staff" id="twilio-staff" />
                            <Label htmlFor="twilio-staff" className="font-normal cursor-pointer">{t('Staff')}</Label>
                        </div>
                    </RadioGroup>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        {config.recipient_type === 'staff' ? (
                            <div>
                                <Label className="text-sm font-medium text-gray-700">{t('Mobile Number')}</Label>
                                <Select
                                    value={config.mobile_number || ''}
                                    onValueChange={(value) => onConfigChange('mobile_number', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Select Staff')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {staffList.map((staff) => (
                                            <SelectItem key={staff.id} value={staff.mobile_no}>
                                                {staff.name} ({staff.mobile_no})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        ) : (
                            <PhoneInputComponent
                                label={t('Mobile Number')}
                                value={config.mobile_number || ''}
                                onChange={(value) => onConfigChange('mobile_number', value)}
                            />
                        )}
                    </div>
                </div>
            </div>
        );
    }

    if (normalizedActionType === 'telegram') {
        return (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <Label className="text-sm font-medium text-gray-700">{t('Telegram Access Token')}</Label>
                    <Input
                        type="text"
                        value={config.access_token || ''}
                        onChange={(e) => onConfigChange('access_token', e.target.value)}
                        placeholder={t('Please enter Telegram Access Token')}
                    />
                </div>
                <div>
                    <Label className="text-sm font-medium text-gray-700">{t('Telegram ChatID')}</Label>
                    <Input
                        type="text"
                        value={config.chat_id || ''}
                        onChange={(e) => onConfigChange('chat_id', e.target.value)}
                        placeholder={t('Please enter ChatID')}
                    />
                </div>
            </div>
        );
    }

    if (normalizedActionType === 'whatsappapi') {
        return (
            <div className="space-y-4">
                <div>
                    <Label className="text-sm font-medium text-gray-700">{t('Recipient Type')}</Label>
                    <RadioGroup
                        value={config.recipient_type || 'custom'}
                        onValueChange={(value) => onConfigChange('recipient_type', value)}
                        className="flex gap-4 mt-2"
                    >
                        <div className="flex items-center space-x-2">
                            <RadioGroupItem value="custom" id="whatsapp-custom" />
                            <Label htmlFor="whatsapp-custom" className="font-normal cursor-pointer">{t('Custom')}</Label>
                        </div>
                        <div className="flex items-center space-x-2">
                            <RadioGroupItem value="staff" id="whatsapp-staff" />
                            <Label htmlFor="whatsapp-staff" className="font-normal cursor-pointer">{t('Staff')}</Label>
                        </div>
                    </RadioGroup>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label className="text-sm font-medium text-gray-700">{t('WhatsApp Access Token')}</Label>
                        <Input
                            type="text"
                            value={config.access_token || ''}
                            onChange={(e) => onConfigChange('access_token', e.target.value)}
                            placeholder={t('Please enter WhatsApp Access Token')}
                        />
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-700">{t('Phone Number ID')}</Label>
                        <Input
                            type="text"
                            value={config.phone_number_id || ''}
                            onChange={(e) => onConfigChange('phone_number_id', e.target.value)}
                            placeholder={t('Please enter Phone Number ID')}
                        />
                    </div>
                    <div>
                        {config.recipient_type === 'staff' ? (
                            <div>
                                <Label className="text-sm font-medium text-gray-700">{t('Mobile Number')}</Label>
                                <Select
                                    value={config.mobile_number || ''}
                                    onValueChange={(value) => onConfigChange('mobile_number', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Select Staff')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {staffList.map((staff) => (
                                            <SelectItem key={staff.id} value={staff.mobile_no}>
                                                {staff.name} ({staff.mobile_no})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        ) : (
                            <PhoneInputComponent
                                label={t('Mobile Number')}
                                value={config.mobile_number || ''}
                                onChange={(value) => onConfigChange('mobile_number', value)}
                            />
                        )}
                    </div>
                </div>
            </div>
        );
    }

    return null;
}
