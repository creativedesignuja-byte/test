<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function stage()
    {
        return $this->belongsTo(ProductStage::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'task_members');
    }

    public function subtasks()
    {
        return $this->hasMany(Subtask::class)->orderBy('position');
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class, 'task_labels');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }
}
