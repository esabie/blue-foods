import DashboardShell, { AXIS, ChartCard, COLORS, CURSOR, Empty, GRID, LEGEND, MoneyTooltip, number, OnlineShare, share } from '@/Components/Dashboard';
import { Badge, Stat, StatusDot, Table, Td, Th } from '@/Components/PageShell';
import { formatDateTime, formatGhs } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export default function Stock({ headline, stock, movements, filters, products, generatedAt }) {
    const chartData = stock.filter((s) => s.remaining > 0);

    return (
        <DashboardShell title="Stock" filters={filters} products={products} generatedAt={generatedAt}>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-6">
                <Stat label="Units produced" value={number(headline.produced)} icon="plus" accent="sky" hint={`${number(headline.sold)} sold`} />
                <Stat label="Stock remaining" value={number(headline.remaining)} icon="cube" accent="violet" hint={<OnlineShare online={headline.online} total={headline.remaining} />} />
                <Stat label="Not yet assigned" value={number(headline.unassigned)} icon="archive" accent="amber" hint={`In the warehouse · ${share(headline.unassigned, headline.remaining)}`} />
                <Stat label="With marketing team" value={number(headline.with_team)} icon="team" accent="indigo" hint={`${share(headline.with_team, headline.remaining)} of stock on hand`} />
                <Stat label="Value remaining" value={formatGhs(headline.value_remaining_pesewas)} icon="banknotes" accent="emerald" hint="At current prices" />
                <Stat
                    label="Below reorder level"
                    value={number(headline.below_reorder)}
                    tone={headline.below_reorder > 0 ? 'warn' : 'good'}
                    icon="warning"
                    accent={headline.below_reorder > 0 ? 'amber' : 'gray'}
                    hint={
                        headline.below_reorder > 0 ? 'Warehouse stock to top up' : <StatusDot tone="green">Warehouse stock is enough</StatusDot>
                    }
                />
            </div>

            <ChartCard title="Stock position by product">
                {chartData.length === 0 ? (
                    <Empty>No stock on hand at the end of this period.</Empty>
                ) : (
                    <div style={{ height: Math.max(220, chartData.length * 44 + 60) }}>
                        <ResponsiveContainer>
                            <BarChart data={chartData} layout="vertical" margin={{ left: 8, right: 16 }}>
                                <CartesianGrid {...GRID} horizontal={false} />
                                <XAxis type="number" {...AXIS} />
                                <YAxis type="category" dataKey="name" width={150} {...AXIS} />
                                <Tooltip content={<MoneyTooltip money={false} />} cursor={CURSOR} />
                                <Legend {...LEGEND} />
                                <Bar dataKey="unassigned" name="Not yet assigned" stackId="s" fill={COLORS.unassigned} />
                                <Bar dataKey="with_team" name="With marketing team" stackId="s" fill={COLORS.withTeam} />
                                <Bar dataKey="online" name="Online shop" stackId="s" fill={COLORS.shop} radius={[0, 3, 3, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                )}
            </ChartCard>

            <ChartCard title="Stock by product">
                <div className="-m-4">
                    <Table>
                        <thead className="bg-gray-50">
                            <tr>
                                <Th>Product</Th>
                                <Th className="text-right">Produced</Th>
                                <Th className="text-right">Not yet assigned</Th>
                                <Th className="text-right">With team</Th>
                                <Th className="text-right">Online</Th>
                                <Th className="text-right">Sold</Th>
                                <Th className="text-right">Remaining</Th>
                                <Th className="text-right">Value remaining</Th>
                                <Th>Status</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {stock.map((s) => (
                                <tr key={s.product_id}>
                                    <Td>
                                        <div className="font-medium">{s.name}</div>
                                        <div className="text-xs text-gray-500">{s.sku}</div>
                                    </Td>
                                    <Td className="text-right">{number(s.produced)}</Td>
                                    <Td className="text-right">{number(s.unassigned)}</Td>
                                    <Td className="text-right">{number(s.with_team)}</Td>
                                    <Td className="text-right">{number(s.online)}</Td>
                                    <Td className="text-right">{number(s.sold)}</Td>
                                    <Td className="text-right font-medium">{number(s.remaining)}</Td>
                                    <Td className="text-right">{formatGhs(s.value_remaining_pesewas)}</Td>
                                    <Td>
                                        {s.status === 'retired' ? (
                                            <Badge>Retired</Badge>
                                        ) : s.low_stock ? (
                                            <Badge tone="amber">Below reorder ({number(s.reorder_level)})</Badge>
                                        ) : (
                                            <Badge tone="green">OK</Badge>
                                        )}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                </div>
            </ChartCard>

            <ChartCard
                title="Recent movements"
                action={<Link href={route('stock.movements')} className="text-xs font-medium text-sky-700 hover:underline">All movements →</Link>}
            >
                {movements.length === 0 ? (
                    <Empty>No movements in this period.</Empty>
                ) : (
                    <div className="-m-4">
                        <Table>
                            <thead className="bg-gray-50">
                                <tr>
                                    <Th>When</Th>
                                    <Th>Type</Th>
                                    <Th>Product</Th>
                                    <Th className="text-right">Qty</Th>
                                    <Th>From</Th>
                                    <Th>To</Th>
                                    <Th>By</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {movements.map((m) => (
                                    <tr key={m.id}>
                                        <Td className="text-gray-500">{formatDateTime(m.occurred_at)}</Td>
                                        <Td>{m.type_label}</Td>
                                        <Td>{m.product}</Td>
                                        <Td className="text-right font-medium">{number(m.quantity)}</Td>
                                        <Td>{m.from ?? '—'}</Td>
                                        <Td>{m.to ?? '—'}</Td>
                                        <Td className="text-gray-500">{m.user}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </div>
                )}
            </ChartCard>
        </DashboardShell>
    );
}
