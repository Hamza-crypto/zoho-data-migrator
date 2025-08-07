<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'category_id',
        'article_id',
        'title',
        'status',
        'created_at_api',
        'updated_at_api',
    ];
}
