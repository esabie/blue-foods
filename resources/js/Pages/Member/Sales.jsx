import PageShell, { Card } from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import { SalesTable } from '@/Pages/Sales/Index';

export default function MemberSales({ sales }) {
    return (
        <PageShell title="My sales">
            <Card>
                <SalesTable sales={sales.data} />
                <Pagination paginator={sales} />
            </Card>
        </PageShell>
    );
}
