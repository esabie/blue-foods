import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { useState } from 'react';

/** A yes/no question before a significant action, e.g. disabling a user or retiring a product. */
export default function ConfirmModal({ show, title, children, confirmLabel, danger = false, onConfirm, onClose }) {
    const [busy, setBusy] = useState(false);
    const Button = danger ? DangerButton : PrimaryButton;

    const confirm = () => {
        setBusy(true);
        onConfirm(() => {
            setBusy(false);
            onClose();
        });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <h2 className="text-lg font-medium text-gray-900">{title}</h2>
                <div className="mt-2 text-sm text-gray-600">{children}</div>
                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                    <Button type="button" disabled={busy} onClick={confirm}>{confirmLabel}</Button>
                </div>
            </div>
        </Modal>
    );
}
