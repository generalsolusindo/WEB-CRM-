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
    FiDatabase,
    FiCheckCircle,
    FiPercent,
    FiBarChart2,
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
    ],
    finance: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Invoice', href: '/finance/invoices', icon: FiFileText },
        { label: 'Survey', href: '/finance/surveys', icon: FiClipboard },
        { label: 'Pembayaran', href: '/finance/payments', icon: FiCreditCard },
    ],
    management: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Semua Project', href: '/management/projects', icon: FiCalendar },
        { label: 'Project Manager', href: '/management/project-managers', icon: FiUserCheck },
    ],
    project_manager: [
        { label: 'Project Saya', href: '/project-manager/projects', icon: FiCalendar },
    ],
    administrator: [
        { label: 'Dashboard', href: '/dashboard', icon: FiHome },
        { label: 'Manajemen User', href: '#', icon: FiUserCheck },
        { label: 'Master Data', href: '#', icon: FiDatabase },
        { label: 'Master Data · Pajak', href: '/admin/taxes', icon: FiPercent },
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
