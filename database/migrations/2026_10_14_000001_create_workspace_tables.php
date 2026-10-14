<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->string('color')->default('#f07845');
            $t->timestamps();
        });
        Schema::create('project_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role')->default('member');
            $t->unique(['project_id', 'user_id']);
        });
        Schema::create('goals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->text('success_criteria');
            $t->date('start_date');
            $t->date('end_date');
            $t->timestamps();
        });
        foreach (['product_stages', 'member_stages'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->foreignId('project_id')->constrained()->cascadeOnDelete();
                if ($table === 'member_stages') {
                    $t->foreignId('user_id')->constrained();
                }$t->string('title');
                $t->text('description')->nullable();
                $t->date('start_date')->nullable();
                $t->date('end_date')->nullable();
                $t->unsignedInteger('position')->default(0);
                $t->timestamps();
            });
        }
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('stage_id')->constrained('product_stages')->restrictOnDelete();
            $t->foreignId('member_stage_id')->nullable()->constrained('member_stages')->nullOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->date('start_date');
            $t->date('due_date');
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['project_id', 'due_date']);
        });
        Schema::create('task_members', function (Blueprint $t) {
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['task_id', 'user_id']);
        });
        Schema::create('subtasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->unsignedInteger('position')->default(0);
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('labels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('color')->default('#8170b8');
            $t->text('description')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('task_labels', function (Blueprint $t) {
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->foreignId('label_id')->constrained()->cascadeOnDelete();
            $t->primary(['task_id', 'label_id']);
        });
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->string('original_name');
            $t->string('file_name');
            $t->string('mime_type');
            $t->unsignedBigInteger('size');
            $t->string('path');
            $t->timestamps();
        });
        Schema::create('notices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('body');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notices', 'attachments', 'comments', 'task_labels', 'labels', 'subtasks', 'task_members', 'tasks', 'member_stages', 'product_stages', 'goals', 'project_members', 'projects'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
