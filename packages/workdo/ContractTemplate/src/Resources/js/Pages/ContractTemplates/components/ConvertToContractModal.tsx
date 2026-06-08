import React from 'react';
import { useForm } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';

interface Props {
    template: any;
    contractTypes: Record<number, string>;
    users: Record<number, string>;
    open: boolean;
    onClose: () => void;
}

export default function ConvertToContractModal({ template, contractTypes, users, open, onClose }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        subject: template.subject,
        type_id: template.contract_type?.id || '',
        description: template.description || '',
        comments_duplicate: false,
        notes_duplicate: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('contract-templates.convert-to-contract', template.id), {
            onSuccess: () => {
                reset();
                onClose();
            }
        });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Convert to Contract</DialogTitle>
                </DialogHeader>
                
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label htmlFor="subject">Subject</Label>
                        <Input
                            id="subject"
                            value={data.subject}
                            onChange={(e) => setData('subject', e.target.value)}
                            error={errors.subject}
                        />
                    </div>

                    <div>
                        <Label htmlFor="type_id">Contract Type</Label>
                        <Select value={data.type_id.toString()} onValueChange={(value) => setData('type_id', parseInt(value))}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select contract type" />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(contractTypes).map(([id, name]) => (
                                    <SelectItem key={id} value={id}>{name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.type_id && <p className="text-sm text-destructive mt-1">{errors.type_id}</p>}
                    </div>

                    <div>
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={3}
                        />
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="comments_duplicate"
                                checked={data.comments_duplicate}
                                onCheckedChange={(checked) => setData('comments_duplicate', !!checked)}
                            />
                            <Label htmlFor="comments_duplicate">Copy comments</Label>
                        </div>
                        
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="notes_duplicate"
                                checked={data.notes_duplicate}
                                onCheckedChange={(checked) => setData('notes_duplicate', !!checked)}
                            />
                            <Label htmlFor="notes_duplicate">Copy notes</Label>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Converting...' : 'Convert to Contract'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}