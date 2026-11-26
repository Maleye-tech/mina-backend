<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('professional_profiles')) {
            Schema::create('professional_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('display_name');
                $table->string('specialty')->default('psychologue');
                $table->json('languages')->nullable();
                $table->text('bio')->nullable();
                $table->string('photo_path')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->timestamp('charter_accepted_at')->nullable();
                $table->timestamps();

                $table->index(['specialty', 'is_verified']);
            });
        }

        Schema::table('appointment_professionals', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointment_professionals', 'display_name')) {
                $table->string('display_name')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('appointment_professionals', 'specialty')) {
                $table->string('specialty')->default('psychologue')->after('display_name');
            }
            if (! Schema::hasColumn('appointment_professionals', 'languages')) {
                $table->json('languages')->nullable()->after('specialty');
            }
            if (! Schema::hasColumn('appointment_professionals', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('bio');
            }
            if (! Schema::hasColumn('appointment_professionals', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('is_available');
            }
            if (! Schema::hasColumn('appointment_professionals', 'charter_accepted_at')) {
                $table->timestamp('charter_accepted_at')->nullable()->after('is_verified');
            }
        });

        DB::table('appointment_professionals')
            ->whereNull('display_name')
            ->update(['display_name' => DB::raw('title')]);

        if (! Schema::hasTable('availability_slots')) {
            Schema::create('availability_slots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('professional_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->string('mode')->default('visio');
                $table->boolean('is_booked')->default(false);
                $table->timestamps();

                $table->index(['professional_id', 'starts_at']);
                $table->index(['starts_at', 'is_booked']);
            });
        }

        Schema::table('appointments', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointments', 'professional_user_id')) {
                $table->foreignId('professional_user_id')->nullable()->after('professional_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('appointments', 'reason')) {
                $table->string('reason', 255)->nullable();
            }
            if (! Schema::hasColumn('appointments', 'meeting_url')) {
                $table->string('meeting_url', 500)->nullable();
            }
            if (! Schema::hasColumn('appointments', 'responded_at')) {
                $table->timestamp('responded_at')->nullable();
            }
            if (! Schema::hasColumn('appointments', 'availability_slot_id')) {
                $table->foreignId('availability_slot_id')->nullable()->after('slot_id')
                    ->constrained('availability_slots')->nullOnDelete();
            }

            $table->index(['professional_user_id', 'status'], 'appointments_professional_user_status_index');
        });

        DB::table('appointments')
            ->whereNull('professional_user_id')
            ->update([
                'professional_user_id' => DB::raw(
                    '(SELECT user_id FROM appointment_professionals WHERE appointment_professionals.id = appointments.professional_id)'
                ),
            ]);

        Schema::table('chat_conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_conversations', 'appointment_id')) {
                $table->foreignId('appointment_id')->nullable()->after('participant_two_id')
                    ->constrained('appointments')->cascadeOnDelete();
            }

            if ($this->hasIndex('chat_conversations', ['participant_one_id', 'participant_two_id'], unique: true)) {
                $table->dropUnique(['participant_one_id', 'participant_two_id']);
            }

            $table->index(['participant_one_id', 'participant_two_id'], 'chat_conversations_participants_index');
            $table->unique('appointment_id', 'chat_conversations_appointment_unique');
        });

        if (! Schema::hasTable('session_notes')) {
            Schema::create('session_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
                $table->foreignId('professional_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamps();

                $table->unique(['appointment_id', 'professional_id']);
            });
        }

        if (! Schema::hasTable('urgent_alerts')) {
            Schema::create('urgent_alerts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
                $table->text('message')->nullable();
                $table->string('status')->default('open');
                $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('handled_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropUnique('chat_conversations_appointment_unique');
            $table->dropIndex('chat_conversations_participants_index');
            $table->dropConstrainedForeignId('appointment_id');
            $table->unique(['participant_one_id', 'participant_two_id']);
        });

        Schema::dropIfExists('urgent_alerts');
        Schema::dropIfExists('session_notes');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex('appointments_professional_user_status_index');
            $table->dropConstrainedForeignId('professional_user_id');
            $table->dropConstrainedForeignId('availability_slot_id');
            $table->dropColumn(['reason', 'meeting_url', 'responded_at']);
        });

        Schema::dropIfExists('availability_slots');

        Schema::table('appointment_professionals', function (Blueprint $table): void {
            $table->dropColumn(['display_name', 'specialty', 'languages', 'photo_path', 'is_verified', 'charter_accepted_at']);
        });

        Schema::dropIfExists('professional_profiles');
    }

    private function hasIndex(string $table, array $columns, bool $unique = false): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns && $index['unique'] === $unique) {
                return true;
            }
        }

        return false;
    }
};
