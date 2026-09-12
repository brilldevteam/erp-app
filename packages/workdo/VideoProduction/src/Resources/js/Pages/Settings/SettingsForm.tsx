import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { useTranslation } from 'react-i18next';

const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

export default function SettingsForm({ settings }: any) {
    const { t } = useTranslation();
    const form = useForm({
        monthly_reel_target: settings.monthly_reel_target,
        monthly_static_target: settings.monthly_static_target,
        minimum_shoots: settings.minimum_shoots,
        maximum_shoots: settings.maximum_shoots,
        included_hours_per_shoot: settings.included_hours_per_shoot,
        required_lead_days: settings.required_lead_days,
        included_revisions: settings.included_revisions,
        working_days: settings.working_days || days.slice(0, 5),
        workflow_effective_date: settings.workflow_effective_date?.slice(0, 10) || '',
    });
    const numberField = (key: string, label: string, step = '1') => <div><Label>{label}</Label><Input type="number" min="0" step={step} value={(form.data as any)[key]} onChange={e => form.setData(key as any, e.target.value as any)} /><InputError message={(form.errors as any)[key]} /></div>;
    const toggleDay = (day: string, checked: boolean) => form.setData('working_days', checked ? [...form.data.working_days, day] : form.data.working_days.filter(item => item !== day));

    const workflow = [
        ['1', 'Create a Shoot ID', 'Add every planned or unplanned shoot as soon as the request is received.'],
        ['2', 'Record exact timestamps', 'Enter when requests, confirmations, content plans, and scripts were actually received.'],
        ['3', 'Create each deliverable', 'Add every reel or static post and link it to the Shoot ID where applicable.'],
        ['4', 'Log every revision', 'Record every revision round, request, category, delivery date, and evidence.'],
        ['5', 'Track working hours', 'Log shooting, editing, revisions, planning, meetings, setup, and other production work.'],
        ['6', 'Save evidence', 'Keep screenshots and source files in Drive, then add the link to the relevant record and Evidence Register.'],
        ['7', 'Review monthly', 'Choose the reporting month on Overview before every management review.'],
    ];
    const definitions = [
        ['Planned Shoot', 'Normal scheduled shoot. The configured script and plan lead-time rule applies.'],
        ['Unplanned Shoot', 'Last-minute shoot subject to production-team availability. Lead-time compliance is N/A.'],
        ['Ready for Production', 'Topic and final script are confirmed; duration, B-roll, and editing references are defined or N/A; medical content is approved.'],
        ['Waiting for Input', 'At least one required pre-production input is incomplete, missing, or not approved.'],
        ['Brill Turnaround', 'Working days between complete client inputs and Brill delivery, excluding client review time.'],
        ['Client Review Time', 'Working days between Brill delivery and the next client approval or revision response.'],
        ['Revision 4+', 'A revision number above the revisions included in the agreement.'],
    ];

    return <div className="space-y-5"><form onSubmit={e => { e.preventDefault(); form.put(route('video-production.settings.update'), { preserveScroll: true }); }}>
        <Card><CardHeader><CardTitle>{t('Production Settings')}</CardTitle></CardHeader><CardContent className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            {numberField('monthly_reel_target', t('Monthly Reel Target'))}
            {numberField('monthly_static_target', t('Monthly Static Post Target'))}
            {numberField('minimum_shoots', t('Minimum Shoot Sessions'))}
            {numberField('maximum_shoots', t('Maximum Shoot Sessions'))}
            {numberField('included_hours_per_shoot', t('Included Hours per Shoot'), '0.25')}
            {numberField('required_lead_days', t('Required Lead Time (working days)'))}
            {numberField('included_revisions', t('Included Revisions'))}
            <div><Label>{t('Workflow Effective Date')}</Label><Input type="date" value={form.data.workflow_effective_date} onChange={e => form.setData('workflow_effective_date', e.target.value)} /><InputError message={form.errors.workflow_effective_date} /></div>
            <div className="md:col-span-2 lg:col-span-3"><Label>{t('Working Days')}</Label><div className="mt-2 flex flex-wrap gap-4">{days.map(day => <label key={day} className="flex items-center gap-2 capitalize"><Checkbox checked={form.data.working_days.includes(day)} onCheckedChange={checked => toggleDay(day, checked === true)} />{t(day)}</label>)}</div><InputError message={form.errors.working_days} /></div>
            <div className="flex justify-end md:col-span-2 lg:col-span-3"><Button disabled={form.processing}>{t('Save Settings')}</Button></div>
        </CardContent></Card>
    </form>
        <div className="grid gap-5 xl:grid-cols-2">
            <Card><CardHeader><CardTitle>{t('How to Use This System')}</CardTitle></CardHeader><CardContent className="space-y-3">{workflow.map(([number, title, text]) => <div key={number} className="flex gap-3 rounded-lg border p-3"><span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">{number}</span><div><p className="font-medium">{t(title)}</p><p className="text-sm text-muted-foreground">{t(text)}</p></div></div>)}</CardContent></Card>
            <Card><CardHeader><CardTitle>{t('Workflow Definitions')}</CardTitle></CardHeader><CardContent className="space-y-3">{definitions.map(([title, text]) => <div key={title} className="rounded-lg border p-3"><p className="font-medium">{t(title)}</p><p className="text-sm text-muted-foreground">{t(text)}</p></div>)}</CardContent></Card>
        </div>
        <Card><CardHeader><CardTitle>{t('Evidence Standard')}</CardTitle></CardHeader><CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div><Label>{t('Recommended folder structure')}</Label><p className="mt-1 text-sm text-muted-foreground">Company / YYYY / Month / Shoot ID / 01 Script & Brief / 02 Shoot / 03 V1 / 04 Revisions / 05 Final Approval</p></div>
            <div><Label>{t('Recommended screenshot name')}</Label><p className="mt-1 text-sm text-muted-foreground">DOC_R041_ScriptReceived_YYYY-MM-DD_HHMM.png</p></div>
            <div><Label>{t('Neutral wording')}</Label><p className="mt-1 text-sm text-muted-foreground">Script Lead Time, Awaiting Client Input, Client Review Time, and Brill Turnaround.</p></div>
            <div><Label>{t('Evidence reminder')}</Label><p className="mt-1 text-sm text-muted-foreground">Save important scripts, confirmations, deliveries, revisions, approvals, and disputes with exact timestamps.</p></div>
        </CardContent></Card>
    </div>;
}
