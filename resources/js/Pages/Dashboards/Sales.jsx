import DashboardShell, { AXIS, ChartCard, COLORS, CURSOR, Empty, GRID, LEGEND, MoneyTooltip, number, percent, profitGhs, ProfitHint, shortGhs } from '@/Components/Dashboard';
import { Badge, Change, Stat, Table, Td, Th } from '@/Components/PageShell';
import { formatDateTime, formatGhs } from '@/lib/format';
import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export default function Sales({ headline, revenueByWeek, byProduct, byChannel, channelProfit, latest, filters, products, generatedAt }) {
    const channels = [
        { name: 'Marketing team', value: byChannel.team, color: COLORS.team, profit: channelProfit.team },
        { name: 'Online', value: byChannel.online, color: COLORS.online, profit: channelProfit.online },
    ];
    const channelTotal = byChannel.team + byChannel.online;

    return (
        <DashboardShell title="Sales and revenue" filters={filters} products={products} generatedAt={generatedAt}>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-6">
                <Stat label="Revenue" value={formatGhs(headline.revenue_pesewas)} icon="banknotes" accent="sky" hint={<Change value={headline.revenue_change} />} />
                <Stat
                    label="Gross profit"
                    value={profitGhs(headline.gross_profit_pesewas)}
                    icon="trend"
                    accent="emerald"
                    hint={<ProfitHint profit={headline.gross_profit_pesewas} margin={headline.margin} uncosted={headline.uncosted_units} />}
                />
                <Stat
                    label="Units sold"
                    value={number(headline.units_sold)}
                    icon="bag"
                    accent="violet"
                    hint={headline.sales_count > 0
                        ? `${number(headline.sales_count)} ${headline.sales_count === 1 ? 'sale' : 'sales'} · ${(headline.units_sold / headline.sales_count).toFixed(1)} per sale`
                        : 'No sales'}
                />
                <Stat label="Average sale" value={formatGhs(headline.average_sale_pesewas)} icon="pie" accent="indigo" hint="Revenue per sale" />
                <Stat
                    label="Most units sold"
                    value={headline.most_units?.name ?? '—'}
                    icon="star"
                    accent="amber"
                    hint={headline.most_units ? `${number(headline.most_units.units)} units` : null}
                />
                <Stat
                    label="Sales today"
                    value={formatGhs(headline.today.revenue_pesewas)}
                    icon="clock"
                    accent="teal"
                    hint={`${number(headline.today.sales_count)} ${headline.today.sales_count === 1 ? 'sale' : 'sales'}`}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <ChartCard title="Revenue by week" className="lg:col-span-2">
                    <div className="h-72">
                        <ResponsiveContainer>
                            <BarChart data={revenueByWeek} margin={{ left: 0, right: 8 }}>
                                <CartesianGrid {...GRID} vertical={false} />
                                <XAxis dataKey="short_label" {...AXIS} tick={{ ...AXIS.tick, fontSize: 11 }} interval="preserveStartEnd" minTickGap={12} />
                                <YAxis tickFormatter={shortGhs} {...AXIS} width={48} />
                                <Tooltip content={<MoneyTooltip />} cursor={CURSOR} />
                                <Legend {...LEGEND} />
                                <Bar dataKey="team_revenue" name="Marketing team" stackId="r" fill={COLORS.team} />
                                <Bar dataKey="online_revenue" name="Online" stackId="r" fill={COLORS.online} radius={[3, 3, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </ChartCard>

                <ChartCard title="Revenue by channel">
                    {channelTotal === 0 ? (
                        <Empty>No sales in this period.</Empty>
                    ) : (
                        <>
                            <div className="h-52">
                                <ResponsiveContainer>
                                    <PieChart>
                                        <Pie data={channels} dataKey="value" nameKey="name" innerRadius="60%" outerRadius="90%" paddingAngle={2} stroke="none">
                                            {channels.map((c) => (
                                                <Cell key={c.name} fill={c.color} />
                                            ))}
                                        </Pie>
                                        <Tooltip formatter={(v) => formatGhs(v)} />
                                    </PieChart>
                                </ResponsiveContainer>
                            </div>
                            <dl className="mt-2 space-y-2 text-sm">
                                {channels.map((c) => (
                                    <div key={c.name}>
                                        <div className="flex items-center justify-between">
                                            <dt className="flex items-center gap-2">
                                                <span className="inline-block h-2.5 w-2.5 rounded-full" style={{ background: c.color }} />
                                                {c.name}
                                            </dt>
                                            <dd className="font-medium">
                                                {formatGhs(c.value)} <span className="text-gray-400">({Math.round((c.value / channelTotal) * 1000) / 10}%)</span>
                                            </dd>
                                        </div>
                                        {c.profit.gross_profit_pesewas !== null && c.value > 0 && (
                                            <dd className="pl-[18px] text-xs text-gray-500">
                                                Profit {formatGhs(c.profit.gross_profit_pesewas)} · {percent(c.profit.margin)} margin
                                            </dd>
                                        )}
                                    </div>
                                ))}
                            </dl>
                        </>
                    )}
                </ChartCard>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <ChartCard title="Revenue and profit by product">
                    {byProduct.length === 0 ? (
                        <Empty />
                    ) : (
                        <div style={{ height: Math.max(220, byProduct.length * 52 + 70) }}>
                            <ResponsiveContainer>
                                <BarChart data={byProduct} layout="vertical" margin={{ left: 8, right: 16 }} barGap={2}>
                                    <CartesianGrid {...GRID} horizontal={false} />
                                    <XAxis type="number" tickFormatter={shortGhs} {...AXIS} />
                                    <YAxis type="category" dataKey="name" width={150} {...AXIS} />
                                    <Tooltip content={<MoneyTooltip />} cursor={CURSOR} />
                                    <Legend {...LEGEND} />
                                    <Bar dataKey="revenue" name="Revenue" fill={COLORS.team} radius={[0, 3, 3, 0]} />
                                    <Bar dataKey="gross_profit_pesewas" name="Gross profit" fill={COLORS.profit} radius={[0, 3, 3, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                    {byProduct.length > 0 && (
                        <div className="-mx-4 -mb-4 mt-4 border-t border-gray-100">
                            <Table>
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Th>Product</Th>
                                        <Th className="text-right">Units</Th>
                                        <Th className="text-right">Revenue</Th>
                                        <Th className="text-right">Share</Th>
                                        <Th className="text-right">Profit</Th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {byProduct.map((p) => (
                                        <tr key={p.product_id}>
                                            <Td>{p.name}</Td>
                                            <Td className="text-right">{number(p.units)}</Td>
                                            <Td className="text-right font-medium">{formatGhs(p.revenue)}</Td>
                                            <Td className="text-right">{percent(p.share)}</Td>
                                            <Td className="text-right">
                                                {p.gross_profit_pesewas === null ? (
                                                    <span className="text-amber-700">No cost set</span>
                                                ) : (
                                                    <>
                                                        <div>{formatGhs(p.gross_profit_pesewas)}</div>
                                                        <div className="text-xs text-gray-500">{percent(p.margin)} margin</div>
                                                    </>
                                                )}
                                            </Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </Table>
                        </div>
                    )}
                </ChartCard>

                <ChartCard title="Latest sales">
                    {latest.length === 0 ? (
                        <Empty />
                    ) : (
                        <ul className="-my-2 divide-y divide-gray-100">
                            {latest.map((s) => (
                                <li key={s.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {s.quantity} × {s.product}
                                        </p>
                                        <p className="truncate text-xs text-gray-500">
                                            <Badge tone={s.channel === 'online' ? 'amber' : 'blue'}>{s.channel === 'online' ? 'Online' : 'Team'}</Badge>{' '}
                                            {s.who} · {formatDateTime(s.occurred_at)}
                                        </p>
                                    </div>
                                    <span className="shrink-0 font-semibold">{formatGhs(s.revenue)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </ChartCard>
            </div>
        </DashboardShell>
    );
}
