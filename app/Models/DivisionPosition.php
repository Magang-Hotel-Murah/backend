<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DivisionPosition extends Pivot
{
    protected $table = 'division_position';
    protected $fillable = ['division_id', 'position_id'];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
}
