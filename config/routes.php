<?php
use Cake\Routing\Router;
Router::prefix('admin', function ($routes) {
    $routes->plugin('Upload', function ($routes) {
        $routes->fallbacks('InflectedRoute');
    });
});
