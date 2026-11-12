<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Identitas platform terpisah (Developer/Owner) - bukan peran operasional.
        Schema::create('platform_owners', function (Blueprint $table) {
            $table->id();
            $table->string('owner_id', 40)->unique();
            $table->string('name', 120);
            $table->string('password');
            $table->boolean('must_change_password')->default(true);
            $table->string('status', 12)->default('ACTIVE'); // ACTIVE | SUSPENDED
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('registration_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_reference', 24)->unique();          // REG-2026-XXXXXX
            $table->string('status_code_hash', 64);                     // token status pemohon (di-hash)
            $table->string('full_name', 120);
            $table->string('identification_type', 20);                  // NRP | NIP | NIK_DINAS | LAINNYA
            $table->text('identification_number');                      // terenkripsi (cast encrypted)
            $table->string('organization', 160);
            $table->string('unit', 160)->nullable();
            $table->string('position', 120)->nullable();
            $table->text('contact_email');                              // terenkripsi
            $table->text('contact_phone')->nullable();                  // terenkripsi
            $table->string('requested_role', 20);                       // ADMINISTRATOR | OPERATOR
            $table->text('access_reason');
            $table->string('recommender_name', 120)->nullable();
            $table->string('recommender_position', 120)->nullable();
            $table->text('recommender_contact')->nullable();            // terenkripsi
            $table->text('verification_info')->nullable();
            $table->string('approval_status', 24)->default('PENDING REVIEW')->index();
            $table->string('approved_role', 20)->nullable();
            $table->text('review_notes')->nullable();                   // catatan yang terlihat oleh pemohon
            $table->unsignedBigInteger('reviewed_by')->nullable();      // platform_owners.id
            $table->timestamp('reviewed_at')->nullable();
            $table->string('trace_id', 14)->nullable()->index();
            $table->timestamps();
        });

        // Setiap transisi status (pendaftaran maupun akun) dicatat di sini.
        Schema::create('status_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20);                         // REGISTRATION | ACCOUNT
            $table->string('subject_id', 40);                           // reference / trace_id
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('actor_type', 10);                           // OWNER | USER | SYSTEM | APPLICANT
            $table->string('actor_label', 60)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['status_transitions', 'registration_requests', 'platform_owners'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
