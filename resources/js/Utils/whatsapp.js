/** Sapaan sesuai jam saat ini: pagi (<11), siang (11-15), sore (15-18), malam (>18). */
function timeGreeting(date = new Date()) {
    const hour = date.getHours();
    if (hour < 11) return 'pagi';
    if (hour < 15) return 'siang';
    if (hour < 18) return 'sore';
    return 'malam';
}

/** Pesan follow up default, menyapa PIC pakai sapaan waktu dan memperkenalkan sales yang login. */
export function buildFollowUpMessage(contact, salesName) {
    return `Selamat ${timeGreeting()} pak ${contact.name}, apa kabar hari ini?\n`
        + 'Semoga selalu dalam keadaan sehat dan aktivitas hari ini berjalan lancar yaa.\n\n'
        + `saya ${salesName || 'Sales'} team dari General Solusindo. saat ini kami masih aktif menangani berbagai project IT seperti Instalasi jaringan (LAN/FO/WIFI), Service server, CCTV, hingga Test fluke & Pengadaan perangkat. layanan kami sudah support projek di seluruh wilayah Indonesia.`;
}

/** Link click-to-chat wa.me — null kalau contact tidak punya nomor WhatsApp valid. */
export function contactWhatsappLink(contact, salesName) {
    if (!contact.whatsapp_number) return null;

    return `https://wa.me/${contact.whatsapp_number}?text=${encodeURIComponent(buildFollowUpMessage(contact, salesName))}`;
}
