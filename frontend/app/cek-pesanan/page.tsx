import type { Metadata } from 'next';
import SiteHeader from '../site-header';
import TrackForm from './track-form';
export const metadata: Metadata = { title: 'Cek Pesanan | Younz Digital Center' };
export default function TrackPage(){return <main><SiteHeader/><section className="form-page shell narrow-page"><p className="eyebrow">Pelacakan</p><h1>Cek status pesanan</h1><p className="form-lead">Masukkan nomor pesanan dan nomor WhatsApp yang digunakan saat memesan.</p><TrackForm/></section></main>}