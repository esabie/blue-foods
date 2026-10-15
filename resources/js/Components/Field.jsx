import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';

export default function Field({ label, htmlFor, error, hint, className = '', children }) {
    return (
        <div className={className}>
            <InputLabel htmlFor={htmlFor} value={label} />
            <div className="mt-1">{children}</div>
            {hint && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
            <InputError message={error} className="mt-1" />
        </div>
    );
}
