<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_documents', function (Blueprint $table) {
            $table->string('document_type')
                ->default('other')
                ->after('shipment_id');
        });

        $documents = DB::table('shipment_documents')
            ->select(['id', 'document_type_id'])
            ->get();

        foreach ($documents as $row) {
            $slug = DB::table('document_types')
                ->where('id', $row->document_type_id)
                ->value('slug');

            $documentType = is_string($slug) ? $slug : 'other';

            DB::table('shipment_documents')
                ->where('id', $row->id)
                ->update(['document_type' => $documentType]);
        }

        $valid = [
            'bill-of-lading', 'commercial-invoice', 'customs-declaration',
            'packing-list', 'bill-of-sale', 'title-document',
            'stamp-dock-receipt', 'photos-and-videos', 'other',
        ];

        DB::table('shipment_documents')
            ->whereNotIn('document_type', $valid)
            ->update(['document_type' => 'other']);

        Schema::table('shipment_documents', function (Blueprint $table) {
            $table->dropForeign(['document_type_id']);
            $table->dropColumn('document_type_id');
        });

        Schema::dropIfExists('document_types');
    }

    public function down(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        $now = now();

        $oldCases = [
            'bill-of-lading' => 'Bill of lading',
            'commercial-invoice' => 'Commercial invoice',
            'customs-declaration' => 'Customs declaration',
            'packing-list' => 'Packing list',
            'bill-of-sale' => 'Bill of sale',
            'title-document' => 'Title document',
            'stamp-dock-receipt' => 'Stamp dock receipt',
            'photos-and-videos' => 'Photos and videos',
            'other' => 'Other',
        ];

        foreach ($oldCases as $slug => $name) {
            DB::table('document_types')->insert([
                'name' => $name,
                'slug' => $slug,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('shipment_documents', function (Blueprint $table) {
            $table->foreignId('document_type_id')
                ->nullable()
                ->after('shipment_id')
                ->constrained()
                ->restrictOnDelete();
        });

        $slugToId = DB::table('document_types')->pluck('id', 'slug')->all();

        $documents = DB::table('shipment_documents')
            ->select(['id', 'document_type'])
            ->get();

        $otherId = $slugToId['other'] ?? null;

        foreach ($documents as $row) {
            $typeId = $slugToId[$row->document_type] ?? $otherId;

            DB::table('shipment_documents')
                ->where('id', $row->id)
                ->update(['document_type_id' => $typeId]);
        }

        Schema::table('shipment_documents', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }

    // Private helper methods removed
};
