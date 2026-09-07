import SiteHeader from './site-header';

type Section = { title: string; body: string };

export default function LegalDocument({ title, sections }: { title: string; sections: Section[] }) {
  return (
    <main>
      <SiteHeader />
      <section className="legal-page shell">
        <article>
          <p className="eyebrow">Dokumen resmi</p>
          <h1>{title}</h1>
          <p className="legal-updated">Terakhir diperbarui: 30 Juli 2026</p>
          <div>{sections.map((section) => <section key={section.title}><h2>{section.title}</h2><p>{section.body}</p></section>)}</div>
        </article>
      </section>
    </main>
  );
}
