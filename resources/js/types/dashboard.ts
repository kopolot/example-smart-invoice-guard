export type DashboardSummary = {
    totalInvoices: number;
    collectedRevenue: number;
    outstandingRevenue: number;
    sentInvoices: number;
    averageInvoiceValue: number;
};

export type StatusBreakdownItem = {
    status: 'paid' | 'unpaid' | 'partially_paid';
    label: string;
    count: number;
};

export type MonthlyRevenueItem = {
    month: string;
    label: string;
    revenue: number;
    invoices: number;
};

export type RecentActivityItem = {
    invoiceNumber: string;
    status: string;
    changedAt: string;
};
