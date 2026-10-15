import DashboardShell, { AXIS, ChartCard, COLORS, CURSOR, Empty, GRID, LEGEND, MoneyTooltip, number, OnlineShare, percent, profitGhs, ProfitHint, ReorderStatus, shortGhs } from '@/Components/Dashboard';
import { Change, Stat, Table, Td, Th } from '@/Components/PageShell';
import { formatDateTime, formatGhs } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const TONES = {
    red: 'bg-red-50 text-red-800 ring-red-200',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
    blue: 'bg-sky-50 text-sky-800 ring-sky-200',
    gray: 'bg-gray-50 text-gray-700 ring-gray-200',
};

export default function Overview({ headline, revenueByMonth, members, attention, filters, products, generatedAt }) {
    const change = headline.revenue_change;
    const where = [
        { name: 'Not yet assigned', value: headline.unassigned, color: COLORS.unassigned },
        { name: 'With marketing team', value: headline.with_team, color: COLORS.withTeam },
        { name: 'Online shop', value: headline.online, color: COLORS.shop },
    ];

    return (
        <DashboardShell title="Management overview" filters={filters} products={products} generatedAt={generatedAt}>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-6">
                <Stat label="Revenue" value={formatGhs(headline.revenue_pesewas)} icon="banknotes" accent="sky" hint={<Change value={change} />} />
                <Stat
                    label="Gross profit"
                    value={profitGhs(headline.gross_profit_pesewas)}
                    icon="trend"
                    accent="emerald"
                    hint={<ProfitHint profit={headline.gross_profit_pesewas} margin={headline.margin} uncosted={headline.uncosted_units} />}
                />
                <Stat label="Units sold" value={number(headline.units_sold)} icon="bag" accent="indigo" hint={`${number(headline.sales_count)} sales`} />
                <Stat label="Stock remaining" value={number(headline.stock_remaining)} icon="cube" accent="violet" hint={<OnlineShare online={headline.online} total={headline.stock_remaining} />} />
                <Stat label="Not yet assigned" value={number(headline.unassigned)} icon="archive" accent="amber" hint={<ReorderStatus count={headline.below_reorder} />} />
                <Stat
                    label="Team sell‑through"
                    value={percent(headline.sell_through)}
                    icon="chart"
                    accent="teal"
                    hint={`${number(headline.team_sold)} of ${number(headline.team_assigned)} assigned units`}
                    progress={headline.sell_through}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <ChartCard title="Revenue by month" className="lg:col-span-2">
                    <div className="h-56">
                        <ResponsiveContainer>
                            <BarChart data={revenueByMonth} margin={{ left: 0, right: 8 }}>
                                <CartesianGrid {...GRID} vertical={false} />
                                <XAxis dataKey="short_label" {...AXIS} />
                                <YAxis tickFormatter={shortGhs} {...AXIS} width={48} />
                                <Tooltip content={<MoneyTooltip />} cursor={CURSOR} />
                                <Legend {...LEGEND} />
                                <Bar dataKey="team_revenue" name="Marketing team" stackId="r" fill={COLORS.team} />
                                <Bar dataKey="online_revenue" name="Online" stackId="r" fill={COLORS.online} radius={[3, 3, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </ChartCard>

                <ChartCard title="Where the stock is">
                    {headline.stock_remaining > 0 ? (
                        <div className="h-56">
                            <ResponsiveContainer>
                                <PieChart>
                                    <Pie data={where} dataKey="value" nameKey="name" innerRadius="60%" outerRadius="85%" paddingAngle={2} stroke="none">
                                        {where.map((w) => (
                                            <Cell key={w.name} fill={w.color} />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(v) => number(v)} />
                                    <Legend {...LEGEND} />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                    ) : (
                        <Empty>No stock on hand.</Empty>
                    )}
                </ChartCard>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <ChartCard
                    title="Performance by team member"
                    className="lg:col-span-2"
                    action={<Link href={route('dashboards.team', filters)} className="text-xs font-medium text-sky-700 hover:underline">Team dashboard →</Link>}
                >
                    {members.length === 0 ? (
                        <Empty>No marketing team members yet.</Empty>
                    ) : (
                        <div className="-m-4">
                            <Table>
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Th>Member</Th>
                                        <Th className="text-right">Assigned</Th>
                                        <Th className="text-right">Sold</Th>
                                        <Th className="text-right">Held</Th>
                                        <Th className="text-right">Sell-through</Th>
                                        <Th className="text-right">Revenue</Th>
                                        <Th className="text-right">Profit</Th>
                                        <Th>Last sale</Th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {members.map((m) => (
                                        <tr key={m.user_id}>
                                            <Td className="font-medium">{m.name}{!m.active && <span className="ml-1 text-xs text-gray-400">(disabled)</span>}</Td>
                                            <Td className="text-right">{number(m.assigned)}</Td>
                                            <Td className="text-right">{number(m.sold)}</Td>
                                            <Td className="text-right">{number(m.held)}</Td>
                                            <Td className="text-right">{percent(m.sell_through)}</Td>
                                            <Td className="text-right font-medium">{formatGhs(m.revenue)}</Td>
                                            <Td className="text-right">{profitGhs(m.gross_profit_pesewas)}</Td>
                                            <Td className="text-gray-500">{m.last_sale_at ? formatDateTime(m.last_sale_at) : 'Never'}</Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </Table>
                        </div>
                    )}
                </ChartCard>

                <ChartCard title="Needs attention">
                    {attention.length === 0 ? (
                        <Empty>Nothing needs attention right now.</Empty>
                    ) : (
                        <ul className="space-y-2">
                            {attention.map((item, i) => (
                                <li key={i}>
                                    <Link href={route(item.route)} className={'block rounded-md px-3 py-2 text-sm ring-1 hover:opacity-80 ' + TONES[item.tone]}>
                                        {item.text}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </ChartCard>
            </div>
        </DashboardShell>
    );
}
