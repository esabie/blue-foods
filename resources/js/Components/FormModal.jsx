import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

export default function FormModal({
    show,
    title,
    onClose,
    onSubmit,
    processing,
    submitLabel = 'Save',
    maxWidth = 'lg',
    children,
}) {
    return (
        <Modal show={show} onClose={onClose} maxWidth={maxWidth}>
            <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
                <div className="shrink-0 border-b border-gray-100 px-6 py-4">
                    <h2 className="text-lg font-medium text-gray-900">{title}</h2>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                    <div className="space-y-4">{children}</div>
                </div>
                <div className="flex shrink-0 justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <SecondaryButton type="button" onClick={onClose}>Cancel</SecondaryButton>
                    <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
