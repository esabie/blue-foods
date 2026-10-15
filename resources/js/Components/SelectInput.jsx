export default function SelectInput({ className = '', children, ...props }) {
    return (
        <select
            {...props}
            className={
                'rounded-md border-gray-300 shadow-sm focus:border-sky-600 focus:ring-sky-600 ' +
                className
            }
        >
            {children}
        </select>
    );
}
