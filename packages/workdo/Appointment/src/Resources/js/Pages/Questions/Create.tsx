import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { CreateQuestionProps, CreateQuestionFormData } from './types';
import { useState } from 'react';
import { Repeater, RepeaterItem } from '@/components/ui/repeater';

function Create({ onSuccess }: CreateQuestionProps) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm<CreateQuestionFormData>({
        question_name: '',
        question_type: '0',
        available_answers: '[]',
        required_answer: false,
        enabled: false,
    });

    const [answersArray, setAnswersArray] = useState<RepeaterItem[]>([
        { id: '1', answer: '' }
    ]);

    const handleAnswersChange = (items: RepeaterItem[]) => {
        setAnswersArray(items);
        const answers = items
            .map((item) => (item.answer ?? '').toString())
            .map((answer) => answer.trim())
            .filter((answer) => answer !== '');
        setData('available_answers', JSON.stringify(answers));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        post(route('appointment.questions.store'), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Create Question')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="question_name">{t('Question Name')}</Label>
                    <Input
                        id="question_name"
                        value={data.question_name}
                        onChange={(e) => setData('question_name', e.target.value)}
                        placeholder={t('Enter Question Name')}
                        required
                    />
                    <InputError message={errors.question_name} />
                </div>

                <div>
                    <Label htmlFor="question_type">{t('Question Type')}</Label>
                    <Select value={data.question_type?.toString() || '0'} onValueChange={(value) => {
                        setData('question_type', value);
                        if (value === '2') {
                            setData('available_answers', '[]');
                            setAnswersArray([]);
                        } else if (data.question_type === '2' && value !== '2') {
                            setData('available_answers', '[]');
                            setAnswersArray([{ id: '1', answer: '' }]);
                        }
                    }}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="0">{t('radio')}</SelectItem>
                            <SelectItem value="1">{t('dropdown')}</SelectItem>
                            <SelectItem value="2">{t('text')}</SelectItem>
                            <SelectItem value="3">{t('checkbox')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.question_type} />
                </div>

                {data.question_type !== '2' && (
                    <div>
                        <Label>{t('Available Answers')}</Label>
                        <Repeater
                            fields={[
                                {
                                    name: 'answer',
                                    label: t('Available Answer'),
                                    type: 'text',
                                    placeholder: t('Enter Available Answer'),
                                    required: true
                                }
                            ]}
                            value={answersArray}
                            onChange={handleAnswersChange}
                            addButtonText={t('Add Answer')}
                            deleteTooltipText={t('Delete')}
                            minItems={1}
                            showDefault={true}
                            className="space-y-2"
                            layout={{ type: 'stack', gap: '2' }}
                        />
                        <InputError message={errors.available_answers} />
                    </div>
                )}

                <div className="grid grid-cols-2 gap-4">
                    <div className="flex items-center space-x-2">
                        <Switch
                            id="required_answer"
                            checked={data.required_answer || false}
                            onCheckedChange={(checked) => setData('required_answer', !!checked)}
                        />
                        <Label htmlFor="required_answer" className="cursor-pointer">{t('Required Answer')}</Label>
                        <InputError message={errors.required_answer} />
                    </div>

                    <div className="flex items-center space-x-2">
                        <Switch
                            id="enabled"
                            checked={data.enabled || false}
                            onCheckedChange={(checked) => setData('enabled', !!checked)}
                        />
                        <Label htmlFor="enabled" className="cursor-pointer">{t('Enabled')}</Label>
                        <InputError message={errors.enabled} />
                    </div>
                </div>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Creating...') : t('Create')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}

export default Create;
