<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberStage extends Model
{
    protected $guarded = ['id'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
