<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_bosp_inventories', function (Blueprint $table): void {
            $table->id();
            $table->string('nomor_urut', 20)->nullable();
            $table->string('nama_barang', 180);
            $table->unsignedInteger('quality')->default(1);
            $table->unsignedTinyInteger('bulan_beli')->nullable();
            $table->unsignedSmallInteger('tahun_beli')->nullable();
            $table->string('kode_barang', 80)->nullable();
            $table->string('lokasi_barang', 180)->nullable();
            $table->date('tanggal_datang')->nullable();
            $table->decimal('total_harga', 15, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['tahun_beli', 'bulan_beli'], 'sarpras_bosp_tahun_bulan_index');
            $table->index('tanggal_datang', 'sarpras_bosp_tanggal_datang_index');
            $table->index('kode_barang', 'sarpras_bosp_kode_barang_index');
        });

        Schema::create('sarpras_room_inventories', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_gedung', 180);
            $table->string('nama_ruang', 180);
            $table->string('nomor_ruang', 50)->nullable();
            $table->date('tanggal_pendataan')->nullable();
            $table->string('penanggung_jawab', 150)->nullable();
            $table->string('diketahui_oleh', 150)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['nama_gedung', 'nama_ruang'], 'sarpras_room_gedung_ruang_index');
            $table->index('tanggal_pendataan', 'sarpras_room_tanggal_index');
        });

        Schema::create('sarpras_room_inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sarpras_room_inventory_id')
                ->constrained('sarpras_room_inventories')
                ->cascadeOnDelete();
            $table->unsignedInteger('urutan')->default(0);
            $table->date('tanggal')->nullable();
            $table->string('nama_barang', 180);
            $table->unsignedInteger('jumlah')->default(1);
            $table->string('kondisi_barang', 100)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->index(['sarpras_room_inventory_id', 'urutan'], 'sarpras_room_items_owner_order_index');
            $table->index('tanggal', 'sarpras_room_items_tanggal_index');
        });

        Schema::create('sarpras_activities', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal_pengerjaan');
            $table->text('perbaikan');
            $table->string('penanggung_jawab', 150)->nullable();
            $table->text('hasil_akhir')->nullable();
            $table->string('foto_sebelum')->nullable();
            $table->string('foto_sesudah')->nullable();
            $table->string('pelaksana_paraf', 150)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('tanggal_pengerjaan', 'sarpras_activities_tanggal_index');
        });

        Schema::create('sarpras_monthly_agendas', function (Blueprint $table): void {
            $table->id();
            $table->date('bulan_agenda')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->string('jenis_kegiatan', 255);
            $table->string('tindak_lanjut_status', 20)->default('belum');
            $table->string('penanggung_jawab', 150)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['bulan_agenda', 'urutan'], 'sarpras_agenda_bulan_urutan_index');
            $table->index('tindak_lanjut_status', 'sarpras_agenda_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_monthly_agendas');
        Schema::dropIfExists('sarpras_activities');
        Schema::dropIfExists('sarpras_room_inventory_items');
        Schema::dropIfExists('sarpras_room_inventories');
        Schema::dropIfExists('sarpras_bosp_inventories');
    }
};
