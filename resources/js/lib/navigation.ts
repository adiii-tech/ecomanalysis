import {
    BarChart3,
    Bell,
    Boxes,
    Building2,
    Camera,
    FileBarChart,
    LayoutDashboard,
    Megaphone,
    PlugZap,
    Settings,
    Sparkles,
    Store,
    Truck,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-react';

export interface NavItem {
    label: string;
    href: string;
    icon: typeof LayoutDashboard;
    permission?: string;
    badge?: string;
}

export interface NavSection {
    label: string;
    items: NavItem[];
}

export const NAVIGATION: NavSection[] = [
    {
        label: 'Overview',
        items: [
            { label: 'Command Centre', href: '/dashboard', icon: LayoutDashboard, permission: 'dashboard.kpi_strip.view' },
            { label: 'Finance', href: '/finance', icon: Wallet, permission: 'finance.kpi_strip.view' },
        ],
    },
    {
        label: 'Growth',
        items: [
            { label: 'Marketing', href: '/marketing', icon: Megaphone, permission: 'marketing.kpi_strip.view' },
            { label: 'Instagram', href: '/instagram', icon: Camera, permission: 'instagram.kpi_strip.view' },
            { label: 'Customers & Reviews', href: '/customers', icon: Users, permission: 'customer_intelligence.kpi_strip.view' },
        ],
    },
    {
        label: 'Operations',
        items: [
            { label: 'Marketplace', href: '/marketplace', icon: Store, permission: 'marketplace.kpi_strip.view' },
            { label: 'Operations', href: '/operations', icon: Truck, permission: 'operations.kpi_strip.view' },
            { label: 'Catalog', href: '/catalog', icon: Boxes, permission: 'catalog.kpi_strip.view' },
            { label: 'Inventory', href: '/inventory', icon: Warehouse, permission: 'catalog.stock.view' },
        ],
    },
    {
        label: 'Intelligence',
        items: [
            { label: 'Reports', href: '/reports', icon: FileBarChart, permission: 'reports.library.view' },
            { label: 'Ask AI', href: '/ask-ai', icon: Sparkles, permission: 'ai.chat.view' },
            { label: 'Alerts', href: '/alerts', icon: Bell, permission: 'alerts.events.view' },
        ],
    },
    {
        label: 'Setup',
        items: [
            { label: 'Connectors', href: '/connectors', icon: PlugZap, permission: 'connectors.index.view' },
            { label: 'Admin', href: '/admin/users', icon: Settings, permission: 'admin.users.view' },
        ],
    },
];

export interface ReportLink {
    key: string;
    slug: string;
    label: string;
    category: string;
    description: string;
}

export const REPORT_LINKS: ReportLink[] = [
    { key: 'owner_business_review', slug: 'owner-business-review', label: 'Owner Business Review', category: 'Executive', description: 'One-screen scorecard against the previous period.' },
    { key: 'channel_scorecard', slug: 'channel-scorecard', label: 'Channel Scorecard', category: 'Profit & Margin', description: 'Revenue, orders, AOV, margin % and RTO ranked by channel.' },
    { key: 'discount_impact', slug: 'discount-impact', label: 'Discount Impact', category: 'Profit & Margin', description: 'Did the promo buy volume or burn margin?' },
    { key: 'fee_leakage', slug: 'fee-leakage', label: 'Fee Leakage', category: 'Profit & Margin', description: 'Commission, fixed, shipping and settlement fees per channel.' },
    { key: 'net_realisation', slug: 'net-realisation', label: 'Net Realisation', category: 'Profit & Margin', description: 'Gross to net waterfall per marketplace after fees, discounts and GST.' },
    { key: 'order_profitability', slug: 'order-profitability', label: 'Order Profitability', category: 'Profit & Margin', description: 'Order-level P&L, filterable and exportable.' },
    { key: 'cohort_retention', slug: 'cohort-retention', label: 'Cohort Retention', category: 'Marketing & Customers', description: 'Acquisition month by month repeat rate.' },
    { key: 'geo_cities', slug: 'geo-cities', label: 'Geography', category: 'Marketing & Customers', description: 'Revenue and orders by state and city.' },
    { key: 'channel_cac', slug: 'channel-cac', label: 'Channel CAC', category: 'Marketing & Customers', description: 'CAC and ROAS by acquisition source.' },
    { key: 'new_vs_repeat', slug: 'new-vs-repeat', label: 'New vs Repeat', category: 'Marketing & Customers', description: 'Conversion and ROAS split by customer type.' },
    { key: 'state_roi', slug: 'state-roi', label: 'State ROI', category: 'Marketing & Customers', description: 'Revenue, spend, ROAS and CAC by state.' },
    { key: 'top_customers', slug: 'top-customers', label: 'Top Customers', category: 'Marketing & Customers', description: 'Highest lifetime value customers.' },
    { key: 'inventory_health', slug: 'inventory-health', label: 'Inventory Health', category: 'Operations & Inventory', description: 'Stock levels, turnover and reorder signals by SKU.' },
    { key: 'logistics_performance', slug: 'logistics-performance', label: 'Logistics Performance', category: 'Operations & Inventory', description: 'Shipment status, courier performance and RTO by state.' },
    { key: 'order_aging', slug: 'order-aging', label: 'Order Aging', category: 'Operations & Inventory', description: 'Unshipped orders by age with SLA breach flags.' },
    { key: 'reorder_replenishment', slug: 'reorder-replenishment', label: 'Reorder & Replenishment', category: 'Operations & Inventory', description: 'Days of cover, suggested quantities, ABC class and dead stock.' },
    { key: 'stockout', slug: 'stockout', label: 'Stockout Impact', category: 'Operations & Inventory', description: 'Revenue lost to out-of-stock per SKU.' },
    { key: 'cod_cash_flow', slug: 'cod-cash-flow', label: 'COD Cash Flow', category: 'Returns & Cash', description: 'Collected vs remitted, settlement delays and reconciliation.' },
    { key: 'returns_rto_register', slug: 'returns-rto-register', label: 'Returns & RTO Register', category: 'Returns & Cash', description: 'Line-item register in Tally-matching column format.' },
    { key: 'transaction_ledger', slug: 'transaction-ledger', label: 'Transaction Ledger', category: 'Returns & Cash', description: 'Every payment, refund and failure with gateway and status.' },
    { key: 'pnl_statement', slug: 'pnl-statement', label: 'P&L Statement', category: 'Finance', description: 'Full monthly P&L down to EBITDA.' },
    { key: 'gst_summary', slug: 'gst-summary', label: 'GST Summary', category: 'Finance', description: 'Output tax, HSN-wise, B2C/B2B split.' },
    { key: 'sku_margin_waterfall', slug: 'sku-margin-waterfall', label: 'SKU Margin Waterfall', category: 'Profit & Margin', description: 'MRP to margin, step by step, per SKU.' },
    { key: 'contribution_by_cohort', slug: 'contribution-by-cohort', label: 'Contribution by Cohort', category: 'Marketing & Customers', description: 'Profit per acquisition cohort over time.' },
    { key: 'forecast', slug: 'forecast', label: 'Forecast', category: 'Executive', description: '30/60/90-day sales and inventory projection.' },
    { key: 'stock_ledger', slug: 'stock-ledger', label: 'Stock Ledger', category: 'Operations & Inventory', description: 'Every stock movement, with the shrinkage it adds up to.' },
    { key: 'inventory_valuation', slug: 'inventory-valuation', label: 'Inventory Valuation', category: 'Finance', description: 'Stock value at cost, at retail, and what is aging out.' },
];

export const REPORT_CATEGORIES = [...new Set(REPORT_LINKS.map((report) => report.category))];
