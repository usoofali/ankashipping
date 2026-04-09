<?php

declare(strict_types=1);

use App\Enums\ShipmentDocumentType;
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
                ->default(ShipmentDocumentType::Other->value)
                ->after('shipment_id');
        });

        $documents = DB::table('shipment_documents')
            ->select(['id', 'document_type_id'])
            ->get();

        foreach ($documents as $row) {
            $slug = DB::table('document_types')
                ->where('id', $row->document_type_id)
                ->value('slug');

            $documentType = is_string($slug) ? $slug : ShipmentDocumentType::Other->value;

            DB::table('shipment_documents')
                ->where('id', $row->id)
                ->update(['document_type' => $documentType]);
        }

        $valid = ShipmentDocumentType::values();

        DB::table('shipment_documents')
            ->whereNotIn('document_type', $valid)
            ->update(['document_type' => ShipmentDocumentType::Other->value]);

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

        foreach (ShipmentDocumentType::cases() as $case) {
            DB::table('document_types')->insert([
                'name' => $this->documentTypeSeedName($case),
                'slug' => $case->value,
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

        $otherId = $slugToId[ShipmentDocumentType::Other->value] ?? null;

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

    private function documentTypeSeedName(ShipmentDocumentType $case): string
    {
        return match ($case) {
            ShipmentDocumentType::BillOfLading => 'Bill of lading',
            ShipmentDocumentType::CommercialInvoice => 'Commercial invoice',
            ShipmentDocumentType::CustomsDeclaration => 'Customs declaration',
            ShipmentDocumentType::PackingList => 'Packing list',
            ShipmentDocumentType::BillOfSale => 'Bill of sale',
            ShipmentDocumentType::TitleDocument => 'Title document',
            ShipmentDocumentType::StampDockReceipt => 'Stamp dock receipt',
            ShipmentDocumentType::PhotosAndVideos => 'Photos and videos',
            ShipmentDocumentType::Other => 'Other',
        };
    }
};
