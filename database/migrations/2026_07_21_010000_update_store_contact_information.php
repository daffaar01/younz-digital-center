<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->upsertKnowledgeDocument(
            'Jam Operasional',
            'Younz Digital Center buka '.config('services.store.open_hours').'.',
        );

        $this->upsertKnowledgeDocument(
            'Kontak dan Lokasi',
            'Younz Digital Center beralamat di '.config('services.store.address').
                ' dan dapat dihubungi melalui WhatsApp '.config('services.whatsapp.display_number').'.',
        );
    }

    public function down(): void
    {
        DB::table('knowledge_documents')->where('title', 'Kontak dan Lokasi')->delete();
    }

    private function upsertKnowledgeDocument(string $title, string $content): void
    {
        $query = DB::table('knowledge_documents')->where('title', $title);
        $values = [
            'type' => 'faq',
            'content' => $content,
            'status' => 'active',
            'updated_at' => now(),
        ];

        if ($query->exists()) {
            $query->update($values);

            return;
        }

        DB::table('knowledge_documents')->insert($values + [
            'title' => $title,
            'created_at' => now(),
        ]);
    }
};
