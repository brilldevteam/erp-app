import React from 'react';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { useTranslation } from 'react-i18next';

interface Field {
    name: string;
    type: string;
    model_name?: string;
}

interface FieldValue {
    id: number | string;
    name: string;
}

interface ConditionValueFieldProps {
    selectedField: Field | undefined;
    value: string;
    onChange: (value: string) => void;
    fieldValues?: FieldValue[];
}

function ConditionValueField({ 
    selectedField, 
    value, 
    onChange, 
    fieldValues = [] 
}: ConditionValueFieldProps) {
    const { t } = useTranslation();

    if (!selectedField) {
        return (
            <Input
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={t('Enter value')}
            />
        );
    }

    switch (selectedField.type) {
        case 'date':
            return (
                <DatePicker
                    value={value}
                    onChange={(date) => onChange(date)}
                    placeholder={t('Select date')}
                />
            );

        case 'email':
            return (
                <Input
                    type="email"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={t('Enter email')}
                />
            );

        case 'number':
            return (
                <Input
                    type="number"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={t('Enter number')}
                />
            );

        case 'select':
            if (fieldValues.length > 0) {
                return (
                    <Select value={value} onValueChange={onChange}>
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select Value')} />
                        </SelectTrigger>
                        <SelectContent>
                            {fieldValues.map((fieldValue) => (
                                <SelectItem key={fieldValue.id} value={String(fieldValue.id)}>
                                    {fieldValue.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                );
            }
            return (
                <Input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={t('Enter value')}
                />
            );

        case 'text':
        default:
            return (
                <Input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={t('Enter text')}
                />
            );
    }
}

export default ConditionValueField;