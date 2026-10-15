import Field from '@/Components/Field';
import PageShell, { Card } from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';

const textarea = 'block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500';

function ImageField({ label, name, current, form }) {
    const file = form.data[name];
    const preview = file ? URL.createObjectURL(file) : current;

    return (
        <Field label={label} htmlFor={name} error={form.errors[name]} hint="JPG or PNG, up to 4 MB.">
            <div className="flex items-center gap-4">
                {preview ? (
                    <img src={preview} alt="" className="h-20 w-32 rounded-md object-cover ring-1 ring-gray-200" />
                ) : (
                    <div className="flex h-20 w-32 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-400">No photo</div>
                )}
                <input id={name} type="file" accept="image/*" onChange={(e) => form.setData(name, e.target.files[0] ?? null)} className="text-sm" />
            </div>
        </Field>
    );
}

export default function Edit({ values, images, siteUrl }) {
    const form = useForm({ ...values, home_image: null, about_image: null });
    const text = (name, label, props = {}) => (
        <Field label={label} htmlFor={name} error={form.errors[name]}>
            <TextInput id={name} value={form.data[name] ?? ''} onChange={(e) => form.setData(name, e.target.value)} className="block w-full" {...props} />
        </Field>
    );
    const area = (name, label, rows = 3, hint) => (
        <Field label={label} htmlFor={name} error={form.errors[name]} hint={hint}>
            <textarea id={name} rows={rows} value={form.data[name] ?? ''} onChange={(e) => form.setData(name, e.target.value)} className={textarea} />
        </Field>
    );

    const submit = (e) => {
        e.preventDefault();
        form.post(route('website.update'), { forceFormData: true, preserveScroll: true, onSuccess: () => form.setData({ ...form.data, home_image: null, about_image: null }) });
    };

    return (
        <PageShell
            title="Website"
            actions={
                <a href={siteUrl} target="_blank" rel="noreferrer" className="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">
                    View website ↗
                </a>
            }
        >
            <form onSubmit={submit} className="space-y-6">
                <Card title="Home page">
                    <div className="space-y-4 p-4">
                        {text('home_title', 'Headline')}
                        {area('home_intro', 'Introduction', 2)}
                        <ImageField label="Banner photo" name="home_image" current={images.home_image} form={form} />
                    </div>
                </Card>

                <Card title="About us page">
                    <div className="space-y-4 p-4">
                        {text('about_title', 'Title')}
                        {area('about_body', 'Text', 8, 'Leave a blank line between paragraphs.')}
                        <ImageField label="Photo" name="about_image" current={images.about_image} form={form} />
                    </div>
                </Card>

                <Card title="Contact page">
                    <div className="grid gap-4 p-4 sm:grid-cols-2">
                        {text('contact_phone', 'Phone')}
                        {text('contact_email', 'Email', { type: 'email' })}
                        {area('contact_address', 'Address', 2)}
                        {area('contact_hours', 'Opening hours', 2)}
                    </div>
                </Card>

                <Card title="Delivery and collection">
                    <div className="grid gap-4 p-4 sm:grid-cols-2">
                        <Field label="Delivery fee (GHS)" htmlFor="delivery_fee" error={form.errors.delivery_fee} hint="Added to every delivery order. Collection is free.">
                            <TextInput id="delivery_fee" inputMode="decimal" value={form.data.delivery_fee} onChange={(e) => form.setData('delivery_fee', e.target.value)} className="block w-full" />
                        </Field>
                        {text('collection_point', 'Collection point')}
                        <div className="sm:col-span-2">{area('delivery_areas', 'Where you deliver', 2)}</div>
                    </div>
                </Card>

                <Card title="Search engines">
                    <div className="p-4">{area('seo_description', 'Site description', 2, 'Shown under the Blue Foods link in Google results. Aim for about 150 characters.')}</div>
                </Card>

                <div className="flex justify-end">
                    <PrimaryButton disabled={form.processing}>Save website</PrimaryButton>
                </div>
            </form>
        </PageShell>
    );
}
