import PageShell, { Card, Table, Td, Th } from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import SelectInput from '@/Components/SelectInput';
import { formatDateTime } from '@/lib/format';
import { router } from '@inertiajs/react';

function Values({ values }) {
    if (!values) {
        return <span className="text-gray-400">—</span>;
    }

    return (
        <dl className="space-y-0.5 text-xs">
            {Object.entries(values).map(([key, value]) => (
                <div key={key} className="flex gap-2">
                    <dt className="text-gray-500">{key}:</dt>
                    <dd className="break-all font-mono">{value === null ? 'null' : String(value)}</dd>
                </div>
            ))}
        </dl>
    );
}

export default function AuditIndex({ logs, entities, filters }) {
    return (
        <PageShell title="Audit log">
            <div>
                <SelectInput
                    value={filters.entity ?? ''}
                    onChange={(e) =>
                        router.get(route('audit.index'), { entity: e.target.value || undefined }, { preserveState: true, replace: true })
                    }
                >
                    <option value="">All records</option>
                    {entities.map((entity) => (
                        <option key={entity} value={entity}>{entity}</option>
                    ))}
                </SelectInput>
            </div>

            <Card>
                <Table>
                    <thead className="bg-gray-50">
                        <tr>
                            <Th>When</Th>
                            <Th>Who</Th>
                            <Th>What</Th>
                            <Th>Before</Th>
                            <Th>After</Th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 align-top">
                        {logs.data.map((log) => (
                            <tr key={log.id}>
                                <Td>{formatDateTime(log.created_at)}</Td>
                                <Td>{log.user}</Td>
                                <Td>
                                    <span className="font-medium">{log.action}</span> {log.entity} #{log.entity_id}
                                </Td>
                                <Td className="whitespace-normal"><Values values={log.before} /></Td>
                                <Td className="whitespace-normal"><Values values={log.after} /></Td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
                <Pagination paginator={logs} />
            </Card>
        </PageShell>
    );
}
