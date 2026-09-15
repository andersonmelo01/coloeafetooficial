const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

export function money(value: number): string {
  return brl.format(Number.isFinite(value) ? value : 0);
}

export function parseMoneyInput(value: string): number {
  if (!value) return 0;
  const cleaned = value.replace(/[^\d.,-]/g, '');
  if (!cleaned) return 0;
  const negative = cleaned.startsWith('-');
  let s = cleaned.replace('-', '');

  if (s.includes(',') && s.includes('.')) {
    const lastComma = s.lastIndexOf(',');
    const lastDot = s.lastIndexOf('.');
    s = lastComma > lastDot ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
  } else if (s.includes(',')) {
    s = s.replace(/\./g, '').replace(',', '.');
  }
  const n = parseFloat(s);
  if (Number.isNaN(n)) return 0;
  return Math.round((negative ? -n : n) * 100) / 100;
}

export function maskMoney(value: string): string {
  const n = parseMoneyInput(value);
  return `${n.toFixed(2).replace('.', ',')}`;
}

export function moneyToInput(value: number): string {
  return value.toFixed(2).replace('.', ',');
}

export function parseDateInput(value: string): string {
  const d = value.replace(/\D/g, '');
  if (d.length <= 2) return d;
  if (d.length <= 4) return `${d.slice(0, 2)}/${d.slice(2)}`;
  return `${d.slice(0, 2)}/${d.slice(2, 4)}/${d.slice(4, 8)}`;
}

export function parseDateBR(value: string): string {
  const d = value.replace(/\D/g, '');
  if (d.length < 8) return value;
  return `${d.slice(0, 4)}-${d.slice(4, 6)}-${d.slice(6, 8)}`;
}

export function dateTimeBR(value?: string | null): string {
  if (!value) return '—';
  const d = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return value;
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function dateBR(value?: string | null): string {
  if (!value) return '—';
  const d = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return value;
  const [y, m, day] = value.slice(0, 10).split('-');
  return `${day}/${m}/${y}`;
}

export function daysFromNow(days: number): string {
  const d = new Date();
  d.setDate(d.getDate() + days);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function todayBR(): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  const d = new Date();
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
}