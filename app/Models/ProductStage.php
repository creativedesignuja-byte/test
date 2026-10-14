<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStage extends Model
{
    protected $guarded = ['id'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'stage_id');
    }
}
