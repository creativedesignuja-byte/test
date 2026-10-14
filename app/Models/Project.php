<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $guarded = ['id'];

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_members')->withPivot('role');
    }

    public function goal()
    {
        return $this->hasOne(Goal::class);
    }

    public function stages()
    {
        return $this->hasMany(ProductStage::class)->orderBy('position');
    }

    public function memberStages()
    {
        return $this->hasMany(MemberStage::class)->orderBy('position');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
