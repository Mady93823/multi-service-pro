import InputError from '@/components/input-error';
import { MediaPicker } from '@/components/media/media-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/lib/i18n';
import { type BlockFieldSchema, type BlockPayload, type MediaAsset } from '@/types';
import { Plus, Trash2 } from 'lucide-react';

type Values = BlockPayload;

interface BlockFieldsProps {
    fields: BlockFieldSchema[];
    values: Values;
    onChange: (name: string, value: BlockPayload[string]) => void;
    /** Errors keyed the way the server sends them: `payload.items.0.title`. */
    errors: Record<string, string>;
    /** Where these fields sit in the payload — the repeater deepens it. */
    path: string;
    /** Thumbnails of the pictures the block already carries, by library asset id. */
    imageUrls: Record<number, string>;
}

/**
 * The admin form for a block, rendered from the schema the block declared (M20).
 * Fourteen block types share this one form: a new field on a block is a line of
 * PHP, not a new React screen.
 */
export function BlockFields({ fields, values, onChange, errors, path, imageUrls }: BlockFieldsProps) {
    const t = useTrans();

    return (
        <div className="space-y-4">
            {fields.map((field) => {
                const key = `${path}.${field.name}`;
                const error = errors[key];
                const value = values[field.name];

                if (field.type === 'repeater') {
                    const rows: Values[] = Array.isArray(value) ? (value as Values[]) : [];

                    const setRows = (next: Values[]) => onChange(field.name, next);

                    return (
                        <div key={field.name} className="grid gap-3 rounded-lg border p-3">
                            <div className="flex items-center justify-between">
                                <Label>{field.label}</Label>
                                <Button type="button" variant="outline" size="sm" onClick={() => setRows([...rows, rowDefaults(field.fields)])}>
                                    <Plus className="h-4 w-4" />
                                    {t('Add')}
                                </Button>
                            </div>

                            {rows.length === 0 && <p className="text-muted-foreground text-sm">{t('Nothing here yet.')}</p>}

                            {rows.map((row, index) => (
                                <div key={index} className="grid gap-3 rounded-md border p-3">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground text-xs">{t('Item :number', { number: String(index + 1) })}</span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Remove')}
                                            onClick={() => setRows(rows.filter((_, at) => at !== index))}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>

                                    <BlockFields
                                        fields={field.fields}
                                        values={row}
                                        onChange={(name, next) =>
                                            setRows(rows.map((current, at) => (at === index ? { ...current, [name]: next } : current)))
                                        }
                                        errors={errors}
                                        path={`${key}.${index}`}
                                        imageUrls={imageUrls}
                                    />
                                </div>
                            ))}

                            {field.help !== null && <p className="text-muted-foreground text-xs">{field.help}</p>}
                            <InputError message={error} />
                        </div>
                    );
                }

                if (field.type === 'media') {
                    const assetId = typeof value === 'number' ? value : null;

                    return (
                        <div key={field.name} className="grid gap-2">
                            <Label>{field.label}</Label>
                            <MediaPicker
                                value={null}
                                onChange={(asset: MediaAsset | null) => onChange(field.name, asset?.id ?? null)}
                                currentUrl={assetId !== null ? (imageUrls[assetId] ?? null) : null}
                                error={error}
                            />
                            {field.help !== null && <p className="text-muted-foreground text-xs">{field.help}</p>}
                        </div>
                    );
                }

                if (field.type === 'toggle') {
                    return (
                        <label key={field.name} className="flex items-center justify-between gap-4 text-sm">
                            <span className="font-medium">{field.label}</span>
                            <Switch checked={value === true} onCheckedChange={(checked) => onChange(field.name, checked)} />
                        </label>
                    );
                }

                return (
                    <div key={field.name} className="grid gap-2">
                        <Label htmlFor={key}>{field.label}</Label>

                        {field.type === 'select' ? (
                            <Select
                                value={value === null || value === undefined || value === '' ? ANY : String(value)}
                                onValueChange={(next) => onChange(field.name, next === ANY ? null : next)}
                            >
                                <SelectTrigger id={key}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {field.options.map((option) => (
                                        // Radix forbids an empty value, so "all / none" rides a sentinel.
                                        <SelectItem key={option.value} value={option.value === '' ? ANY : option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : field.type === 'textarea' || field.type === 'markdown' ? (
                            <Textarea
                                id={key}
                                rows={field.type === 'markdown' ? 10 : 3}
                                value={String(value ?? '')}
                                onChange={(e) => onChange(field.name, e.target.value)}
                            />
                        ) : (
                            <Input
                                id={key}
                                type={field.type === 'number' ? 'number' : 'text'}
                                value={String(value ?? '')}
                                onChange={(e) => onChange(field.name, field.type === 'number' ? Number(e.target.value) : e.target.value)}
                            />
                        )}

                        {field.help !== null && <p className="text-muted-foreground text-xs">{field.help}</p>}
                        <InputError message={error} />
                    </div>
                );
            })}
        </div>
    );
}

/** Radix `SelectItem` cannot hold an empty string, so "no choice" travels as this. */
export const ANY = '__any__';

export function rowDefaults(fields: BlockFieldSchema[]): Values {
    const row: Values = {};

    fields.forEach((field) => {
        row[field.name] = field.default;
    });

    return row;
}
