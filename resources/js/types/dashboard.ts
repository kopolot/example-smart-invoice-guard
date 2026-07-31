export type DashboardSummary = {
    totalInvoices: number;
    collectedRevenue: number;
    outstandingRevenue: number;
    sentInvoices: number;
    averageInvoiceValue: number;
};

export type DashboardOverdue = {
    count: number;
    revenue: number;
};

export type StatusBreakdownItem = {
    status: 'paid' | 'unpaid' | 'partially_paid' | 'overdue';
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

export type HotInvoiceItem = {
    invoiceId: number;
    number: string;
    views: number;
    uniqueVisitors: number;
    heat: number;
    lastSeenAt: string | null;
};

export type InvoicePulse = {
    views: number;
    uniqueVisitors: number;
    lastSeenAt: string | null;
    lastVisitor: string | null;
    number: string | null;
};
