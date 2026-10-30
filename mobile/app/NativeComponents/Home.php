<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Home extends NativeComponent
{
    public function registrarEntrada(): void
    {
        // Implementaremos o registro na próxima etapa.
    }

    public function iniciarIntervalo(): void
    {
        // Implementaremos o registro na próxima etapa.
    }

    public function finalizarIntervalo(): void
    {
        // Implementaremos o registro na próxima etapa.
    }

    public function registrarSaida(): void
    {
        // Implementaremos o registro na próxima etapa.
    }

    public function render(): View
    {
        return view('native.home');
    }
}
