import {
    FiHome,
    FiUsers,
    FiFileText,
    FiShoppingCart,
    FiClipboard,
    FiPackage,
    FiCalendar,
    FiTruck,
    FiCheckSquare,
    FiRefreshCw,
    FiTool,
    FiCreditCard,
    FiUserCheck,
    FiEdit3,
    FiCheckCircle,
    FiPercent,
    FiBarChart2,
    FiBox,
} from 'react-icons/fi';

/**
 * Menu per role. `href: '#'` = halaman belum dibuat (render non-klik).
 * Dipakai oleh Sidebar.jsx.
 */
const menuConfig = {
    sales: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Contacts', href: '/sales/contacts', icon: FiUserCheck },
        { label: 'Leads & Opportunities', href: '/sales/leads', icon: FiUsers },
        { label: 'Quotations', href: '/sales/quotations', icon: FiFileText },
        { label: 'Sales Orders', href: '/sales/sales-orders', icon: FiShoppingCart },
        { label: 'Report Lead', href: '/sales/reports/leads', icon: FiBarChart2 },
    ],
    procurement: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Procurement Request', href: '/procurement/procurement-requests', icon: FiClipboard },
        { label: 'Pengadaan Project', href: '/procurement/project-procurements', icon: FiTruck },
        { label: 'Vendor & Katalog Produk', href: '/procurement/vendors', icon: FiPackage },
        { label: 'Survey', href: '/procurement/surveys', icon: FiClipboard },
        { label: 'Surveyor & Teknisi', href: '/procurement/technicians', icon: FiTool },
        { label: 'Akun PIC Vendor', href: '/procurement/vendor-accounts', icon: FiUserCheck },
        { label: 'Stok Gudang', href: '/warehouse/items', icon: FiBox },
    ],
    operational: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Semua Project', href: '/operational/projects', icon: FiCalendar },
        { label: 'Menunggu Barang', href: '/operational/projects?status=waiting_resource', icon: FiTruck },
        { label: 'Verifikasi BAST', href: '/operational/projects?status=verification', icon: FiCheckSquare },
        { label: 'Survey', href: '/operational/surveys', icon: FiClipboard },
    ],
    technician: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Tugas Saya', href: '/technician/tasks', icon: FiTool },
        { label: 'Survey', href: '/technician/surveys', icon: FiClipboard },
        { label: 'SOW Saya', href: '/technician/sows', icon: FiFileText },
    ],
    finance: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Invoice', href: '/finance/invoices', icon: FiFileText },
        { label: 'Survey', href: '/finance/surveys', icon: FiClipboard },
        { label: 'Pembayaran Invoice', href: '/finance/payments', icon: FiCreditCard },
        { label: 'Pembayaran Vendor', href: '/finance/procurement-payments', icon: FiTruck },
    ],
    management: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Opportunity', href: '/management/opportunities', icon: FiUsers },
        { label: 'Verifikasi Quotation', href: '/management/quotations', icon: FiFileText },
        { label: 'Semua Quotation', href: '/management/quotations-overview', icon: FiFileText },
        { label: 'Procurement Request', href: '/management/procurement-requests', icon: FiClipboard },
        { label: 'Invoice', href: '/management/invoices', icon: FiCreditCard },
        { label: 'Survey', href: '/management/surveys', icon: FiTool },
        { label: 'Semua Project', href: '/management/projects', icon: FiCalendar },
        { label: 'Profit Project', href: '/management/project-profit', icon: FiPercent },
        { label: 'Project Manager', href: '/management/project-managers', icon: FiUserCheck },
        { label: 'SOW Menunggu TTD', href: '/management/sows', icon: FiFileText },
    ],
    project_manager: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Opportunity Saya', href: '/project-manager/opportunities', icon: FiUsers },
        { label: 'Verifikasi Quotation', href: '/project-manager/quotations', icon: FiFileText },
        { label: 'Persetujuan Pengadaan', href: '/project-manager/procurement-payments', icon: FiCheckSquare },
        { label: 'Project Saya', href: '/project-manager/projects', icon: FiCalendar },
        { label: 'SOW Menunggu TTD', href: '/project-manager/sows', icon: FiFileText },
    ],
    administrator: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Manajemen User', href: '/admin/users', icon: FiUserCheck },
        { label: 'Master Pajak', href: '/admin/taxes', icon: FiPercent },
        { label: 'Tanda Tangan', href: '/admin/signature', icon: FiEdit3 },
    ],
    hr: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Review SOW', href: '/hr/sows', icon: FiFileText },
    ],
    vendor: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'SOW', href: '/vendor/sows', icon: FiFileText },
    ],
    warehouse: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Stok Barang', href: '/warehouse/items', icon: FiBox },
    ],
};

/**
 * Susun daftar menu untuk user tertentu.
 * Item "Submit BAST" hanya untuk technician yang jadi project leader.
 */
export function getMenuForUser(auth) {
    const role = auth?.user?.role;
    const items = [...(menuConfig[role] ?? [])];

    if (role === 'technician' && auth?.isProjectLeader === true) {
        items.push({ label: 'Submit BAST', href: '/technician/tasks', icon: FiCheckCircle });
    }

    return items;
}

export default menuConfig;
