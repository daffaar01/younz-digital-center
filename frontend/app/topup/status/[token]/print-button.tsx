'use client';

export default function PrintButton({ label = 'Cetak / Simpan PDF' }: { label?: string }) {
  return (
    <button type="button" className="button button-dark" onClick={() => window.print()}>
      {label}
    </button>
  );
}
