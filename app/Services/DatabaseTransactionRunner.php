<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

class DatabaseTransactionRunner
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(Closure $callback, int $attempts = 1): mixed
    {
        return DB::transaction($callback, $attempts);
    }
}
