import DashboardShell, { AXIS, ChartCard, COLORS, CURSOR, Empty, GRID, MoneyTooltip, number, percent, profitGhs, shortGhs } from '@/Components/Dashboard';
import { Stat, Table, Td, Th } from '@/Components/PageShell';
import { formatDateTime, formatGhs } from '@/lib/format';
import { router } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export default function Team({ headline, members, selectedMember, selectedStock, filters, products, generatedAt }) {
    const teamRevenue = members.reduce((sum, m) => sum + m.revenue, 0);
    const topSeller = members.find((m) => m.name === headline.top_seller);
    const select = (id) =>
        router.get(route('dashboards.team'), { ...filters, member: id }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <DashboardShell
            title="Marketing team"
            filters={filters}
            products={products}
            generatedAt={generatedAt}
            extra={selectedMember ? { member: selectedMember.id } : {}}
        >
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-6">
                <Stat label="Active members" value={number(headline.active_members)} icon="users" accent="sky" hint={`${number(members.length)} in total`} />
                <Stat label="Units assigned" value={number(headline.assigned)} icon="arrowIn" accent="indigo" hint="After returns" />
                <Stat label="Units sold" value={number(headline.sold)} icon="bag" accent="emerald" hint={`${formatGhs(teamRevenue)} revenue`} />
                <Stat label="Units still held" value={number(headline.held)} icon="team" accent="violet" hint="With members now" />
                <Stat
                    label="Sell-through"
                    value={percent(headline.sell_through)}
                    icon="chart"
                    accent="teal"
                    hint={`${number(headline.sold)} of ${number(headline.assigned)} assigned units`}
                    progress={headline.sell_through}
                />
                <Stat label="Top seller" value={headline.top_seller ?? '—'} icon="star" accent="amber" hint={topSeller ? `${formatGhs(topSeller.revenue)} revenue` : null} />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <ChartCard title="Revenue by member">
                    {members.length === 0 ? (
                        <Empty>No marketing team members yet.</Empty>
                    ) : (
                        <div style={{ height: Math.max(220, members.length * 40 + 40) }}>
                            <ResponsiveContainer>
                                <BarChart data={members} layout="vertical" margin={{ left: 8, right: 16 }}>
                                    <CartesianGrid {...GRID} horizontal={false} />
                                    <XAxis type="number" tickFormatter={shortGhs} {...AXIS} />
                                    <YAxis type="category" dataKey="name" width={120} {...AXIS} />
                                    <Tooltip content={<MoneyTooltip />} cursor={CURSOR} />
                                    <Bar dataKey="revenue" name="Revenue" radius={[0, 3, 3, 0]} onClick={(d) => select(d.user_id)} className="cursor-pointer">
                                        {members.map((m) => (
                                            <Cell key={m.user_id} fill={m.user_id === selectedMember?.id ? COLORS.online : COLORS.team} />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </ChartCard>

                <ChartCard
                    title={selectedMember ? `${selectedMember.name}'s stock by product` : 'Member stock by product'}
                    action={
                        members.length > 0 && (
                            <select
                                value={selectedMember?.id ?? ''}
                                onChange={(e) => select(e.target.value)}
                                className="rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm"
                                aria-label="Choose team member"
                            >
                                {members.map((m) => (
                                    <option key={m.user_id} value={m.user_id}>
                                        {m.name}
                                    </option>
                                ))}
                            </select>
                        )
                    }
                >
                    {selectedStock.length === 0 ? (
                        <Empty>{selectedMember ? `${selectedMember.name} holds no stock at the end of this period.` : 'Choose a member.'}</Empty>
                    ) : (
                        <div style={{ height: Math.max(200, selectedStock.length * 40 + 40) }}>
                            <ResponsiveContainer>
                                <BarChart data={selectedStock} layout="vertical" margin={{ left: 8, right: 16 }}>
                                    <CartesianGrid {...GRID} horizontal={false} />
                                    <XAxis type="number" allowDecimals={false} {...AXIS} />
                                    <YAxis type="category" dataKey="name" width={150} {...AXIS} />
                                    <Tooltip content={<MoneyTooltip money={false} />} cursor={CURSOR} />
                                    <Bar dataKey="quantity" name="Units held" fill={COLORS.withTeam} radius={[0, 3, 3, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </ChartCard>
            </div>

            <ChartCard title="Leaderboard">
                {members.length === 0 ? (
                    <Empty />
                ) : (
                    <div className="-m-4">
                        <Table>
                            <thead className="bg-gray-50">
                                <tr>
                                    <Th>#</Th>
                                    <Th>Member</Th>
                                    <Th className="text-right">Revenue</Th>
                                    <Th className="text-right">Share</Th>
                                    <Th className="text-right">Profit</Th>
                                    <Th className="text-right">Margin</Th>
                                    <Th className="text-right">Sales</Th>
                                    <Th className="text-right">Assigned</Th>
                                    <Th className="text-right">Sold</Th>
                                    <Th className="text-right">Held</Th>
                                    <Th className="text-right">Sell-through</Th>
                                    <Th>Last sale</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {members.map((m, i) => (
                                    <tr key={m.user_id} onClick={() => select(m.user_id)} className={'cursor-pointer hover:bg-gray-50 ' + (m.user_id === selectedMember?.id ? 'bg-sky-50' : '')}>
                                        <Td className="text-gray-400">{i + 1}</Td>
                                        <Td className="font-medium">{m.name}{!m.active && <span className="ml-1 text-xs text-gray-400">(disabled)</span>}</Td>
                                        <Td className="text-right font-medium">{formatGhs(m.revenue)}</Td>
                                        <Td className="text-right">{percent(m.share)}</Td>
                                        <Td className="text-right">{profitGhs(m.gross_profit_pesewas)}</Td>
                                        <Td className="text-right">{percent(m.margin)}</Td>
                                        <Td className="text-right">{number(m.sales)}</Td>
                                        <Td className="text-right">{number(m.assigned)}</Td>
                                        <Td className="text-right">{number(m.sold)}</Td>
                                        <Td className="text-right">{number(m.held)}</Td>
                                        <Td className="text-right">{percent(m.sell_through)}</Td>
                                        <Td className="text-gray-500">{m.last_sale_at ? formatDateTime(m.last_sale_at) : 'Never'}</Td>
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
