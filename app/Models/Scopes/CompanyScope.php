<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $user = Auth::user();

        if ($user && $user->role !== 'super_admin') {
            $builder->where($model->getTable() . '.company_id', $user->company_id);
        }
    }
}
