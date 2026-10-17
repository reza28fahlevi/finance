import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Format angka ke Rupiah (tanpa desimal bila bulat)
window.formatRupiah = (value) => {
    const n = Number(value || 0);
    return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
};

// Parse input nominal: terima "1.250.000", "1250000,50", atau "1250000.50" (sama dengan TransactionRequest).
window.parseAmount = (input) => {
    let s = String(input ?? '').trim();
    if (/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/.test(s)) s = s.replace(/\./g, '').replace(',', '.');
    else if (/^\d+,\d{1,2}$/.test(s)) s = s.replace(',', '.');
    const n = Number(s);
    return isNaN(n) ? 0 : n;
};

// Form jurnal manual: hitung total debit/kredit & selisih secara real-time.
// Perhitungan memakai sen (integer) agar tidak terkena galat floating point.
Alpine.data('journalForm', (initialLines = []) => ({
    lines: initialLines.length
        ? initialLines
        : [
              { account_id: '', description: '', debit: '', credit: '' },
              { account_id: '', description: '', debit: '', credit: '' },
          ],
    toCents(v) {
        const s = String(v ?? '').replace(',', '.').trim();
        if (s === '' || isNaN(Number(s))) return 0;
        return Math.round(Number(s) * 100);
    },
    get totalDebit() {
        return this.lines.reduce((sum, l) => sum + this.toCents(l.debit), 0);
    },
    get totalCredit() {
        return this.lines.reduce((sum, l) => sum + this.toCents(l.credit), 0);
    },
    get difference() {
        return this.totalDebit - this.totalCredit;
    },
    get balanced() {
        return this.totalDebit > 0 && this.difference === 0;
    },
    money(cents) {
        return window.formatRupiah(cents / 100);
    },
    addLine() {
        this.lines.push({ account_id: '', description: '', debit: '', credit: '' });
    },
    removeLine(i) {
        if (this.lines.length > 2) this.lines.splice(i, 1);
    },
}));

Alpine.start();
