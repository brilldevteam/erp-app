import { useMemo, useState } from 'react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

const COUNTRY_CODES = [
    'AD','AE','AF','AG','AI','AL','AM','AO','AQ','AR','AS','AT','AU','AW','AX','AZ','BA','BB','BD','BE','BF','BG','BH','BI','BJ','BL','BM','BN','BO','BQ','BR','BS','BT','BV','BW','BY','BZ','CA','CC','CD','CF','CG','CH','CI','CK','CL','CM','CN','CO','CR','CU','CV','CW','CX','CY','CZ','DE','DJ','DK','DM','DO','DZ','EC','EE','EG','EH','ER','ES','ET','FI','FJ','FK','FM','FO','FR','GA','GB','GD','GE','GF','GG','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT','GU','GW','GY','HK','HM','HN','HR','HT','HU','ID','IE','IL','IM','IN','IO','IQ','IR','IS','IT','JE','JM','JO','JP','KE','KG','KH','KI','KM','KN','KP','KR','KW','KY','KZ','LA','LB','LC','LI','LK','LR','LS','LT','LU','LV','LY','MA','MC','MD','ME','MF','MG','MH','MK','ML','MM','MN','MO','MP','MQ','MR','MS','MT','MU','MV','MW','MX','MY','MZ','NA','NC','NE','NF','NG','NI','NL','NO','NP','NR','NU','NZ','OM','PA','PE','PF','PG','PH','PK','PL','PM','PN','PR','PS','PT','PW','PY','QA','RE','RO','RS','RU','RW','SA','SB','SC','SD','SE','SG','SH','SI','SJ','SK','SL','SM','SN','SO','SR','SS','ST','SV','SX','SY','SZ','TC','TD','TF','TG','TH','TJ','TK','TL','TM','TN','TO','TR','TT','TV','TW','TZ','UA','UG','UM','US','UY','UZ','VA','VC','VE','VG','VI','VN','VU','WF','WS','YE','YT','ZA','ZM','ZW',
] as const;

const flagFor = (code: string) => String.fromCodePoint(...code.split('').map((char) => 127397 + char.charCodeAt(0)));

const regionName = (code: string, locale: string) => {
    try {
        return new Intl.DisplayNames([locale], { type: 'region' }).of(code) || code;
    } catch {
        return code;
    }
};

export interface CountrySelection {
    code: string;
    name: string;
}

interface CountrySelectProps {
    id?: string;
    value?: string;
    countryName?: string;
    onChange: (country: CountrySelection) => void;
    invalid?: boolean;
}

export function CountrySelect({ id, value, countryName, onChange, invalid }: CountrySelectProps) {
    const { t, i18n } = useTranslation();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const locale = i18n.resolvedLanguage || i18n.language || 'en';
    const countries = useMemo(() => COUNTRY_CODES.map((code) => ({
        code,
        name: regionName(code, locale),
        canonicalName: regionName(code, 'en'),
        flag: flagFor(code),
    })).sort((a, b) => a.name.localeCompare(b.name, locale)), [locale]);
    const selected = countries.find((country) => country.code === value?.toUpperCase());
    const normalizedQuery = query.trim().toLocaleLowerCase(locale);
    const filteredCountries = normalizedQuery
        ? countries.filter((country) => `${country.code} ${country.name} ${country.canonicalName}`.toLocaleLowerCase(locale).includes(normalizedQuery))
        : countries;

    return (
        <Popover open={open} onOpenChange={(nextOpen) => { setOpen(nextOpen); if (!nextOpen) setQuery(''); }}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={invalid}
                    className="w-full justify-between font-normal"
                >
                    <span className={cn('truncate', !selected && !countryName && 'text-muted-foreground')}>
                        {selected ? `${selected.flag} ${selected.name} (${selected.code})` : countryName || t('Select country')}
                    </span>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-[var(--radix-popover-trigger-width)] p-0">
                <Command>
                    <CommandInput value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t('Search country by name or code')} />
                    <CommandList>
                        {filteredCountries.length === 0 ? <CommandEmpty>{t('No country found')}</CommandEmpty> : <CommandGroup>
                            {filteredCountries.map((country) => (
                                <CommandItem
                                    key={country.code}
                                    onSelect={() => {
                                        onChange({ code: country.code, name: country.canonicalName });
                                        setOpen(false);
                                    }}
                                    className="gap-2"
                                >
                                    <Check className={cn('h-4 w-4', country.code === value?.toUpperCase() ? 'opacity-100' : 'opacity-0')} />
                                    <span aria-hidden="true">{country.flag}</span>
                                    <span className="flex-1 truncate">{country.name}</span>
                                    <span className="text-xs font-medium text-muted-foreground">{country.code}</span>
                                </CommandItem>
                            ))}
                        </CommandGroup>}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
