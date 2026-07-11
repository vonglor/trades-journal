<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
          // ປ້ອນ Code ເພີ່ມ Alias ຢູ່ບ່ອນນີ້  👇
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);

        // 🎯 ໃສ່ຄຳສັ່ງນີ້ເພີ່ມເຂົ້າໄປ ເພື່ອແກ້ບັກ ERR_CONNECTION_CLOSED ເທິງ Render
        $middleware->trustProxies(at: '*');
        
        // ຖ້າມີການເອີ້ນໃຊ້ລະບົບ CORS ພາຍໃນ ໃຫ້ບັງຄັບອະນຸຍາດ Origin
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();


    