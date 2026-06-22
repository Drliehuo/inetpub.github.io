<?php
declare(strict_types=1);

return [
    // 前台路由
    '/' => 'HomeController@index',
    '/article/{id}' => 'ArticleController@show',
    '/category/{slug}' => 'CategoryController@index',
    '/tag/{slug}' => 'TagController@index',
    '/page/{slug}' => 'PageController@show',
    
    // 用户认证
    '/login' => 'AuthController@login',
    '/logout' => 'AuthController@logout',
    '/register' => 'AuthController@register',
    
    // 后台路由
    '/admin' => 'AdminController@dashboard',
    '/admin/articles' => 'AdminController@articles',
    '/admin/article/create' => 'AdminController@createArticle',
    '/admin/article/edit/{id}' => 'AdminController@editArticle',
    '/admin/article/delete/{id}' => 'AdminController@deleteArticle',
    '/admin/categories' => 'AdminController@categories',
    '/admin/category/create' => 'AdminController@createCategory',
    '/admin/category/edit/{id}' => 'AdminController@editCategory',
    '/admin/category/delete/{id}' => 'AdminController@deleteCategory',
    '/admin/users' => 'AdminController@users',
    '/admin/settings' => 'AdminController@settings',
    '/admin/cache/clear' => 'AdminController@clearCache',
];
