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
            <form onSubmit={onSubmit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900">{title}</h2>
                <div className="mt-4 space-y-4">{children}</div>
                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                    <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
