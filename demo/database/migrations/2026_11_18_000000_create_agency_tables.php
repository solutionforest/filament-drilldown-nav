<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A small agency: clients, their projects, tasks, invoices and tracked time. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('status')->default('active'); // prospect | active | churned
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->unsignedInteger('budget')->default(0);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('todo'); // todo | doing | review | done
            $table->string('priority')->default('normal'); // low | normal | high
            $table->date('due_on')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->unsignedInteger('amount')->default(0);
            $table->string('status')->default('sent'); // draft | sent | paid | overdue
            $table->date('due_on')->nullable();
            $table->timestamps();
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('minutes');
            $table->date('worked_on');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member'); // admin | manager | member | client
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('tasks');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['due_on', 'budget']);
        });
        Schema::dropIfExists('clients');
    }
};
