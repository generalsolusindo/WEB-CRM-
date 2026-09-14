/** Pesan follow up default, menyapa PIC dan menyebut nama perusahaannya kalau ada. */
export function buildFollowUpMessage(contact, salesName) {
    const greeting = contact.company_name
        ? `Halo ${contact.name} dari ${contact.company_name}`
        : `Halo ${contact.name}`;

    return `${greeting},\n\n`
        + `Saya ${salesName || 'Sales'} dari CV. General Solusindo. Ingin follow up terkait kebutuhan Anda, apakah ada yang bisa kami bantu?\n\n`
        + 'Terima kasih.';
}

/** Link click-to-chat wa.me — null kalau contact tidak punya nomor WhatsApp valid. */
export function contactWhatsappLink(contact, salesName) {
    if (!contact.whatsapp_number) return null;

    return `https://wa.me/${contact.whatsapp_number}?text=${encodeURIComponent(buildFollowUpMessage(contact, salesName))}`;
}
